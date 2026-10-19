<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage, usePoll } from '@inertiajs/vue3';
import {
    CalendarClock,
    ChevronLeft,
    ChevronRight,
    List,
    Plus,
    Trash2,
    Unplug,
} from '@lucide/vue';
import { create, drafts as draftsRoute } from '@/routes/posts';
import {
    cancel as cancelPost,
    index as scheduleRoute,
    store as storeSchedule,
} from '@/routes/schedule';
import { retry as retryTarget } from '@/routes/schedule/targets';
import {
    connect as connectAccount,
    disconnect as disconnectAccount,
} from '@/routes/social-accounts';
import { usePostPilotWorkspace } from '@/composables/usePostPilotWorkspace';
import type {
    ConnectedSocialAccount,
    PostDraft,
    PublicationTarget,
    ScheduledPublication,
    SocialPlatformId,
} from '@/types/postpilot';
import PageHeader from '@/components/postpilot/PageHeader.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Schedule',
                href: scheduleRoute(),
            },
        ],
    },
});

const props = defineProps<{
    scheduledPosts: ScheduledPublication[];
    socialAccounts: ConnectedSocialAccount[];
}>();

const page = usePage();
const workspace = usePostPilotWorkspace(page.props.auth.user.id);
const month = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1));
const selectedDate = ref(dateKey(new Date()));
const viewMode = ref<'month' | 'list'>('month');
const showForm = ref(false);
const selectedDraftId = ref('');
const fileInput = ref<HTMLInputElement | null>(null);
const form = useForm({
    request_key: '',
    content: '',
    scheduled_at: localDateTimeValue(new Date(Date.now() + 60 * 60 * 1000)),
    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',
    account_ids: [] as number[],
    media_data_url: null as string | null,
    media_file_name: null as string | null,
});

const monthLabel = computed(() =>
    new Intl.DateTimeFormat(undefined, {
        month: 'long',
        year: 'numeric',
    }).format(month.value),
);
const calendarDays = computed(() => {
    const year = month.value.getFullYear();
    const monthIndex = month.value.getMonth();
    const firstDay = new Date(year, monthIndex, 1);
    const leadingDays = (firstDay.getDay() + 6) % 7;
    const daysInMonth = new Date(year, monthIndex + 1, 0).getDate();

    return [
        ...Array.from({ length: leadingDays }, () => null),
        ...Array.from({ length: daysInMonth }, (_, index) => {
            const date = new Date(year, monthIndex, index + 1);

            return {
                day: index + 1,
                key: dateKey(date),
            };
        }),
    ];
});
const selectedDraft = computed<PostDraft | undefined>(() =>
    workspace.drafts.value.find((draft) => draft.id === selectedDraftId.value),
);
const scheduledEvents = computed(() =>
    [...props.scheduledPosts].sort(
        (first, second) =>
            new Date(first.scheduledAt).getTime() -
            new Date(second.scheduledAt).getTime(),
    ),
);
const availableAccounts = computed(() =>
    props.socialAccounts.filter(
        (account) => account.status === 'connected' && !account.disconnectedAt,
    ),
);

const platforms: SocialPlatformId[] = ['linkedin', 'instagram', 'x'];
const platformNames: Record<SocialPlatformId, string> = {
    linkedin: 'LinkedIn',
    instagram: 'Instagram',
    x: 'X',
};
const weekDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

function dateKey(date: Date): string {
    const year = date.getFullYear();
    const monthNumber = `${date.getMonth() + 1}`.padStart(2, '0');
    const day = `${date.getDate()}`.padStart(2, '0');

    return `${year}-${monthNumber}-${day}`;
}

function localDateTimeValue(date: Date): string {
    const localDate = new Date(
        date.getTime() - date.getTimezoneOffset() * 60_000,
    );

    return localDate.toISOString().slice(0, 16);
}

function titleFor(post: ScheduledPublication): string {
    return post.content.split('\n')[0]?.slice(0, 80) || 'Untitled post';
}

function eventsForDate(key: string): ScheduledPublication[] {
    return scheduledEvents.value.filter(
        (event) => dateKey(new Date(event.scheduledAt)) === key,
    );
}

function chooseDate(key: string): void {
    selectedDate.value = key;
    const [year, monthNumber, day] = key.split('-').map(Number);
    const selectedDay = new Date(year, monthNumber - 1, day, 12);
    const [hours, minutes] = form.scheduled_at.split('T')[1]?.split(':') ?? [
        '12',
        '00',
    ];

    selectedDay.setHours(Number(hours), Number(minutes), 0, 0);
    form.scheduled_at = localDateTimeValue(selectedDay);
}

function changeMonth(direction: -1 | 1): void {
    month.value = new Date(
        month.value.getFullYear(),
        month.value.getMonth() + direction,
        1,
    );
}

function selectDraft(): void {
    const draft = selectedDraft.value;

    if (!draft) {
        return;
    }

    form.content = draft.content;
    form.media_data_url = draft.mediaDataUrl ?? null;
    form.media_file_name = draft.mediaFileName ?? null;
    form.account_ids = availableAccounts.value
        .filter(
            (account) =>
                account.platform.toLowerCase() === draft.platform.toLowerCase(),
        )
        .map((account) => Number(account.id));
}

function chooseImage(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) {
        return;
    }

    const reader = new FileReader();
    reader.addEventListener('load', () => {
        if (typeof reader.result === 'string') {
            form.media_data_url = reader.result;
            form.media_file_name = file.name;
        }
    });
    reader.readAsDataURL(file);
}

function saveSchedule(): void {
    if (form.processing) {
        return;
    }

    form.clearErrors();

    if (!form.account_ids.length) {
        form.setError('account_ids', 'Choose at least one connected account.');
        return;
    }

    if (!form.request_key) {
        form.request_key = crypto.randomUUID();
    }

    const scheduledAt = new Date(form.scheduled_at);

    if (Number.isNaN(scheduledAt.getTime())) {
        form.setError('scheduled_at', 'Choose a valid date and time.');
        return;
    }

    form.transform((data) => ({
        ...data,
        scheduled_at: scheduledAt.toISOString(),
    })).post(storeSchedule().url, {
        preserveScroll: true,
        onSuccess: () => {
            month.value = new Date(
                scheduledAt.getFullYear(),
                scheduledAt.getMonth(),
                1,
            );
            selectedDate.value = dateKey(scheduledAt);
            showForm.value = false;
            selectedDraftId.value = '';
            form.reset();
            form.request_key = crypto.randomUUID();
            form.scheduled_at = localDateTimeValue(
                new Date(Date.now() + 60 * 60 * 1000),
            );
            form.timezone =
                Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
        },
    });
}

function cancelScheduledPost(post: ScheduledPublication): void {
    if (
        !window.confirm(
            `Cancel “${titleFor(post)}” and its unpublished destinations?`,
        )
    ) {
        return;
    }

    router.delete(cancelPost(post.id).url, { preserveScroll: true });
}

function retryPublication(target: PublicationTarget): void {
    router.post(retryTarget(target.id).url, {}, { preserveScroll: true });
}

function disconnect(account: ConnectedSocialAccount): void {
    if (
        !window.confirm(
            `Disconnect ${account.accountName ?? platformNames[account.platform]}?`,
        )
    ) {
        return;
    }

    router.delete(disconnectAccount(account.id).url, {
        preserveScroll: true,
    });
}

function formatTime(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    }).format(new Date(value));
}

function statusLabel(status: string): string {
    return status.replaceAll('_', ' ');
}

function canCancel(post: ScheduledPublication): boolean {
    return (
        post.status === 'pending' &&
        post.targets.every((target) =>
            ['pending', 'queued'].includes(target.status),
        )
    );
}

watch(selectedDraftId, selectDraft);

onMounted(() => {
    workspace.load();
    form.request_key = crypto.randomUUID();
});

usePoll(15_000, {
    only: ['scheduledPosts', 'socialAccounts'],
});
</script>

<template>
    <Head title="Scheduled publishing" />
    <div
        class="mx-auto flex w-full max-w-[1440px] flex-col gap-7 p-4 sm:p-6 lg:p-8"
    >
        <PageHeader
            title="Scheduled publishing"
            description="Connect social accounts, schedule content, and track each platform's publication status."
            eyebrow="PUBLISHING"
        >
            <template #actions>
                <button
                    type="button"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                    @click="showForm = !showForm"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    Schedule a post
                </button>
            </template>
        </PageHeader>

        <section
            class="grid gap-4 rounded-2xl border border-border bg-card p-5 sm:grid-cols-3 sm:p-6"
            aria-label="Social account connections"
        >
            <div
                v-for="platform in platforms"
                :key="platform"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border p-4"
            >
                <div class="flex min-w-0 flex-col gap-1">
                    <h2 class="font-semibold">{{ platformNames[platform] }}</h2>
                    <template
                        v-for="account in socialAccounts.filter(
                            (item) => item.platform === platform,
                        )"
                        :key="account.id"
                    >
                        <p class="truncate text-sm text-muted-foreground">
                            {{
                                account.accountName ??
                                account.username ??
                                'Connected account'
                            }}
                            <span v-if="account.status !== 'connected'">
                                · {{ statusLabel(account.status) }}
                            </span>
                        </p>
                    </template>
                    <p
                        v-if="
                            !socialAccounts.some(
                                (account) => account.platform === platform,
                            )
                        "
                        class="text-sm text-muted-foreground"
                    >
                        No account connected
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <a
                        :href="connectAccount(platform).url"
                        class="inline-flex min-h-9 items-center justify-center rounded-lg border border-border px-3 text-sm font-medium hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        {{
                            socialAccounts.some(
                                (account) => account.platform === platform,
                            )
                                ? 'Reconnect'
                                : 'Connect'
                        }}
                    </a>
                    <button
                        v-for="account in socialAccounts.filter(
                            (item) =>
                                item.platform === platform &&
                                item.status === 'connected',
                        )"
                        :key="account.id"
                        type="button"
                        class="inline-flex size-9 items-center justify-center rounded-lg text-muted-foreground hover:bg-destructive/10 hover:text-destructive focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        :aria-label="`Disconnect ${account.accountName ?? platformNames[platform]}`"
                        @click="disconnect(account)"
                    >
                        <Unplug class="size-4" aria-hidden="true" />
                    </button>
                </div>
            </div>
        </section>

        <form
            v-if="showForm"
            class="grid gap-5 rounded-2xl border border-border bg-card p-5 sm:p-6"
            @submit.prevent="saveSchedule"
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="grid gap-2 text-sm font-medium">
                    Start from a saved draft
                    <select
                        v-model="selectedDraftId"
                        class="min-h-11 rounded-lg border border-input bg-background px-3 text-sm font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <option value="">Write or select a draft</option>
                        <option
                            v-for="draft in workspace.drafts.value"
                            :key="draft.id"
                            :value="draft.id"
                        >
                            {{ draft.title }} · {{ draft.platform }}
                        </option>
                    </select>
                </label>
                <label class="grid gap-2 text-sm font-medium">
                    Date and time
                    <input
                        v-model="form.scheduled_at"
                        required
                        type="datetime-local"
                        class="min-h-11 rounded-lg border border-input bg-background px-3 text-sm font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    />
                    <span
                        v-if="form.errors.scheduled_at"
                        class="text-destructive"
                    >
                        {{ form.errors.scheduled_at }}
                    </span>
                </label>
            </div>

            <label class="grid gap-2 text-sm font-medium">
                Post content
                <textarea
                    v-model="form.content"
                    required
                    rows="6"
                    maxlength="5000"
                    class="rounded-lg border border-input bg-background px-3 py-2 text-sm font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    placeholder="Write the content you want to publish…"
                />
                <span class="text-xs font-normal text-muted-foreground">
                    {{ form.content.length }} characters
                </span>
                <span v-if="form.errors.content" class="text-destructive">
                    {{ form.errors.content }}
                </span>
            </label>

            <div class="grid gap-3">
                <p class="text-sm font-medium">Publish to connected accounts</p>
                <label
                    v-for="account in availableAccounts"
                    :key="account.id"
                    class="flex items-center gap-3 rounded-lg border border-border p-3 text-sm"
                >
                    <input
                        v-model="form.account_ids"
                        type="checkbox"
                        :value="Number(account.id)"
                        class="size-4 accent-primary"
                    />
                    <span class="flex-1">
                        {{ platformNames[account.platform] }} ·
                        {{
                            account.accountName ?? account.username ?? 'Account'
                        }}
                    </span>
                </label>
                <p
                    v-if="!availableAccounts.length"
                    class="text-sm text-muted-foreground"
                >
                    Connect a social account above before scheduling a post.
                </p>
                <p
                    v-if="form.errors.account_ids"
                    class="text-sm text-destructive"
                >
                    {{ form.errors.account_ids }}
                </p>
            </div>

            <div class="grid gap-2">
                <label for="publication-image" class="text-sm font-medium">
                    Image (optional; required for Instagram)
                </label>
                <input
                    id="publication-image"
                    ref="fileInput"
                    type="file"
                    accept="image/jpeg,image/png"
                    class="block w-full text-sm text-muted-foreground file:mr-3 file:min-h-10 file:rounded-lg file:border-0 file:bg-muted file:px-3 file:text-sm file:font-medium"
                    @change="chooseImage"
                />
                <div v-if="form.media_data_url" class="flex items-center gap-3">
                    <img
                        :src="form.media_data_url"
                        alt="Scheduled post image preview"
                        class="size-16 rounded-lg border border-border object-cover"
                    />
                    <span
                        class="min-w-0 flex-1 truncate text-sm text-muted-foreground"
                    >
                        {{ form.media_file_name ?? 'Post image' }}
                    </span>
                    <button
                        type="button"
                        class="text-sm text-destructive hover:underline"
                        @click="
                            form.media_data_url = null;
                            form.media_file_name = null;
                            if (fileInput) fileInput.value = '';
                        "
                    >
                        Remove
                    </button>
                </div>
                <p
                    v-if="form.errors.media_data_url"
                    class="text-sm text-destructive"
                >
                    {{ form.errors.media_data_url }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <button
                    type="submit"
                    :disabled="form.processing || !availableAccounts.length"
                    class="inline-flex min-h-11 items-center justify-center rounded-lg bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{ form.processing ? 'Scheduling…' : 'Schedule post' }}
                </button>
                <button
                    type="button"
                    class="inline-flex min-h-11 items-center justify-center rounded-lg border border-border px-4 text-sm hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    @click="showForm = false"
                >
                    Close
                </button>
                <Link
                    :href="create()"
                    class="inline-flex min-h-11 items-center px-2 text-sm font-medium text-primary hover:underline"
                >
                    Open post studio
                </Link>
            </div>
            <p class="text-xs text-muted-foreground">
                Times are converted from your device’s timezone and published by
                the server when due.
            </p>
        </form>

        <section
            class="grid items-start gap-6 xl:grid-cols-[minmax(0,1.55fr)_minmax(300px,0.75fr)]"
        >
            <div
                class="flex flex-col gap-4 rounded-2xl border border-border bg-card p-4 sm:p-6"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex size-10 items-center justify-center rounded-lg border border-border hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            aria-label="Previous month"
                            @click="changeMonth(-1)"
                        >
                            <ChevronLeft class="size-4" aria-hidden="true" />
                        </button>
                        <button
                            type="button"
                            class="inline-flex size-10 items-center justify-center rounded-lg border border-border hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            aria-label="Next month"
                            @click="changeMonth(1)"
                        >
                            <ChevronRight class="size-4" aria-hidden="true" />
                        </button>
                        <h2 class="ml-2 text-lg font-semibold">
                            {{ monthLabel }}
                        </h2>
                    </div>
                    <div
                        class="flex rounded-lg bg-muted p-1"
                        aria-label="Schedule view"
                    >
                        <button
                            type="button"
                            class="inline-flex min-h-9 items-center gap-1.5 rounded-md px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            :class="
                                viewMode === 'month'
                                    ? 'bg-card text-foreground shadow-sm'
                                    : 'text-muted-foreground'
                            "
                            :aria-pressed="viewMode === 'month'"
                            @click="viewMode = 'month'"
                        >
                            <CalendarClock class="size-4" aria-hidden="true" />
                            Month
                        </button>
                        <button
                            type="button"
                            class="inline-flex min-h-9 items-center gap-1.5 rounded-md px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            :class="
                                viewMode === 'list'
                                    ? 'bg-card text-foreground shadow-sm'
                                    : 'text-muted-foreground'
                            "
                            :aria-pressed="viewMode === 'list'"
                            @click="viewMode = 'list'"
                        >
                            <List class="size-4" aria-hidden="true" />
                            List
                        </button>
                    </div>
                </div>

                <div
                    v-if="viewMode === 'month'"
                    class="grid grid-cols-7 overflow-hidden rounded-xl border border-border"
                    aria-label="Monthly publishing calendar"
                >
                    <div
                        v-for="day in weekDays"
                        :key="day"
                        class="border-b border-border bg-muted/60 px-1 py-2 text-center text-xs font-medium text-muted-foreground sm:px-3 sm:py-3"
                    >
                        {{ day }}
                    </div>
                    <template
                        v-for="(date, index) in calendarDays"
                        :key="date?.key ?? `blank-${index}`"
                    >
                        <div
                            v-if="date"
                            class="flex min-h-24 min-w-0 flex-col gap-1 border-r border-b border-border p-1.5 text-left sm:min-h-28 sm:p-2"
                            :class="
                                selectedDate === date.key
                                    ? 'bg-primary/[0.04]'
                                    : 'bg-card'
                            "
                        >
                            <button
                                type="button"
                                class="flex size-7 shrink-0 items-center justify-center rounded-full text-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                :class="
                                    selectedDate === date.key
                                        ? 'bg-primary text-primary-foreground'
                                        : 'hover:bg-muted'
                                "
                                :aria-label="`Select ${date.key}`"
                                :aria-pressed="selectedDate === date.key"
                                @click="chooseDate(date.key)"
                            >
                                {{ date.day }}
                            </button>
                            <button
                                v-for="post in eventsForDate(date.key).slice(
                                    0,
                                    2,
                                )"
                                :key="post.id"
                                type="button"
                                class="flex min-w-0 items-center gap-1 rounded-md bg-primary/5 px-1.5 py-1 text-left text-[10px] leading-4 text-primary hover:bg-primary/10 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:text-xs"
                                :title="`${formatTime(post.scheduledAt)} · ${titleFor(post)}`"
                                @click="chooseDate(date.key)"
                            >
                                <span class="shrink-0">{{
                                    formatTime(post.scheduledAt)
                                }}</span>
                                <span class="truncate">{{
                                    titleFor(post)
                                }}</span>
                            </button>
                            <span
                                v-if="eventsForDate(date.key).length > 2"
                                class="px-1 text-[10px] text-muted-foreground"
                            >
                                +{{ eventsForDate(date.key).length - 2 }} more
                            </span>
                        </div>
                        <div
                            v-else
                            class="min-h-24 border-r border-b border-border bg-muted/20 sm:min-h-28"
                            aria-hidden="true"
                        ></div>
                    </template>
                </div>

                <ul
                    v-else-if="scheduledEvents.length"
                    class="flex flex-col divide-y divide-border"
                >
                    <li
                        v-for="post in scheduledEvents"
                        :key="post.id"
                        class="flex flex-wrap items-start justify-between gap-3 py-4"
                    >
                        <div class="flex min-w-0 flex-1 flex-col gap-2">
                            <span class="truncate text-sm font-medium">
                                {{ titleFor(post) }}
                            </span>
                            <span class="text-xs text-muted-foreground">
                                {{ formatDate(post.scheduledAt) }} ·
                                {{ formatTime(post.scheduledAt) }} ·
                                {{ statusLabel(post.status) }}
                            </span>
                            <ul class="flex flex-col gap-2">
                                <li
                                    v-for="target in post.targets"
                                    :key="target.id"
                                    class="flex flex-wrap items-center gap-2 text-xs"
                                >
                                    <span class="font-medium">
                                        {{ platformNames[target.platform] }}
                                        ·
                                        {{
                                            target.accountName ??
                                            target.username
                                        }}
                                    </span>
                                    <span class="text-muted-foreground">
                                        {{ statusLabel(target.status) }}
                                    </span>
                                    <span
                                        v-if="target.safeError"
                                        class="text-destructive"
                                    >
                                        {{ target.safeError }}
                                    </span>
                                    <button
                                        v-if="target.retryable"
                                        type="button"
                                        class="font-medium text-primary hover:underline"
                                        @click="retryPublication(target)"
                                    >
                                        Retry
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <button
                            v-if="canCancel(post)"
                            type="button"
                            class="inline-flex size-9 items-center justify-center rounded-lg text-muted-foreground hover:bg-destructive/10 hover:text-destructive focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            :aria-label="`Cancel ${titleFor(post)}`"
                            @click="cancelScheduledPost(post)"
                        >
                            <Trash2 class="size-4" aria-hidden="true" />
                        </button>
                    </li>
                </ul>
                <p
                    v-else-if="viewMode === 'list'"
                    class="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground"
                >
                    No scheduled publications yet. Choose connected accounts and
                    add a post to the calendar.
                </p>
            </div>

            <aside
                class="flex flex-col gap-4 rounded-2xl border border-border bg-card p-5 sm:p-6"
            >
                <div class="flex flex-col gap-1">
                    <h2 class="text-lg font-semibold">Selected day</h2>
                    <p class="text-sm text-muted-foreground">
                        {{
                            new Intl.DateTimeFormat(undefined, {
                                weekday: 'long',
                                month: 'long',
                                day: 'numeric',
                            }).format(new Date(`${selectedDate}T12:00:00`))
                        }}
                    </p>
                </div>
                <ul
                    v-if="eventsForDate(selectedDate).length"
                    class="flex flex-col gap-3"
                >
                    <li
                        v-for="post in eventsForDate(selectedDate)"
                        :key="post.id"
                        class="flex items-start gap-3 rounded-xl bg-muted/50 p-3"
                    >
                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <p class="truncate text-sm font-medium">
                                {{ titleFor(post) }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ formatTime(post.scheduledAt) }} ·
                                {{ statusLabel(post.status) }}
                            </p>
                            <ul class="flex flex-col gap-1">
                                <li
                                    v-for="target in post.targets"
                                    :key="target.id"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ platformNames[target.platform] }}:
                                    {{ statusLabel(target.status) }}
                                </li>
                            </ul>
                        </div>
                        <button
                            v-if="canCancel(post)"
                            type="button"
                            class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg text-muted-foreground hover:bg-destructive/10 hover:text-destructive focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            :aria-label="`Cancel ${titleFor(post)}`"
                            @click="cancelScheduledPost(post)"
                        >
                            <Trash2 class="size-4" aria-hidden="true" />
                        </button>
                    </li>
                </ul>
                <div
                    v-else
                    class="rounded-xl border border-dashed border-border px-4 py-6 text-center"
                >
                    <p class="text-sm font-medium">
                        Nothing scheduled for this day
                    </p>
                    <p class="mt-1 text-xs leading-5 text-muted-foreground">
                        Choose a day and schedule content for one or more
                        connected accounts.
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex min-h-10 items-center justify-center gap-2 self-start rounded-lg border border-border px-3 text-sm font-medium hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    @click="
                        showForm = true;
                        chooseDate(selectedDate);
                    "
                >
                    <Plus class="size-4" aria-hidden="true" />
                    Schedule on this day
                </button>
                <Link
                    :href="draftsRoute()"
                    class="text-sm font-medium text-primary hover:underline"
                >
                    Browse saved drafts
                </Link>
            </aside>
        </section>
    </div>
</template>
