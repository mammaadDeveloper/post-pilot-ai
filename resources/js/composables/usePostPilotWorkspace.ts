import { computed, ref } from 'vue';
import type { PostDraft, ScheduledPost } from '@/types/postpilot';

type WorkspaceState = {
    drafts: PostDraft[];
    scheduledPosts: ScheduledPost[];
};

const drafts = ref<PostDraft[]>([]);
const scheduledPosts = ref<ScheduledPost[]>([]);
const storageError = ref<string | null>(null);
const activeUserId = ref<number | null>(null);

function isDraft(value: unknown): value is PostDraft {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const draft = value as Record<string, unknown>;

    return (
        typeof draft.id === 'string' &&
        typeof draft.title === 'string' &&
        typeof draft.content === 'string' &&
        typeof draft.platform === 'string' &&
        typeof draft.format === 'string' &&
        typeof draft.tone === 'string' &&
        typeof draft.language === 'string' &&
        typeof draft.instructions === 'string' &&
        typeof draft.createdAt === 'string' &&
        typeof draft.updatedAt === 'string'
    );
}

function isScheduledPost(value: unknown): value is ScheduledPost {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const post = value as Record<string, unknown>;

    return (
        typeof post.id === 'string' &&
        typeof post.draftId === 'string' &&
        typeof post.title === 'string' &&
        typeof post.platform === 'string' &&
        typeof post.scheduledAt === 'string' &&
        typeof post.status === 'string' &&
        typeof post.createdAt === 'string'
    );
}

function keyFor(userId: number): string {
    return `postpilot.workspace.v1.${userId}`;
}

export function usePostPilotWorkspace(userId: number) {
    const draftCount = computed(() => drafts.value.length);
    const activePlatformCount = computed(
        () => new Set(drafts.value.map((draft) => draft.platform)).size,
    );

    function load(): boolean {
        if (typeof window === 'undefined') {
            return false;
        }

        try {
            const raw = window.localStorage.getItem(keyFor(userId));
            const state: unknown = raw ? JSON.parse(raw) : {};
            const savedDrafts =
                typeof state === 'object' && state !== null && 'drafts' in state
                    ? state.drafts
                    : [];
            const savedSchedule =
                typeof state === 'object' &&
                state !== null &&
                'scheduledPosts' in state
                    ? state.scheduledPosts
                    : [];

            if (
                !Array.isArray(savedDrafts) ||
                !savedDrafts.every(isDraft) ||
                !Array.isArray(savedSchedule) ||
                !savedSchedule.every(isScheduledPost)
            ) {
                throw new Error(
                    'Saved workspace data has an unsupported format. Your local drafts were not loaded.',
                );
            }

            drafts.value = savedDrafts;
            scheduledPosts.value = savedSchedule;
            activeUserId.value = userId;
            storageError.value = null;

            return true;
        } catch (error) {
            storageError.value =
                error instanceof Error
                    ? error.message
                    : 'Your local workspace could not be read.';

            return false;
        }
    }

    function persist(state: WorkspaceState): boolean {
        if (typeof window === 'undefined') {
            storageError.value = 'Local workspace storage is unavailable.';

            return false;
        }

        try {
            window.localStorage.setItem(keyFor(userId), JSON.stringify(state));
            drafts.value = state.drafts;
            scheduledPosts.value = state.scheduledPosts;
            activeUserId.value = userId;
            storageError.value = null;

            return true;
        } catch (error) {
            storageError.value =
                error instanceof Error
                    ? error.message
                    : 'Your workspace could not be saved in this browser.';

            return false;
        }
    }

    function saveDraft(
        input: Omit<PostDraft, 'id' | 'createdAt' | 'updatedAt'> & {
            id?: string;
        },
    ): PostDraft | null {
        if (activeUserId.value !== userId && !load()) {
            return null;
        }

        const existing = drafts.value.find((draft) => draft.id === input.id);
        const now = new Date().toISOString();
        const draft: PostDraft = {
            ...input,
            id: existing?.id ?? input.id ?? crypto.randomUUID(),
            createdAt: existing?.createdAt ?? now,
            updatedAt: now,
        };
        const nextDrafts = existing
            ? drafts.value.map((item) => (item.id === draft.id ? draft : item))
            : [draft, ...drafts.value];

        return persist({
            drafts: nextDrafts,
            scheduledPosts: scheduledPosts.value,
        })
            ? draft
            : null;
    }

    function removeDraft(id: string): boolean {
        if (activeUserId.value !== userId && !load()) {
            return false;
        }

        return persist({
            drafts: drafts.value.filter((draft) => draft.id !== id),
            scheduledPosts: scheduledPosts.value,
        });
    }

    function saveScheduledPost(
        input: Omit<ScheduledPost, 'id' | 'createdAt'>,
    ): ScheduledPost | null {
        if (activeUserId.value !== userId && !load()) {
            return null;
        }

        const post: ScheduledPost = {
            ...input,
            id: crypto.randomUUID(),
            createdAt: new Date().toISOString(),
        };

        return persist({
            drafts: drafts.value,
            scheduledPosts: [...scheduledPosts.value, post],
        })
            ? post
            : null;
    }

    function removeScheduledPost(id: string): boolean {
        if (activeUserId.value !== userId && !load()) {
            return false;
        }

        return persist({
            drafts: drafts.value,
            scheduledPosts: scheduledPosts.value.filter(
                (post) => post.id !== id,
            ),
        });
    }

    return {
        drafts,
        scheduledPosts,
        draftCount,
        activePlatformCount,
        storageError,
        load,
        saveDraft,
        removeDraft,
        saveScheduledPost,
        removeScheduledPost,
    };
}
