<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    CalendarClock,
    ChevronLeft,
    ChevronRight,
    List,
    Plus,
    Trash2,
} from '@lucide/vue';
import { create, drafts as draftsRoute } from '@/routes/posts';
import { index as scheduleRoute } from '@/routes/schedule';
import { usePostPilotWorkspace } from '@/composables/usePostPilotWorkspace';
import type { ScheduleStatus, ScheduledPost } from '@/types/postpilot';
import PageHeader from '@/components/postpilot/PageHeader.vue';
import PlatformBadge from '@/components/postpilot/PlatformBadge.vue';

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

const user = usePage().props.auth.user;
const workspace = usePostPilotWorkspace(user.id);
const month = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1));
const selectedDate = ref(dateKey(new Date()));
const viewMode = ref<'month' | 'list'>('month');
const showForm = ref(false);
const selectedDraftId = ref('');
const scheduledAt = ref(
    localDateTimeValue(new Date(Date.now() + 60 * 60 * 1000)),
);
const status = ref<ScheduleStatus>('Scheduled');
const error = ref('');
const notice = ref('');

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
const selectedDraft = computed(() =>
    workspace.drafts.value.find((draft) => draft.id === selectedDraftId.value),
);
const scheduledEvents = computed(() =>
    [...workspace.scheduledPosts.value].sort(
        (first, second) =>
            new Date(first.scheduledAt).getTime() -
            new Date(second.scheduledAt).getTime(),
    ),
);

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

function eventsForDate(key: string): ScheduledPost[] {
    return scheduledEvents.value.filter(
        (event) => dateKey(new Date(event.scheduledAt)) === key,
    );
}

function chooseDate(key: string): void {
    selectedDate.value = key;
    const [year, monthNumber, day] = key.split('-').map(Number);
    const selectedDay = new Date(year, monthNumber - 1, day, 12);
    const [hours, minutes] = scheduledAt.value.split('T')[1]?.split(':') ?? [
        '12',
        '00',
    ];

    selectedDay.setHours(Number(hours), Number(minutes), 0, 0);
    scheduledAt.value = localDateTimeValue(selectedDay);
}

function changeMonth(direction: -1 | 1): void {
    month.value = new Date(
        month.value.getFullYear(),
        month.value.getMonth() + direction,
        1,
    );
}

function saveSchedule(): void {
    error.value = '';
    notice.value = '';

    if (!selectedDraft.value) {
        error.value = 'Choose a saved draft before adding it to your plan.';
        return;
    }

    const scheduledDate = new Date(scheduledAt.value);

    if (Number.isNaN(scheduledDate.getTime())) {
        error.value = 'Choose a valid date and time.';
        return;
    }

    const saved = workspace.saveScheduledPost({
        draftId: selectedDraft.value.id,
        title: selectedDraft.value.title,
        platform: selectedDraft.value.platform,
        scheduledAt: scheduledDate.toISOString(),
        status: status.value,
    });

    if (!saved) {
        error.value =
            workspace.storageError.value ??
            'The plan item could not be saved in this browser.';
        return;
    }

    month.value = new Date(
        scheduledDate.getFullYear(),
        scheduledDate.getMonth(),
        1,
    );
    selectedDate.value = dateKey(scheduledDate);
    showForm.value = false;
    notice.value = 'Added to your local editorial plan.';
}

function removeEvent(event: ScheduledPost): void {
    if (window.confirm(`Remove “${event.title}” from your local plan?`)) {
        const removed = workspace.removeScheduledPost(event.id);
        notice.value = removed
            ? 'Removed from your local editorial plan.'
            : (workspace.storageError.value ??
              'The plan item could not be removed.');
    }
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

onMounted(() => workspace.load());
</script>

<template>
    <Head title="Editorial schedule" />
    <div
        class="mx-auto flex w-full max-w-[1440px] flex-col gap-7 p-4 sm:p-6 lg:p-8"
    >
        <PageHeader
            title="Editorial schedule"
            description="Plan when to share your ideas. This calendar is an internal organizer, not a connected publisher."
            eyebrow="PLANNING"
        >
            <template #actions>
                <button
                    type="button"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                    @click="showForm = !showForm"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    Plan a post
                </button>
            </template>
        </PageHeader>

        <p
            v-if="workspace.storageError.value"
            class="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive"
            role="alert"
        >
            {{ workspace.storageError.value }}
        </p>
        <p v-if="error" class="text-sm text-destructive" role="alert">
            {{ error }}
        </p>
        <p v-if="notice" class="text-sm text-muted-foreground" role="status">
            {{ notice }}
        </p>

        <form
            v-if="showForm"
            class="grid gap-4 rounded-2xl border border-border bg-card p-5 sm:grid-cols-2 sm:p-6 xl:grid-cols-[1.2fr_1fr_1fr_auto]"
            @submit.prevent="saveSchedule"
        >
            <label class="grid gap-2 text-sm font-medium">
                Saved draft
                <select
                    v-model="selectedDraftId"
                    required
                    class="min-h-11 rounded-lg border border-input bg-background px-3 text-sm font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                    <option value="" disabled>Select a draft</option>
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
                    v-model="scheduledAt"
                    required
                    type="datetime-local"
                    class="min-h-11 rounded-lg border border-input bg-background px-3 text-sm font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring"
                />
            </label>
            <label class="grid gap-2 text-sm font-medium">
                Workflow state
                <select
                    v-model="status"
                    class="min-h-11 rounded-lg border border-input bg-background px-3 text-sm font-normal outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                    <option>Scheduled</option>
                    <option>Draft</option>
                    <option>Needs Attention</option>
                </select>
            </label>
            <div class="flex items-end gap-2 sm:col-span-2 xl:col-span-1">
                <button
                    type="submit"
                    :disabled="!workspace.drafts.value.length"
                    class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Add to plan
                </button>
                <button
                    type="button"
                    class="inline-flex min-h-11 items-center justify-center rounded-lg border border-border px-3 text-sm hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    @click="showForm = false"
                >
                    Cancel
                </button>
            </div>
            <p
                v-if="!workspace.drafts.value.length"
                class="text-sm text-muted-foreground sm:col-span-2 xl:col-span-4"
            >
                Create a draft before planning a post.
                <Link
                    :href="create()"
                    class="font-medium text-primary hover:underline"
                >
                    Open the post studio
                </Link>
            </p>
            <p
                class="text-xs text-muted-foreground sm:col-span-2 xl:col-span-4"
            >
                Times use your device’s local timezone. Adding a post here does
                not publish it.
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
                    aria-label="Monthly editorial calendar"
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
                            class="flex min-h-24 min-w-0 flex-col gap-1 border-r border-b border-border p-1.5 text-left last:border-r-0 sm:min-h-28 sm:p-2"
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
                                v-for="event in eventsForDate(date.key).slice(
                                    0,
                                    2,
                                )"
                                :key="event.id"
                                type="button"
                                class="flex min-w-0 items-center gap-1 rounded-md bg-primary/5 px-1.5 py-1 text-left text-[10px] leading-4 text-primary hover:bg-primary/10 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:text-xs"
                                :title="`${formatTime(event.scheduledAt)} · ${event.title}`"
                                @click="chooseDate(date.key)"
                            >
                                <span class="shrink-0">{{
                                    formatTime(event.scheduledAt)
                                }}</span>
                                <span class="truncate">{{ event.title }}</span>
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
                        v-for="event in scheduledEvents"
                        :key="event.id"
                        class="flex flex-wrap items-center justify-between gap-3 py-4"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <PlatformBadge :platform="event.platform" compact />
                            <div class="flex min-w-0 flex-col gap-1">
                                <span class="truncate text-sm font-medium">{{
                                    event.title
                                }}</span>
                                <span class="text-xs text-muted-foreground"
                                    >{{ formatDate(event.scheduledAt) }} ·
                                    {{ formatTime(event.scheduledAt) }}</span
                                >
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="rounded-full bg-muted px-2.5 py-1 text-xs"
                                >{{ event.status }}</span
                            >
                            <button
                                type="button"
                                class="inline-flex size-9 items-center justify-center rounded-lg text-muted-foreground hover:bg-destructive/10 hover:text-destructive focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                :aria-label="`Remove ${event.title} from schedule`"
                                @click="removeEvent(event)"
                            >
                                <Trash2 class="size-4" aria-hidden="true" />
                            </button>
                        </div>
                    </li>
                </ul>
                <p
                    v-else
                    class="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground"
                >
                    No posts in your plan yet. Choose a saved draft and add a
                    time to get started.
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
                        v-for="event in eventsForDate(selectedDate)"
                        :key="event.id"
                        class="flex items-start gap-3 rounded-xl bg-muted/50 p-3"
                    >
                        <PlatformBadge :platform="event.platform" compact />
                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <p class="truncate text-sm font-medium">
                                {{ event.title }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ formatTime(event.scheduledAt) }} ·
                                {{ event.status }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg text-muted-foreground hover:bg-destructive/10 hover:text-destructive focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            :aria-label="`Remove ${event.title} from schedule`"
                            @click="removeEvent(event)"
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
                        Nothing planned for this day
                    </p>
                    <p class="mt-1 text-xs leading-5 text-muted-foreground">
                        Your calendar is for planning only; posts are never
                        published automatically.
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
                    Plan on this day
                </button>
                <Link
                    :href="draftsRoute()"
                    class="text-sm font-medium text-primary hover:underline"
                >
                    Browse saved drafts
                </Link>
            </aside>
        </section>

        <p class="text-xs text-muted-foreground">
            Schedule entries are saved in this browser using your local
            timezone. They do not publish to social platforms.
        </p>
    </div>
</template>
