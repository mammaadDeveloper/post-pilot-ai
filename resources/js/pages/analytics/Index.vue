<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    CalendarClock,
    FileText,
    Lightbulb,
    Plus,
    Sparkles,
} from '@lucide/vue';
import { index as analyticsRoute } from '@/routes/analytics';
import { create, drafts as draftsRoute } from '@/routes/posts';
import { usePostPilotWorkspace } from '@/composables/usePostPilotWorkspace';
import type { Platform, ScheduledPost } from '@/types/postpilot';
import PageHeader from '@/components/postpilot/PageHeader.vue';
import PlatformBadge from '@/components/postpilot/PlatformBadge.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Analytics',
                href: analyticsRoute(),
            },
        ],
    },
});

const user = usePage().props.auth.user;
const workspace = usePostPilotWorkspace(user.id);
const range = ref<7 | 30 | 90>(30);

const cutoff = computed(() => Date.now() - range.value * 24 * 60 * 60 * 1000);
const draftsInRange = computed(() =>
    workspace.drafts.value.filter(
        (draft) => new Date(draft.createdAt).getTime() >= cutoff.value,
    ),
);
const plansInRange = computed(() =>
    workspace.scheduledPosts.value.filter(
        (post) => new Date(post.createdAt).getTime() >= cutoff.value,
    ),
);
const platformTotals = computed(() => {
    const counts = new Map<Platform, number>();

    for (const draft of draftsInRange.value) {
        counts.set(draft.platform, (counts.get(draft.platform) ?? 0) + 1);
    }

    const total = draftsInRange.value.length;

    return (['LinkedIn', 'X', 'Instagram'] as const).map((platform) => ({
        platform,
        count: counts.get(platform) ?? 0,
        percentage:
            total === 0
                ? 0
                : Math.round(((counts.get(platform) ?? 0) / total) * 100),
    }));
});

const activityBuckets = computed(() => {
    const period = (range.value * 24 * 60 * 60 * 1000) / 7;
    const now = Date.now();

    return Array.from({ length: 7 }, (_, index) => {
        const start = now - (6 - index) * period;
        const end = start + period;
        const drafts = draftsInRange.value.filter((draft) => {
            const createdAt = new Date(draft.createdAt).getTime();

            return createdAt >= start && createdAt < end;
        }).length;
        const planned = plansInRange.value.filter((post) => {
            const createdAt = new Date(post.createdAt).getTime();

            return createdAt >= start && createdAt < end;
        }).length;

        return {
            label: new Intl.DateTimeFormat(undefined, {
                month: 'short',
                day: 'numeric',
            }).format(new Date(start)),
            drafts,
            planned,
        };
    });
});
const maxActivity = computed(() =>
    Math.max(
        1,
        ...activityBuckets.value.flatMap((bucket) => [
            bucket.drafts,
            bucket.planned,
        ]),
    ),
);
const activityFeed = computed(() => {
    const events = [
        ...workspace.drafts.value.map((draft) => ({
            id: `draft-${draft.id}`,
            label: `Saved draft: ${draft.title}`,
            platform: draft.platform,
            at: draft.updatedAt,
            kind: 'draft' as const,
        })),
        ...workspace.scheduledPosts.value.map((post: ScheduledPost) => ({
            id: `schedule-${post.id}`,
            label: `Added to plan: ${post.title}`,
            platform: post.platform,
            at: post.createdAt,
            kind: 'schedule' as const,
        })),
    ];

    return events
        .sort(
            (first, second) =>
                new Date(second.at).getTime() - new Date(first.at).getTime(),
        )
        .slice(0, 5);
});

onMounted(() => workspace.load());

function percentageWidth(value: number): string {
    return `${(value / maxActivity.value) * 100}%`;
}
</script>

<template>
    <Head title="Workflow analytics" />
    <div
        class="mx-auto flex w-full max-w-[1440px] flex-col gap-7 p-4 sm:p-6 lg:p-8"
    >
        <PageHeader
            title="Workflow analytics"
            description="A transparent view of local drafting and planning activity, not social reach or engagement."
            eyebrow="WORKSPACE INSIGHTS"
        >
            <template #actions>
                <label
                    class="flex min-h-11 items-center gap-2 rounded-lg border border-border bg-card px-3"
                >
                    <span class="sr-only">Analytics date range</span>
                    <select
                        v-model.number="range"
                        class="min-h-9 bg-transparent text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <option :value="7">Last 7 days</option>
                        <option :value="30">Last 30 days</option>
                        <option :value="90">Last 90 days</option>
                    </select>
                </label>
            </template>
        </PageHeader>

        <p
            v-if="workspace.storageError.value"
            class="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive"
            role="alert"
        >
            {{ workspace.storageError.value }}
        </p>

        <section
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
            aria-label="Activity summary"
        >
            <article
                class="flex flex-col gap-3 rounded-2xl border border-border bg-card p-5"
            >
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm text-muted-foreground">
                        AI posts generated
                    </p>
                    <Sparkles
                        class="size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                </div>
                <p class="text-3xl font-semibold tabular-nums">—</p>
                <p class="text-xs leading-5 text-muted-foreground">
                    Generation history is unavailable until an AI service is
                    connected.
                </p>
            </article>
            <article
                class="flex flex-col gap-3 rounded-2xl border border-border bg-card p-5"
            >
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm text-muted-foreground">Drafts saved</p>
                    <FileText
                        class="size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                </div>
                <p class="text-3xl font-semibold tabular-nums">
                    {{ draftsInRange.length }}
                </p>
                <p class="text-xs leading-5 text-muted-foreground">
                    Drafts created in this browser during the selected period.
                </p>
            </article>
            <article
                class="flex flex-col gap-3 rounded-2xl border border-border bg-card p-5"
            >
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm text-muted-foreground">
                        Planning entries
                    </p>
                    <CalendarClock
                        class="size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                </div>
                <p class="text-3xl font-semibold tabular-nums">
                    {{ plansInRange.length }}
                </p>
                <p class="text-xs leading-5 text-muted-foreground">
                    Items added to the local schedule in this period.
                </p>
            </article>
            <article
                class="flex flex-col gap-3 rounded-2xl border border-border bg-card p-5"
            >
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm text-muted-foreground">
                        Publishing status
                    </p>
                    <span
                        class="size-2 rounded-full bg-amber-500"
                        aria-hidden="true"
                    ></span>
                </div>
                <p class="text-lg font-semibold">Not connected</p>
                <p class="text-xs leading-5 text-muted-foreground">
                    There is no social publishing integration in this workspace.
                </p>
            </article>
        </section>

        <div
            class="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(300px,0.8fr)]"
        >
            <section
                class="flex flex-col gap-5 rounded-2xl border border-border bg-card p-5 sm:p-6"
                aria-labelledby="activity-heading"
            >
                <div class="flex flex-col gap-1">
                    <h2 id="activity-heading" class="text-lg font-semibold">
                        Content activity
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Drafts saved and schedule entries created in the
                        selected period.
                    </p>
                </div>
                <div
                    v-if="draftsInRange.length || plansInRange.length"
                    class="flex flex-col gap-4"
                >
                    <div
                        class="flex flex-wrap items-center gap-4 text-xs text-muted-foreground"
                    >
                        <span class="inline-flex items-center gap-2"
                            ><span class="size-2.5 rounded-sm bg-primary"></span
                            >Drafts saved</span
                        >
                        <span class="inline-flex items-center gap-2"
                            ><span
                                class="size-2.5 rounded-sm bg-[#d6b98c]"
                            ></span
                            >Added to plan</span
                        >
                    </div>
                    <div
                        class="grid grid-cols-7 gap-2"
                        role="img"
                        aria-label="Weekly counts of local drafts and scheduled items"
                    >
                        <div
                            v-for="bucket in activityBuckets"
                            :key="bucket.label"
                            class="flex min-w-0 flex-col items-center gap-2"
                        >
                            <div
                                class="flex h-36 w-full items-end justify-center gap-1 rounded-lg bg-muted/40 px-1 pb-1"
                            >
                                <span
                                    class="w-2/5 rounded-t-sm bg-primary transition-[height]"
                                    :style="{
                                        height: bucket.drafts
                                            ? percentageWidth(bucket.drafts)
                                            : '3px',
                                    }"
                                    :title="`${bucket.drafts} drafts saved`"
                                ></span>
                                <span
                                    class="w-2/5 rounded-t-sm bg-[#d6b98c] transition-[height]"
                                    :style="{
                                        height: bucket.planned
                                            ? percentageWidth(bucket.planned)
                                            : '3px',
                                    }"
                                    :title="`${bucket.planned} items added to plan`"
                                ></span>
                            </div>
                            <span
                                class="truncate text-[10px] text-muted-foreground sm:text-xs"
                                >{{ bucket.label }}</span
                            >
                            <span class="sr-only"
                                >{{ bucket.drafts }} drafts saved and
                                {{ bucket.planned }} plan items</span
                            >
                        </div>
                    </div>
                </div>
                <div
                    v-else
                    class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-border bg-muted/30 px-5 py-10 text-center"
                >
                    <FileText
                        class="size-6 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <div class="flex flex-col gap-1">
                        <p class="text-sm font-medium">
                            No activity in this period
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Save a draft or add one to your editorial plan to
                            see local workflow activity.
                        </p>
                    </div>
                    <Link
                        :href="create()"
                        class="inline-flex min-h-9 items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-primary hover:bg-primary/5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        Create a post
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                </div>
            </section>

            <section
                class="flex flex-col gap-5 rounded-2xl border border-border bg-card p-5 sm:p-6"
                aria-labelledby="platform-heading"
            >
                <div class="flex flex-col gap-1">
                    <h2 id="platform-heading" class="text-lg font-semibold">
                        Drafts by platform
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Distribution of drafts created in the selected period.
                    </p>
                </div>
                <ul class="flex flex-col gap-4">
                    <li
                        v-for="item in platformTotals"
                        :key="item.platform"
                        class="flex flex-col gap-2"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <PlatformBadge :platform="item.platform" />
                            <span
                                class="text-sm text-muted-foreground tabular-nums"
                                >{{ item.count }} · {{ item.percentage }}%</span
                            >
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full rounded-full bg-primary transition-[width]"
                                :style="{ width: `${item.percentage}%` }"
                            ></div>
                        </div>
                    </li>
                </ul>
                <p
                    v-if="!draftsInRange.length"
                    class="rounded-lg bg-muted/50 p-3 text-xs leading-5 text-muted-foreground"
                >
                    Platform percentages appear after you save drafts. No sample
                    data is included.
                </p>
            </section>
        </div>

        <div
            class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(300px,0.8fr)]"
        >
            <section
                class="flex flex-col gap-4 rounded-2xl border border-border bg-card p-5 sm:p-6"
                aria-labelledby="feed-heading"
            >
                <div class="flex flex-col gap-1">
                    <h2 id="feed-heading" class="text-lg font-semibold">
                        Recent workspace activity
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Events recorded in this browser.
                    </p>
                </div>
                <ul
                    v-if="activityFeed.length"
                    class="flex flex-col divide-y divide-border"
                >
                    <li
                        v-for="event in activityFeed"
                        :key="event.id"
                        class="flex items-center justify-between gap-3 py-3"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted"
                            >
                                <FileText
                                    v-if="event.kind === 'draft'"
                                    class="size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <CalendarClock
                                    v-else
                                    class="size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />
                            </span>
                            <div class="flex min-w-0 flex-col gap-1">
                                <p class="truncate text-sm font-medium">
                                    {{ event.label }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        new Intl.DateTimeFormat(undefined, {
                                            dateStyle: 'medium',
                                            timeStyle: 'short',
                                        }).format(new Date(event.at))
                                    }}
                                </p>
                            </div>
                        </div>
                        <PlatformBadge :platform="event.platform" compact />
                    </li>
                </ul>
                <p
                    v-else
                    class="rounded-xl border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground"
                >
                    No local activity has been recorded yet.
                </p>
            </section>

            <aside
                class="flex flex-col gap-4 rounded-2xl border border-border bg-[#3f091d] p-5 text-white sm:p-6"
            >
                <Lightbulb class="size-5 text-[#e0c295]" aria-hidden="true" />
                <h2 class="text-lg font-semibold">A note on these insights</h2>
                <p class="text-sm leading-6 text-white/75">
                    This view only summarizes drafts and planning entries saved
                    in this browser. AI generation, publishing, engagement, and
                    cross-device activity are not connected.
                </p>
                <Link
                    :href="draftsRoute()"
                    class="inline-flex min-h-10 w-fit items-center gap-2 rounded-lg bg-white px-3 text-sm font-medium text-[#3f091d] hover:bg-white/90 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none"
                >
                    Browse drafts
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Link>
            </aside>
        </div>
    </div>
</template>
