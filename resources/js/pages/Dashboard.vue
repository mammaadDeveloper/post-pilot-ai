<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    CalendarDays,
    ChartNoAxesCombined,
    FileText,
    Plus,
    Sparkles,
} from '@lucide/vue';
import { computed, onMounted } from 'vue';
import { dashboard } from '@/routes';
import { create as createPost, drafts as draftsRoute } from '@/routes/posts';
import { index as scheduleRoute } from '@/routes/schedule';
import { index as analyticsRoute } from '@/routes/analytics';
import PageHeader from '@/components/postpilot/PageHeader.vue';
import PlatformBadge from '@/components/postpilot/PlatformBadge.vue';
import PostComposer from '@/components/postpilot/PostComposer.vue';
import { usePostPilotWorkspace } from '@/composables/usePostPilotWorkspace';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const user = usePage().props.auth.user;
const firstName = user.name.trim().split(/\s+/)[0] || 'there';
const workspace = usePostPilotWorkspace(user.id);
const today = new Intl.DateTimeFormat(undefined, {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
}).format(new Date());
const recentDrafts = computed(() =>
    [...workspace.drafts.value]
        .sort(
            (first, second) =>
                new Date(second.updatedAt).getTime() -
                new Date(first.updatedAt).getTime(),
        )
        .slice(0, 3),
);

onMounted(() => workspace.load());
</script>

<template>
    <Head title="Dashboard" />

    <div
        class="mx-auto flex w-full max-w-[1440px] flex-col gap-8 p-4 sm:p-6 lg:p-8"
    >
        <PageHeader
            :title="`Welcome back, ${firstName}`"
            description="Your editorial workspace, ready for the next good idea."
            :eyebrow="today"
        >
            <template #actions>
                <Link
                    :href="createPost()"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    Create a post
                </Link>
            </template>
        </PageHeader>

        <section
            class="grid gap-4 sm:grid-cols-3"
            aria-label="Workspace activity"
        >
            <article
                class="flex items-center gap-4 rounded-2xl border border-border bg-card p-5"
            >
                <span
                    class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/5 text-primary"
                >
                    <Sparkles class="size-5" aria-hidden="true" />
                </span>
                <div>
                    <p class="text-sm text-muted-foreground">
                        AI writing assistant
                    </p>
                    <p class="text-2xl font-semibold">Available</p>
                    <p class="text-xs text-muted-foreground">
                        In the post composer
                    </p>
                </div>
            </article>
            <article
                class="flex items-center gap-4 rounded-2xl border border-border bg-card p-5"
            >
                <span
                    class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-secondary/10 text-secondary"
                >
                    <FileText class="size-5" aria-hidden="true" />
                </span>
                <div>
                    <p class="text-sm text-muted-foreground">Saved drafts</p>
                    <p class="text-2xl font-semibold tabular-nums">
                        {{ workspace.draftCount.value }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Stored on this device
                    </p>
                </div>
            </article>
            <article
                class="flex items-center gap-4 rounded-2xl border border-border bg-card p-5"
            >
                <span
                    class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[#f5ebf3] text-[#5a1f32] dark:bg-[#5a1f32]/40 dark:text-[#ffd9e0]"
                >
                    <ChartNoAxesCombined class="size-5" aria-hidden="true" />
                </span>
                <div>
                    <p class="text-sm text-muted-foreground">
                        Active platforms
                    </p>
                    <p class="text-2xl font-semibold tabular-nums">
                        {{ workspace.activePlatformCount.value }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Used by your saved drafts
                    </p>
                </div>
            </article>
        </section>

        <section
            class="relative isolate overflow-hidden rounded-2xl bg-[#3f091d] p-6 text-white shadow-sm sm:p-8"
            aria-labelledby="workspace-heading"
        >
            <div
                class="pointer-events-none absolute -top-24 -right-14 -z-10 size-72 rounded-full border border-white/10"
                aria-hidden="true"
            >
                <div
                    class="absolute inset-8 rounded-full border border-white/10"
                ></div>
            </div>
            <div class="flex max-w-3xl flex-col items-start gap-3">
                <span
                    class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-medium text-white/90"
                >
                    <Sparkles
                        class="size-3.5 text-[#e0c295]"
                        aria-hidden="true"
                    />
                    Your workspace is ready
                </span>
                <div class="flex flex-col gap-3">
                    <h2
                        id="workspace-heading"
                        class="max-w-xl text-2xl leading-tight font-semibold tracking-tight sm:text-3xl"
                    >
                        A better post begins with a clear point of view.
                    </h2>
                    <p
                        class="max-w-xl text-sm leading-6 text-white/75 sm:text-base"
                    >
                        Draft your own copy, tune its voice for each channel,
                        and keep your ideas organized. AI writing is available
                        in the composer when a server-side provider is
                        configured. Social publishing is not connected yet.
                    </p>
                </div>
            </div>
        </section>

        <PostComposer compact @saved="workspace.load()" />

        <div class="grid gap-6 lg:grid-cols-12">
            <section
                class="flex flex-col gap-5 rounded-2xl border border-border bg-card p-5 sm:p-6 lg:col-span-8"
                aria-labelledby="drafts-heading"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex flex-col gap-1">
                        <h2
                            id="drafts-heading"
                            class="text-lg font-semibold tracking-tight"
                        >
                            Recent drafts
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Pick up where you left off.
                        </p>
                    </div>
                    <Link
                        :href="draftsRoute()"
                        class="inline-flex min-h-10 items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-primary hover:bg-primary/5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        All drafts
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                </div>
                <div
                    v-if="workspace.storageError.value"
                    class="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive"
                    role="alert"
                >
                    {{ workspace.storageError.value }}
                </div>
                <ul
                    v-else-if="recentDrafts.length"
                    class="flex flex-col divide-y divide-border"
                >
                    <li
                        v-for="draft in recentDrafts"
                        :key="draft.id"
                        class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <div class="flex min-w-0 flex-1 flex-col gap-2">
                            <Link
                                :href="
                                    createPost({ query: { draft: draft.id } })
                                "
                                class="truncate text-sm font-medium hover:text-primary hover:underline"
                            >
                                {{ draft.title }}
                            </Link>
                            <span class="text-xs text-muted-foreground">
                                {{
                                    new Intl.DateTimeFormat(undefined, {
                                        month: 'short',
                                        day: 'numeric',
                                    }).format(new Date(draft.updatedAt))
                                }}
                            </span>
                        </div>
                        <PlatformBadge :platform="draft.platform" />
                    </li>
                </ul>
                <div
                    v-else
                    class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-border bg-muted/30 px-5 py-8 text-center"
                >
                    <span
                        class="flex size-11 items-center justify-center rounded-full bg-card text-primary shadow-sm"
                    >
                        <FileText class="size-5" aria-hidden="true" />
                    </span>
                    <div class="flex flex-col gap-1">
                        <p class="text-sm font-medium">No drafts yet</p>
                        <p class="text-sm text-muted-foreground">
                            Your saved ideas will be easy to find here.
                        </p>
                    </div>
                </div>
            </section>

            <section
                class="flex flex-col gap-4 rounded-2xl border border-border bg-card p-5 sm:p-6 lg:col-span-4"
                aria-labelledby="workspace-links-heading"
            >
                <div class="flex flex-col gap-1">
                    <h2
                        id="workspace-links-heading"
                        class="text-lg font-semibold tracking-tight"
                    >
                        Your workspace
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Organize ideas and review your activity.
                    </p>
                </div>
                <nav aria-label="Workspace tools">
                    <ul class="flex flex-col divide-y divide-border">
                        <li>
                            <Link
                                :href="scheduleRoute()"
                                class="group flex min-h-14 items-center justify-between gap-3 py-3 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <span class="text-sm font-medium"
                                    >Editorial schedule</span
                                >
                                <ArrowRight
                                    class="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                                    aria-hidden="true"
                                />
                            </Link>
                        </li>
                        <li>
                            <Link
                                :href="analyticsRoute()"
                                class="group flex min-h-14 items-center justify-between gap-3 py-3 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <span class="text-sm font-medium"
                                    >Workflow analytics</span
                                >
                                <ArrowRight
                                    class="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                                    aria-hidden="true"
                                />
                            </Link>
                        </li>
                    </ul>
                </nav>
                <p
                    class="rounded-lg bg-muted/60 p-3 text-xs leading-5 text-muted-foreground"
                >
                    Drafts and planning data stay in this browser and are not
                    synced to an account yet.
                </p>
            </section>
        </div>
    </div>
</template>
