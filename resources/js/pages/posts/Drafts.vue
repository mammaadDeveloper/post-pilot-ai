<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    Copy,
    FileText,
    Plus,
    Search,
    Trash2,
} from '@lucide/vue';
import { create, drafts as draftsRoute } from '@/routes/posts';
import { usePostPilotWorkspace } from '@/composables/usePostPilotWorkspace';
import { platforms } from '@/types/postpilot';
import type { Platform, PostDraft } from '@/types/postpilot';
import PlatformBadge from '@/components/postpilot/PlatformBadge.vue';
import PageHeader from '@/components/postpilot/PageHeader.vue';
import { usePage } from '@inertiajs/vue3';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Saved drafts',
                href: draftsRoute(),
            },
        ],
    },
});

const user = usePage().props.auth.user;
const workspace = usePostPilotWorkspace(user.id);
const query = ref('');
const selectedPlatform = ref<'All Platforms' | Platform>('All Platforms');
const sortBy = ref('updated');
const confirmation = ref('');

const filteredDrafts = computed(() => {
    const normalizedQuery = query.value.trim().toLocaleLowerCase();
    const result = workspace.drafts.value.filter((draft) => {
        const matchesPlatform =
            selectedPlatform.value === 'All Platforms' ||
            draft.platform === selectedPlatform.value;
        const matchesQuery =
            !normalizedQuery ||
            `${draft.title} ${draft.content}`
                .toLocaleLowerCase()
                .includes(normalizedQuery);

        return matchesPlatform && matchesQuery;
    });

    return result.sort((first, second) => {
        if (sortBy.value === 'alphabetical') {
            return first.title.localeCompare(second.title);
        }

        if (sortBy.value === 'length') {
            return second.content.length - first.content.length;
        }

        return (
            new Date(second.updatedAt).getTime() -
            new Date(first.updatedAt).getTime()
        );
    });
});

const updatedThisWeek = computed(() => {
    const weekAgo = Date.now() - 7 * 24 * 60 * 60 * 1000;

    return workspace.drafts.value.filter(
        (draft) => new Date(draft.updatedAt).getTime() >= weekAgo,
    ).length;
});

onMounted(() => workspace.load());

function relativeDate(value: string): string {
    const elapsedDays = Math.round(
        (new Date(value).getTime() - Date.now()) / (1000 * 60 * 60 * 24),
    );
    const formatter = new Intl.RelativeTimeFormat(undefined, {
        numeric: 'auto',
    });

    if (Math.abs(elapsedDays) < 1) {
        const elapsedHours = Math.round(
            (new Date(value).getTime() - Date.now()) / (1000 * 60 * 60),
        );

        if (Math.abs(elapsedHours) < 1) {
            const elapsedMinutes = Math.round(
                (new Date(value).getTime() - Date.now()) / (1000 * 60),
            );

            return formatter.format(elapsedMinutes, 'minute');
        }

        return formatter.format(elapsedHours, 'hour');
    }

    return formatter.format(elapsedDays, 'day');
}

function duplicateDraft(draft: PostDraft): void {
    const duplicate = workspace.saveDraft({
        title: `Copy of ${draft.title}`,
        content: draft.content,
        platform: draft.platform,
        format: draft.format,
        tone: draft.tone,
        language: draft.language,
        instructions: draft.instructions,
        mediaDataUrl: draft.mediaDataUrl,
        mediaFileName: draft.mediaFileName,
    });

    confirmation.value = duplicate
        ? 'Draft duplicated on this device.'
        : (workspace.storageError.value ??
          'The draft could not be duplicated.');
}

function deleteDraft(draft: PostDraft): void {
    if (
        window.confirm(
            `Delete “${draft.title}” from this browser? This cannot be undone.`,
        )
    ) {
        const removed = workspace.removeDraft(draft.id);
        confirmation.value = removed
            ? 'Draft deleted from this device.'
            : (workspace.storageError.value ??
              'The draft could not be deleted.');
    }
}
</script>

<template>
    <Head title="Saved drafts" />
    <div
        class="mx-auto flex w-full max-w-[1440px] flex-col gap-7 p-4 sm:p-6 lg:p-8"
    >
        <PageHeader
            title="Saved drafts"
            description="A local library of your post ideas, ready to pick up whenever you are."
            eyebrow="CONTENT LIBRARY"
        >
            <template #actions>
                <Link
                    :href="create()"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    Create post
                </Link>
            </template>
        </PageHeader>

        <p
            v-if="workspace.storageError.value"
            class="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive"
            role="alert"
        >
            {{ workspace.storageError.value }}
        </p>
        <p
            v-if="confirmation"
            class="text-sm text-muted-foreground"
            role="status"
        >
            {{ confirmation }}
        </p>

        <section
            class="grid gap-4 sm:grid-cols-3"
            aria-label="Draft library summary"
        >
            <article class="rounded-2xl border border-border bg-card p-5">
                <p class="text-sm text-muted-foreground">Total drafts</p>
                <p class="mt-2 text-3xl font-semibold tabular-nums">
                    {{ workspace.draftCount.value }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Saved on this device
                </p>
            </article>
            <article class="rounded-2xl border border-border bg-card p-5">
                <p class="text-sm text-muted-foreground">Updated this week</p>
                <p class="mt-2 text-3xl font-semibold tabular-nums">
                    {{ updatedThisWeek }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Based on local draft activity
                </p>
            </article>
            <article class="rounded-2xl border border-border bg-card p-5">
                <p class="text-sm text-muted-foreground">Platforms used</p>
                <p class="mt-2 text-3xl font-semibold tabular-nums">
                    {{ workspace.activePlatformCount.value }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Across your saved copy
                </p>
            </article>
        </section>

        <section class="flex flex-col gap-5" aria-label="Drafts">
            <div
                class="grid gap-3 rounded-2xl border border-border bg-card p-4 md:grid-cols-[minmax(200px,1fr)_auto_auto]"
            >
                <label class="relative block">
                    <span class="sr-only">Search drafts</span>
                    <Search
                        class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <input
                        v-model="query"
                        type="search"
                        class="min-h-11 w-full rounded-lg border border-input bg-background pr-3 pl-9 text-sm outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring"
                        placeholder="Search your drafts"
                    />
                </label>
                <label class="flex items-center gap-2">
                    <span class="sr-only">Filter by platform</span>
                    <select
                        v-model="selectedPlatform"
                        class="min-h-11 rounded-lg border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <option>All Platforms</option>
                        <option v-for="platform in platforms" :key="platform">
                            {{ platform }}
                        </option>
                    </select>
                </label>
                <label class="flex items-center gap-2">
                    <span class="sr-only">Sort drafts</span>
                    <select
                        v-model="sortBy"
                        class="min-h-11 rounded-lg border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <option value="updated">Last updated</option>
                        <option value="length">Character count</option>
                        <option value="alphabetical">Alphabetical</option>
                    </select>
                </label>
            </div>

            <div
                v-if="!filteredDrafts.length"
                class="flex flex-col items-center gap-4 rounded-2xl border border-dashed border-border bg-card px-6 py-14 text-center"
            >
                <span
                    class="flex size-12 items-center justify-center rounded-full bg-muted text-primary"
                >
                    <FileText class="size-5" aria-hidden="true" />
                </span>
                <div class="flex flex-col gap-1">
                    <h2 class="font-semibold">
                        {{
                            workspace.draftCount.value
                                ? 'No drafts match your search'
                                : 'Your draft library is empty'
                        }}
                    </h2>
                    <p class="max-w-md text-sm leading-6 text-muted-foreground">
                        {{
                            workspace.draftCount.value
                                ? 'Try a different search or platform filter.'
                                : 'Save a post from the studio and it will be kept on this device.'
                        }}
                    </p>
                </div>
                <Link
                    v-if="!workspace.draftCount.value"
                    :href="create()"
                    class="inline-flex min-h-10 items-center gap-2 rounded-lg bg-primary px-3.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    Start a draft
                </Link>
            </div>

            <ul v-else class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
                <li
                    v-for="draft in filteredDrafts"
                    :key="draft.id"
                    class="flex min-w-0 flex-col gap-4 rounded-2xl border border-border bg-card p-5 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <PlatformBadge :platform="draft.platform" />
                        <span class="text-xs text-muted-foreground">
                            {{ relativeDate(draft.updatedAt) }}
                        </span>
                    </div>
                    <div class="flex min-w-0 flex-col gap-2">
                        <h2
                            class="line-clamp-2 text-base leading-6 font-semibold"
                        >
                            {{ draft.title }}
                        </h2>
                        <p
                            class="line-clamp-4 min-h-20 text-sm leading-5 whitespace-pre-line text-muted-foreground"
                        >
                            {{ draft.content }}
                        </p>
                    </div>
                    <div
                        class="flex flex-wrap items-center justify-between gap-2 border-t border-border pt-3"
                    >
                        <span class="text-xs text-muted-foreground">
                            {{ [...draft.content].length }} characters
                        </span>
                        <div class="flex flex-wrap gap-1">
                            <Link
                                :href="create({ query: { draft: draft.id } })"
                                class="inline-flex min-h-9 items-center gap-1.5 rounded-lg px-2.5 text-xs font-medium text-primary hover:bg-primary/5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                Open in studio
                                <ArrowUpRight
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                            </Link>
                            <button
                                type="button"
                                class="inline-flex size-9 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                :aria-label="`Duplicate ${draft.title}`"
                                @click="duplicateDraft(draft)"
                            >
                                <Copy class="size-4" aria-hidden="true" />
                            </button>
                            <button
                                type="button"
                                class="inline-flex size-9 items-center justify-center rounded-lg text-muted-foreground hover:bg-destructive/10 hover:text-destructive focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                :aria-label="`Delete ${draft.title}`"
                                @click="deleteDraft(draft)"
                            >
                                <Trash2 class="size-4" aria-hidden="true" />
                            </button>
                        </div>
                    </div>
                </li>
            </ul>
        </section>

        <p class="text-xs text-muted-foreground">
            This library is stored in your browser. It is not synced to your
            PostPilot account.
        </p>
    </div>
</template>
