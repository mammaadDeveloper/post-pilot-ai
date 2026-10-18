<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    Bookmark,
    Check,
    Clipboard,
    Clock3,
    Globe2,
    Heart,
    ImagePlus,
    MessageCircle,
    Repeat2,
    RotateCw,
    Save,
    Send,
    Share,
    Sparkles,
    Trash2,
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
const mediaDataUrl = ref<string | undefined>();
const mediaFileName = ref<string | undefined>();
const editingId = ref<string | undefined>();
const notice = ref('');
const error = ref('');
const contentInput = ref<HTMLTextAreaElement | null>(null);
const imageInput = ref<HTMLInputElement | null>(null);
const captionExpanded = ref(false);

const characterLimit = computed(() =>
    platform.value === 'X' ? 280 : platform.value === 'Instagram' ? 2200 : 3000,
);
const characterCount = computed(() => [...content.value].length);
const previewContent = computed(() => content.value.trim());
const previewCharacterLimit = computed(() =>
    platform.value === 'Instagram' ? 140 : 420,
);
const displayedPreviewContent = computed(() => {
    if (
        captionExpanded.value ||
        previewContent.value.length <= previewCharacterLimit.value
    ) {
        return previewContent.value;
    }

    return `${previewContent.value.slice(0, previewCharacterLimit.value).trimEnd()}…`;
});
const avatarInitials = computed(() =>
    user.name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase(),
);

function loadDraft(draft: PostDraft): void {
    title.value = draft.title;
    content.value = draft.content;
    platform.value = draft.platform;
    format.value = draft.format;
    tone.value = draft.tone;
    language.value = draft.language;
    instructions.value = draft.instructions;
    mediaDataUrl.value = draft.mediaDataUrl;
    mediaFileName.value = draft.mediaFileName;
    editingId.value = draft.id;
    notice.value = `Editing “${draft.title}”.`;
}

async function handleMediaChange(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    error.value = '';

    if (!file) {
        return;
    }

    if (
        !['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(
            file.type,
        )
    ) {
        error.value = 'Choose a JPEG, PNG, WebP, or GIF image.';
        input.value = '';
        return;
    }

    if (file.size > 1_048_576) {
        error.value =
            'Images must be 1 MB or smaller to save with a local draft.';
        input.value = '';
        return;
    }

    try {
        const dataUrl = await new Promise<string>((resolve, reject) => {
            const reader = new FileReader();
            reader.addEventListener('load', () => {
                if (typeof reader.result === 'string') {
                    resolve(reader.result);
                } else {
                    reject(new Error('The selected image could not be read.'));
                }
            });
            reader.addEventListener('error', () =>
                reject(new Error('The selected image could not be read.')),
            );
            reader.readAsDataURL(file);
        });

        mediaDataUrl.value = dataUrl;
        mediaFileName.value = file.name;
        notice.value = 'Image attached to this browser draft.';
    } catch {
        error.value = 'The selected image could not be read.';
    } finally {
        input.value = '';
    }
}

function removeMedia(): void {
    mediaDataUrl.value = undefined;
    mediaFileName.value = undefined;

    if (imageInput.value) {
        imageInput.value.value = '';
    }
}

function selectPlatform(value: Platform): void {
    platform.value = value;
    captionExpanded.value = false;
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
        mediaDataUrl: mediaDataUrl.value,
        mediaFileName: mediaFileName.value,
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
                    >Draft title
                    <span class="text-muted-foreground"
                        >(optional · not published)</span
                    ></label
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
                        @click="selectPlatform(item)"
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

            <div class="grid gap-2">
                <span class="text-sm font-medium">Post image</span>
                <div class="flex flex-wrap items-center gap-3">
                    <label
                        for="post-image"
                        class="inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-lg border border-border px-3 text-sm font-medium transition-colors focus-within:ring-2 focus-within:ring-ring focus-within:outline-none hover:bg-muted"
                    >
                        <ImagePlus class="size-4" aria-hidden="true" />
                        {{ mediaDataUrl ? 'Replace image' : 'Attach image' }}
                        <input
                            id="post-image"
                            ref="imageInput"
                            type="file"
                            accept="image/jpeg,image/png,image/webp,image/gif"
                            class="sr-only"
                            @change="handleMediaChange"
                        />
                    </label>
                    <span
                        v-if="mediaFileName"
                        class="max-w-52 truncate text-xs text-muted-foreground"
                    >
                        {{ mediaFileName }}
                    </span>
                    <button
                        v-if="mediaDataUrl"
                        type="button"
                        class="inline-flex min-h-10 items-center gap-1.5 rounded-lg px-2 text-sm text-muted-foreground hover:bg-destructive/5 hover:text-destructive focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        @click="removeMedia"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                        Remove
                    </button>
                </div>
                <p class="text-xs leading-5 text-muted-foreground">
                    JPEG, PNG, WebP, or GIF up to 1 MB. Images are saved with
                    drafts in this browser only.
                </p>
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
                        Approximate {{ platform }} feed layout · updates as you
                        edit.
                    </p>
                </div>
                <PlatformBadge :platform="platform" />
            </div>

            <article
                v-if="platform === 'LinkedIn'"
                class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
                :dir="language === 'Persian' ? 'rtl' : 'auto'"
            >
                <header class="flex items-center gap-3 p-4">
                    <div
                        class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary text-sm font-semibold text-primary-foreground"
                        aria-hidden="true"
                    >
                        <img
                            v-if="user.avatar"
                            :src="user.avatar"
                            alt=""
                            class="size-full object-cover"
                        />
                        <span v-else>{{ avatarInitials }}</span>
                    </div>
                    <div class="flex min-w-0 flex-1 flex-col">
                        <p class="truncate text-sm font-semibold">
                            {{ user.name }}
                        </p>
                        <p
                            class="flex items-center gap-1 text-xs text-muted-foreground"
                        >
                            Post preview · Public
                            <span aria-hidden="true">·</span>
                            <Globe2 class="size-3" aria-hidden="true" />
                        </p>
                    </div>
                    <Clock3
                        class="size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                </header>
                <div
                    class="px-4 pb-3 text-sm leading-6 break-words whitespace-pre-wrap"
                >
                    <p v-if="previewContent">
                        {{ displayedPreviewContent }}
                        <button
                            v-if="previewContent.length > previewCharacterLimit"
                            type="button"
                            class="font-medium text-muted-foreground hover:text-primary focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            :aria-expanded="captionExpanded"
                            @click="captionExpanded = !captionExpanded"
                        >
                            {{ captionExpanded ? 'see less' : '…see more' }}
                        </button>
                    </p>
                    <p v-else class="text-muted-foreground italic">
                        Your post copy will appear here as you write.
                    </p>
                </div>
                <img
                    v-if="mediaDataUrl"
                    :src="mediaDataUrl"
                    :alt="
                        mediaFileName
                            ? `Attached image: ${mediaFileName}`
                            : 'Attached post image'
                    "
                    class="aspect-[1.91/1] w-full bg-muted object-cover"
                />
                <footer
                    class="grid grid-cols-4 border-t border-border px-2 py-2 text-xs font-medium text-muted-foreground sm:px-4"
                    aria-label="Post actions preview"
                >
                    <span class="flex items-center justify-center gap-1.5 py-2"
                        ><Heart class="size-4" aria-hidden="true" />Like</span
                    >
                    <span class="flex items-center justify-center gap-1.5 py-2"
                        ><MessageCircle
                            class="size-4"
                            aria-hidden="true"
                        />Comment</span
                    >
                    <span class="flex items-center justify-center gap-1.5 py-2"
                        ><Repeat2
                            class="size-4"
                            aria-hidden="true"
                        />Repost</span
                    >
                    <span class="flex items-center justify-center gap-1.5 py-2"
                        ><Send class="size-4" aria-hidden="true" />Send</span
                    >
                </footer>
            </article>

            <article
                v-else-if="platform === 'Instagram'"
                class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
                :dir="language === 'Persian' ? 'rtl' : 'auto'"
            >
                <header class="flex items-center gap-3 p-3">
                    <div
                        class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary text-xs font-semibold text-primary-foreground ring-2 ring-[#d36a78] ring-offset-2 ring-offset-card"
                        aria-hidden="true"
                    >
                        <img
                            v-if="user.avatar"
                            :src="user.avatar"
                            alt=""
                            class="size-full object-cover"
                        />
                        <span v-else>{{ avatarInitials }}</span>
                    </div>
                    <p class="min-w-0 flex-1 truncate text-sm font-semibold">
                        {{ user.name }}
                    </p>
                    <span class="text-lg leading-none" aria-hidden="true"
                        >•••</span
                    >
                </header>
                <div
                    v-if="mediaDataUrl"
                    class="aspect-[4/5] w-full overflow-hidden bg-muted"
                >
                    <img
                        :src="mediaDataUrl"
                        :alt="
                            mediaFileName
                                ? `Attached image: ${mediaFileName}`
                                : 'Attached post image'
                        "
                        class="size-full object-cover"
                    />
                </div>
                <div
                    v-else
                    class="flex aspect-[4/5] w-full flex-col items-center justify-center gap-2 bg-muted/60 px-6 text-center text-muted-foreground"
                >
                    <ImagePlus class="size-7" aria-hidden="true" />
                    <p class="text-sm font-medium">Photo post preview</p>
                    <p class="max-w-52 text-xs leading-5">
                        Attach an image to see the full Instagram feed layout.
                    </p>
                </div>
                <div class="flex items-center gap-4 px-3 py-3">
                    <Heart class="size-6" aria-hidden="true" />
                    <MessageCircle class="size-6" aria-hidden="true" />
                    <Send class="size-6" aria-hidden="true" />
                    <Bookmark class="ml-auto size-6" aria-hidden="true" />
                </div>
                <div class="px-3 pb-4 text-sm leading-6">
                    <p v-if="previewContent" class="break-words">
                        <span class="inline-block font-semibold mr-1.5">{{ user.name }}</span>
                        <span class="whitespace-pre-wrap">
                            {{ displayedPreviewContent }}
                        </span>
                        <button
                            v-if="previewContent.length > previewCharacterLimit"
                            type="button"
                            class="ms-1 text-muted-foreground hover:text-foreground focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            :aria-expanded="captionExpanded"
                            @click="captionExpanded = !captionExpanded"
                        >
                            {{ captionExpanded ? 'less' : 'more' }}
                        </button>
                    </p>
                    <p v-else class="text-muted-foreground italic">
                        Your caption will appear here as you write.
                    </p>
                </div>
            </article>

            <article
                v-else
                class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm"
                :dir="language === 'Persian' ? 'rtl' : 'auto'"
            >
                <header class="flex items-start gap-3 p-4">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary text-sm font-semibold text-primary-foreground"
                        aria-hidden="true"
                    >
                        <img
                            v-if="user.avatar"
                            :src="user.avatar"
                            alt=""
                            class="size-full object-cover"
                        />
                        <span v-else>{{ avatarInitials }}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-bold">
                            {{ user.name }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Post preview · Public
                        </p>
                    </div>
                    <span class="text-lg leading-none" aria-hidden="true"
                        >•••</span
                    >
                </header>
                <div
                    class="px-4 pb-3 text-sm leading-6 break-words whitespace-pre-wrap"
                >
                    <p v-if="previewContent">
                        {{ displayedPreviewContent }}
                        <button
                            v-if="previewContent.length > previewCharacterLimit"
                            type="button"
                            class="font-medium text-muted-foreground hover:text-primary focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            :aria-expanded="captionExpanded"
                            @click="captionExpanded = !captionExpanded"
                        >
                            {{ captionExpanded ? 'Show less' : 'Show more' }}
                        </button>
                    </p>
                    <p v-else class="text-muted-foreground italic">
                        Your post text will appear here as you write.
                    </p>
                </div>
                <img
                    v-if="mediaDataUrl"
                    :src="mediaDataUrl"
                    :alt="
                        mediaFileName
                            ? `Attached image: ${mediaFileName}`
                            : 'Attached post image'
                    "
                    class="aspect-video w-full bg-muted object-cover"
                />
                <footer
                    class="flex items-center justify-around border-t border-border px-2 py-3 text-xs text-muted-foreground"
                    aria-label="Post actions preview"
                >
                    <span class="inline-flex items-center gap-1.5"
                        ><MessageCircle
                            class="size-4"
                            aria-hidden="true"
                        />Reply</span
                    >
                    <span class="inline-flex items-center gap-1.5"
                        ><Repeat2
                            class="size-4"
                            aria-hidden="true"
                        />Repost</span
                    >
                    <span class="inline-flex items-center gap-1.5"
                        ><Heart class="size-4" aria-hidden="true" />Like</span
                    >
                    <span class="inline-flex items-center gap-1.5"
                        ><Share class="size-4" aria-hidden="true" />Share</span
                    >
                </footer>
            </article>

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
