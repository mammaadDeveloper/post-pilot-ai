<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue';
import { Link, useHttp, usePage } from '@inertiajs/vue3';
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
import { generate as generatePostContent } from '@/actions/App/Http/Controllers/PostContentController';
import { drafts as draftsRoute } from '@/routes/posts';
import { usePostPilotWorkspace } from '@/composables/usePostPilotWorkspace';
import { platforms, postContentOperations } from '@/types/postpilot';
import type {
    PostContentOperation,
    PostContentResponse,
    PostContentVariant,
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
const isReadingMedia = ref(false);
let mediaReadToken = 0;

const operationLabels: Record<PostContentOperation, string> = {
    generate: 'Generate post',
    rewrite: 'Rewrite',
    proofread: 'Proofread',
    improve: 'Improve',
    professional: 'Make more professional',
    engaging: 'Make more engaging',
    change_tone: 'Change tone',
    shorten: 'Shorten',
    expand: 'Expand',
    improve_hook: 'Improve the opening',
    adapt: 'Adapt for platform',
    caption_hashtags: 'Generate caption & hashtags',
    ideas: 'Generate content ideas',
    custom: 'Custom instructions',
};
const selectedOperation = ref<PostContentOperation>('generate');
const requestedVariants = ref(1);
const aiVariants = ref<PostContentVariant[]>([]);
const aiError = ref('');
const aiNotice = ref('');
const aiRequest = useHttp<
    {
        operation: PostContentOperation;
        topic: string | null;
        content: string;
        platform: Platform;
        format: PostFormat;
        tone: PostTone;
        language: PostLanguage;
        instructions: string;
        variants: number;
    },
    PostContentResponse
>({
    operation: 'generate',
    topic: null,
    content: '',
    platform: 'LinkedIn',
    format: 'Standard Post',
    tone: 'Professional',
    language: 'English',
    instructions: '',
    variants: 1,
});

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

    const readToken = ++mediaReadToken;
    isReadingMedia.value = false;

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

    isReadingMedia.value = true;

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

        if (readToken !== mediaReadToken) {
            return;
        }

        await new Promise<void>((resolve, reject) => {
            const image = new Image();
            image.addEventListener('load', () => resolve(), { once: true });
            image.addEventListener(
                'error',
                () =>
                    reject(
                        new Error('The selected file is not a valid image.'),
                    ),
                { once: true },
            );
            image.src = dataUrl;
        });

        if (readToken !== mediaReadToken) {
            return;
        }

        mediaDataUrl.value = dataUrl;
        mediaFileName.value = file.name;
        notice.value = 'Image attached to this browser draft.';
    } catch {
        if (readToken === mediaReadToken) {
            error.value = 'The selected file could not be read as an image.';
        }
    } finally {
        if (readToken === mediaReadToken) {
            isReadingMedia.value = false;
        }

        input.value = '';
    }
}

function removeMedia(): void {
    mediaReadToken += 1;
    isReadingMedia.value = false;
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

function getResponseMessage(data: string | Record<string, unknown>): string {
    let responseData: unknown = data;

    if (typeof data === 'string') {
        try {
            responseData = JSON.parse(data) as unknown;
        } catch {
            return 'AI content generation failed. Please try again.';
        }
    }

    if (
        typeof responseData === 'object' &&
        responseData !== null &&
        'message' in responseData &&
        typeof responseData.message === 'string'
    ) {
        return responseData.message;
    }

    return 'AI content generation failed. Please try again.';
}

async function requestAiContent(): Promise<void> {
    if (aiRequest.processing) {
        return;
    }

    aiError.value = '';
    aiNotice.value = '';
    aiVariants.value = [];
    aiRequest.operation = selectedOperation.value;
    aiRequest.topic = title.value.trim() || null;
    aiRequest.content = content.value;
    aiRequest.platform = platform.value;
    aiRequest.format = format.value;
    aiRequest.tone = tone.value;
    aiRequest.language = language.value;
    aiRequest.instructions = instructions.value.trim();
    aiRequest.variants = requestedVariants.value;

    if (
        selectedOperation.value === 'generate' &&
        !aiRequest.topic &&
        aiRequest.instructions
    ) {
        aiRequest.topic = aiRequest.instructions;
    }

    try {
        await aiRequest.post(generatePostContent.url(), {
            onSuccess: (response) => {
                aiVariants.value = response.variants;
                aiNotice.value = 'Review a result and choose how to apply it.';
            },
            onError: (errors) => {
                aiError.value =
                    Object.values(errors).flat().join(' ') ||
                    'Check the request details and try again.';
            },
            onHttpException: (response) => {
                aiError.value = getResponseMessage(response.data);

                return false;
            },
            onNetworkError: () => {
                aiError.value =
                    'Could not reach the server. Check your connection and try again.';
            },
        });
    } catch {
        if (!aiError.value) {
            aiError.value = 'AI content generation failed. Please try again.';
        }
    }
}

async function regenerateContent(): Promise<void> {
    selectedOperation.value = content.value.trim() ? 'rewrite' : 'generate';
    await requestAiContent();
}

function acceptAiVariant(variant: PostContentVariant, append: boolean): void {
    const hashtags = variant.hashtags
        .map((hashtag) => (hashtag.startsWith('#') ? hashtag : `#${hashtag}`))
        .join(' ');
    const acceptedContent = [variant.content, hashtags]
        .filter(Boolean)
        .join('\n\n');
    const nextContent =
        append && content.value.trim()
            ? `${content.value.trimEnd()}\n\n${acceptedContent}`
            : acceptedContent;

    if ([...nextContent].length > characterLimit.value) {
        aiError.value = `This result would exceed the ${platform.value} character limit. Shorten the draft before appending it.`;
        aiNotice.value = '';
        return;
    }

    content.value = nextContent;

    if (!append && !title.value.trim() && variant.title.trim()) {
        title.value = variant.title;
    }

    aiVariants.value = [];
    aiNotice.value = 'The selected result is now in your draft.';
}

function rejectAiResults(): void {
    aiVariants.value = [];
    aiNotice.value = 'AI suggestions dismissed. Your draft is unchanged.';
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

            <section
                class="grid gap-4 rounded-xl border border-secondary/30 bg-secondary/5 p-4"
                aria-labelledby="ai-assistant-heading"
            >
                <div class="flex items-start gap-3">
                    <Sparkles
                        class="mt-0.5 size-4 shrink-0 text-secondary"
                        aria-hidden="true"
                    />
                    <div class="grid gap-1">
                        <h3
                            id="ai-assistant-heading"
                            class="text-sm font-semibold"
                        >
                            AI writing assistant
                        </h3>
                        <p class="text-xs leading-5 text-muted-foreground">
                            Suggestions stay separate from your draft until you
                            choose to apply one. Your attached image and other
                            settings are preserved.
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
                    <div class="grid gap-2">
                        <label for="ai-operation" class="text-sm font-medium">
                            Writing action
                        </label>
                        <select
                            id="ai-operation"
                            v-model="selectedOperation"
                            :disabled="aiRequest.processing"
                            class="min-h-11 rounded-lg border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <option
                                v-for="operation in postContentOperations"
                                :key="operation"
                                :value="operation"
                            >
                                {{ operationLabels[operation] }}
                            </option>
                        </select>
                    </div>

                    <div class="grid gap-2 sm:w-28">
                        <label
                            for="ai-variant-count"
                            class="text-sm font-medium"
                        >
                            Variants
                        </label>
                        <select
                            id="ai-variant-count"
                            v-model.number="requestedVariants"
                            :disabled="aiRequest.processing"
                            class="min-h-11 rounded-lg border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <option :value="1">1</option>
                            <option :value="2">2</option>
                            <option :value="3">3</option>
                        </select>
                    </div>
                </div>

                <button
                    type="button"
                    :disabled="aiRequest.processing"
                    class="inline-flex min-h-11 items-center justify-center gap-2 justify-self-start rounded-lg bg-secondary px-4 py-2.5 text-sm font-medium text-secondary-foreground transition-colors hover:bg-secondary/80 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-wait disabled:opacity-60"
                    @click="requestAiContent"
                >
                    <span
                        v-if="aiRequest.processing"
                        class="size-4 animate-spin rounded-full border-2 border-current border-r-transparent"
                        aria-hidden="true"
                    ></span>
                    <Sparkles v-else class="size-4" aria-hidden="true" />
                    {{
                        aiRequest.processing
                            ? 'Generating…'
                            : aiVariants.length
                              ? 'Generate another set'
                              : 'Get AI suggestions'
                    }}
                </button>

                <p
                    v-if="aiError"
                    class="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive"
                    role="alert"
                >
                    {{ aiError }}
                </p>
                <p
                    v-if="aiNotice"
                    class="text-sm text-muted-foreground"
                    role="status"
                >
                    {{ aiNotice }}
                </p>

                <div
                    v-if="aiVariants.length"
                    class="grid gap-3"
                    aria-label="AI generated results"
                    aria-live="polite"
                >
                    <article
                        v-for="(variant, index) in aiVariants"
                        :key="`${index}-${variant.title}`"
                        class="grid gap-3 rounded-lg border border-border bg-background p-3"
                        :aria-label="`Generated variant ${index + 1}`"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <h4 class="text-sm font-semibold">
                                {{ variant.title || `Variant ${index + 1}` }}
                            </h4>
                            <span
                                v-if="aiVariants.length > 1"
                                class="text-xs text-muted-foreground"
                            >
                                {{ index + 1 }} of {{ aiVariants.length }}
                            </span>
                        </div>
                        <p
                            class="text-sm leading-6 break-words whitespace-pre-wrap"
                            :dir="language === 'Persian' ? 'rtl' : 'auto'"
                        >
                            {{ variant.content }}
                        </p>
                        <p
                            v-if="variant.hashtags.length"
                            class="text-sm text-primary"
                        >
                            {{
                                variant.hashtags
                                    .map((tag) =>
                                        tag.startsWith('#') ? tag : `#${tag}`,
                                    )
                                    .join(' ')
                            }}
                        </p>
                        <ul
                            v-if="variant.suggestions.length"
                            class="grid gap-1 text-xs text-muted-foreground"
                        >
                            <li
                                v-for="suggestion in variant.suggestions"
                                :key="suggestion"
                            >
                                {{ suggestion }}
                            </li>
                        </ul>
                        <div
                            class="flex flex-wrap gap-2 border-t border-border pt-3"
                        >
                            <button
                                type="button"
                                class="min-h-10 rounded-lg bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                @click="acceptAiVariant(variant, false)"
                            >
                                Replace draft
                            </button>
                            <button
                                type="button"
                                class="min-h-10 rounded-lg border border-border px-3 text-sm font-medium hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                @click="acceptAiVariant(variant, true)"
                            >
                                Append to draft
                            </button>
                            <button
                                type="button"
                                class="min-h-10 rounded-lg px-3 text-sm text-muted-foreground hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                @click="rejectAiResults"
                            >
                                Reject suggestions
                            </button>
                        </div>
                    </article>
                </div>
            </section>

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
                <div
                    v-if="isReadingMedia"
                    class="flex items-center gap-2 text-xs text-muted-foreground"
                    role="status"
                >
                    <span
                        class="size-3 animate-spin rounded-full border-2 border-current border-r-transparent"
                        aria-hidden="true"
                    ></span>
                    Loading image preview…
                </div>
                <div
                    v-else-if="mediaDataUrl"
                    class="flex max-w-sm items-center gap-3 rounded-lg border border-border bg-muted/30 p-2"
                >
                    <img
                        :src="mediaDataUrl"
                        :alt="
                            mediaFileName
                                ? `Selected image: ${mediaFileName}`
                                : 'Selected post image'
                        "
                        class="size-16 shrink-0 rounded-md bg-muted object-cover"
                    />
                    <div class="flex min-w-0 flex-col gap-1">
                        <span class="text-xs font-medium">Image attached</span>
                        <span class="truncate text-xs text-muted-foreground">
                            {{ mediaFileName }}
                        </span>
                    </div>
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
                    :disabled="aiRequest.processing"
                    title="Rewrite the current copy, or generate from the draft title."
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-border px-3.5 py-2 text-sm font-medium transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-wait disabled:opacity-60"
                    @click="regenerateContent"
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
                    <p class="text-xs text-muted-foreground">
                        {{
                            mediaDataUrl
                                ? 'Image attached'
                                : 'Text-only post · no image attached'
                        }}
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
                        <span class="mr-1.5 inline-block font-semibold">{{
                            user.name
                        }}</span>
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
