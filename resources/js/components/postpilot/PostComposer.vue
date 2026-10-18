<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    Check,
    Clipboard,
    Clock3,
    RotateCw,
    Save,
    Sparkles,
} from '@lucide/vue';
import { drafts as draftsRoute } from '@/routes/posts';
import { usePostPilotWorkspace } from '@/composables/usePostPilotWorkspace';
import { platforms } from '@/types/postpilot';
import type {
    Platform,
    PostDraft,
    PostFormat,
    PostLanguage,
    PostTone,
} from '@/types/postpilot';
import PlatformBadge from '@/components/postpilot/PlatformBadge.vue';

const props = withDefaults(
    defineProps<{
        compact?: boolean;
    }>(),
    {
        compact: false,
    },
);

const emit = defineEmits<{
    saved: [draft: PostDraft];
}>();

const user = usePage().props.auth.user;
const workspace = usePostPilotWorkspace(user.id);
const formats: PostFormat[] = [
    'Standard Post',
    'Educational Post',
    'Promotional Post',
    'Thought Leadership',
];
const tones: PostTone[] = [
    'Professional',
    'Friendly',
    'Educational',
    'Casual',
    'Persuasive',
];
const title = ref('');
const content = ref('');
const platform = ref<Platform>('LinkedIn');
const format = ref<PostFormat>('Standard Post');
const tone = ref<PostTone>('Professional');
const language = ref<PostLanguage>('English');
const instructions = ref('');
const editingId = ref<string | undefined>();
const notice = ref('');
const error = ref('');
const contentInput = ref<HTMLTextAreaElement | null>(null);

const characterLimit = computed(() =>
    platform.value === 'X' ? 280 : platform.value === 'Instagram' ? 2200 : 3000,
);
const characterCount = computed(() => [...content.value].length);
const hashtags = computed(
    () => content.value.match(/#[\p{L}\p{N}_]+/gu)?.slice(0, 5) ?? [],
);
const paragraphs = computed(() => content.value.split(/\n+/).filter(Boolean));

function loadDraft(draft: PostDraft): void {
    title.value = draft.title;
    content.value = draft.content;
    platform.value = draft.platform;
    format.value = draft.format;
    tone.value = draft.tone;
    language.value = draft.language;
    instructions.value = draft.instructions;
    editingId.value = draft.id;
    notice.value = `Editing “${draft.title}”.`;
}

onMounted(() => {
    workspace.load();
    const draftId = new URL(window.location.href).searchParams.get('draft');

    if (draftId) {
        const draft = workspace.drafts.value.find(
            (item) => item.id === draftId,
        );

        if (draft) {
            loadDraft(draft);
        } else {
            error.value = 'That draft is not available in this browser.';
        }
    }
});

function saveDraft(): void {
    error.value = '';
    notice.value = '';

    if (!content.value.trim()) {
        error.value = 'Add some post copy before saving your draft.';
        return;
    }

    const firstLine =
        title.value.trim() ||
        content.value.trim().split('\n')[0] ||
        'Untitled draft';
    const saved = workspace.saveDraft({
        id: editingId.value,
        title:
            firstLine.length > 72
                ? `${firstLine.slice(0, 69).trimEnd()}…`
                : firstLine,
        content: content.value.trim(),
        platform: platform.value,
        format: format.value,
        tone: tone.value,
        language: language.value,
        instructions: instructions.value.trim(),
    });

    if (!saved) {
        error.value =
            workspace.storageError.value ??
            'The draft could not be saved in this browser.';
        return;
    }

    editingId.value = saved.id;
    notice.value = 'Draft saved on this device.';
    emit('saved', saved);
}

async function copyContent(): Promise<void> {
    if (!content.value.trim()) {
        error.value = 'Write some post copy before copying it.';
        return;
    }

    try {
        await navigator.clipboard.writeText(content.value);
        notice.value = 'Post copy copied to the clipboard.';
        error.value = '';
    } catch {
        error.value =
            'Clipboard access was denied. Select and copy the text from the editor instead.';
    }
}

async function focusEditor(): Promise<void> {
    await nextTick();
    contentInput.value?.focus();
}
</script>

<template>
    <section
        class="grid items-start gap-6"
        :class="
            compact
                ? 'xl:grid-cols-[minmax(0,1.05fr)_minmax(300px,0.95fr)]'
                : 'xl:grid-cols-[minmax(0,1.1fr)_minmax(320px,0.9fr)]'
        "
        aria-label="Post composer"
    >
        <form
            class="flex flex-col gap-5 rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-6"
            @submit.prevent="saveDraft"
        >
            <div class="flex items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h2 class="text-lg font-semibold tracking-tight">
                        {{ compact ? 'Quick compose' : 'Post studio' }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Shape a post and see the preview update as you write.
                    </p>
                </div>
                <Sparkles
                    class="mt-1 size-5 shrink-0 text-secondary"
                    aria-hidden="true"
                />
            </div>

            <div class="grid gap-2">
                <label for="post-title" class="text-sm font-medium"
                    >Post headline
                    <span class="text-muted-foreground">(optional)</span></label
                >
                <input
                    id="post-title"
                    v-model="title"
                    class="min-h-11 rounded-lg border border-input bg-background px-3 text-sm outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring"
                    maxlength="120"
                    placeholder="A clear idea to start with"
                />
            </div>

            <fieldset class="grid gap-2">
                <legend class="text-sm font-medium">Platform</legend>
                <div class="grid grid-cols-3 gap-2">
                    <button
                        v-for="item in platforms"
                        :key="item"
                        type="button"
                        class="flex min-h-11 items-center justify-center rounded-lg border px-2 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        :class="
                            platform === item
                                ? 'border-primary bg-primary/5 text-primary'
                                : 'border-border bg-background text-muted-foreground hover:bg-muted'
                        "
                        :aria-pressed="platform === item"
                        @click="platform = item"
                    >
                        {{ item }}
                    </button>
                </div>
            </fieldset>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <label for="post-format" class="text-sm font-medium"
                        >Format</label
                    >
                    <select
                        id="post-format"
                        v-model="format"
                        class="min-h-11 rounded-lg border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <option v-for="item in formats" :key="item">
                            {{ item }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <label for="post-tone" class="text-sm font-medium"
                        >Voice &amp; tone</label
                    >
                    <select
                        id="post-tone"
                        v-model="tone"
                        class="min-h-11 rounded-lg border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <option v-for="item in tones" :key="item">
                            {{ item }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <label for="post-language" class="text-sm font-medium"
                        >Language</label
                    >
                    <select
                        id="post-language"
                        v-model="language"
                        class="min-h-11 rounded-lg border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <option>English</option>
                        <option>Persian</option>
                    </select>
                </div>
                <p class="self-end pb-3 text-xs text-muted-foreground">
                    Preferences are saved with each local draft.
                </p>
            </div>

            <div class="grid gap-2">
                <label for="post-content" class="text-sm font-medium"
                    >Post copy</label
                >
                <textarea
                    id="post-content"
                    ref="contentInput"
                    v-model="content"
                    class="min-h-44 resize-y rounded-lg border border-input bg-background px-3 py-3 text-sm leading-6 outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring"
                    :maxlength="characterLimit"
                    :dir="language === 'Persian' ? 'rtl' : 'auto'"
                    placeholder="Share an idea, a lesson, or a milestone…"
                ></textarea>
                <div
                    class="flex items-center justify-between gap-3 text-xs text-muted-foreground"
                >
                    <span
                        >{{ characterCount }} /
                        {{ characterLimit }} characters</span
                    >
                    <span>{{ format }} · {{ tone }}</span>
                </div>
            </div>

            <div class="grid gap-2">
                <label for="post-instructions" class="text-sm font-medium">
                    Context &amp; instructions
                    <span class="text-muted-foreground">(optional)</span>
                </label>
                <textarea
                    id="post-instructions"
                    v-model="instructions"
                    class="min-h-20 resize-y rounded-lg border border-input bg-background px-3 py-2.5 text-sm outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring"
                    maxlength="500"
                    placeholder="Focus on a specific detail or point of view"
                ></textarea>
            </div>

            <p
                v-if="workspace.storageError.value"
                class="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive"
                role="alert"
            >
                {{ workspace.storageError.value }}
            </p>
            <p
                v-if="error"
                class="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive"
                role="alert"
            >
                {{ error }}
            </p>
            <p
                v-if="notice"
                class="text-sm text-emerald-800 dark:text-emerald-300"
                role="status"
            >
                {{ notice }}
            </p>

            <div
                class="flex flex-wrap items-center gap-2 border-t border-border pt-4"
            >
                <button
                    type="submit"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                >
                    <Save class="size-4" aria-hidden="true" />
                    Save draft
                </button>
                <button
                    type="button"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-border px-3.5 py-2 text-sm font-medium transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    @click="copyContent"
                >
                    <Clipboard class="size-4" aria-hidden="true" />
                    Copy
                </button>
                <button
                    type="button"
                    disabled
                    title="AI generation is not connected yet"
                    class="inline-flex min-h-11 cursor-not-allowed items-center justify-center gap-2 rounded-lg border border-border px-3.5 py-2 text-sm font-medium text-muted-foreground opacity-60"
                >
                    <RotateCw class="size-4" aria-hidden="true" />
                    Regenerate
                </button>
                <Link
                    :href="draftsRoute()"
                    class="ml-auto inline-flex min-h-11 items-center gap-1.5 px-2 text-sm font-medium text-primary hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    Drafts
                    <ArrowUpRight class="size-4" aria-hidden="true" />
                </Link>
            </div>
            <p class="text-xs text-muted-foreground">
                Drafts are stored in this browser only. No AI generation or
                social publishing service is connected.
            </p>
        </form>

        <aside
            class="flex flex-col gap-4 rounded-2xl border border-border bg-muted/40 p-4 sm:p-5 xl:sticky xl:top-20"
            aria-label="Live post preview"
        >
            <div class="flex items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h2 class="text-base font-semibold">Live preview</h2>
                    <p class="text-xs text-muted-foreground">
                        A copy-only preview of your post.
                    </p>
                </div>
                <PlatformBadge :platform="platform" />
            </div>

            <article
                class="rounded-xl border border-border bg-card p-5 shadow-sm"
                :dir="language === 'Persian' ? 'rtl' : 'auto'"
            >
                <header class="flex items-center gap-3">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground"
                        aria-hidden="true"
                    >
                        {{
                            user.name
                                .split(/\s+/)
                                .map((part) => part[0])
                                .slice(0, 2)
                                .join('')
                                .toUpperCase()
                        }}
                    </div>
                    <div class="flex min-w-0 flex-col">
                        <p class="truncate text-sm font-semibold">
                            {{ user.name }}
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ user.email }}
                        </p>
                    </div>
                    <Clock3
                        class="ml-auto size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                </header>
                <h3 v-if="title" class="mt-5 text-base font-semibold">
                    {{ title }}
                </h3>
                <div class="mt-4 flex flex-col gap-3 text-sm leading-6">
                    <p v-for="(paragraph, index) in paragraphs" :key="index">
                        {{ paragraph }}
                    </p>
                    <p v-if="!paragraphs.length" class="text-muted-foreground">
                        Your post copy will appear here as you write.
                    </p>
                </div>
                <div v-if="hashtags.length" class="mt-4 flex flex-wrap gap-2">
                    <span
                        v-for="tag in hashtags"
                        :key="tag"
                        class="rounded-full bg-secondary/10 px-2.5 py-1 text-xs font-medium text-secondary"
                        >{{ tag }}</span
                    >
                </div>
                <footer
                    class="mt-5 flex items-center justify-between border-t border-border pt-3 text-xs text-muted-foreground"
                >
                    <span>{{ format }} · {{ tone }}</span>
                    <span>{{ characterCount }} characters</span>
                </footer>
            </article>

            <div
                v-if="instructions"
                class="rounded-lg border border-dashed border-border bg-card p-3 text-xs leading-5 text-muted-foreground"
            >
                <span class="font-medium text-foreground"
                    >Writing context:</span
                >
                {{ instructions }}
            </div>

            <button
                type="button"
                class="inline-flex min-h-10 items-center justify-center gap-2 self-start rounded-lg border border-border bg-card px-3 text-sm font-medium hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                @click="focusEditor"
            >
                <Check class="size-4" aria-hidden="true" />
                Edit post copy
            </button>
        </aside>
    </section>
</template>
