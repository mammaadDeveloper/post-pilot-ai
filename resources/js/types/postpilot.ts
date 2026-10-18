export const platforms = ['LinkedIn', 'Instagram', 'X'] as const;

export type Platform = (typeof platforms)[number];
export type PostFormat =
    | 'Standard Post'
    | 'Educational Post'
    | 'Promotional Post'
    | 'Thought Leadership';
export type PostTone =
    | 'Professional'
    | 'Friendly'
    | 'Educational'
    | 'Casual'
    | 'Persuasive';
export type PostLanguage = 'English' | 'Persian';
export type ScheduleStatus = 'Scheduled' | 'Draft' | 'Needs Attention';

export const postContentOperations = [
    'generate',
    'rewrite',
    'proofread',
    'improve',
    'professional',
    'engaging',
    'change_tone',
    'shorten',
    'expand',
    'improve_hook',
    'adapt',
    'caption_hashtags',
    'ideas',
    'custom',
] as const;

export type PostContentOperation = (typeof postContentOperations)[number];

export type PostContentVariant = {
    title: string;
    content: string;
    hashtags: string[];
    suggestions: string[];
};

export type PostContentResponse = {
    operation: PostContentOperation;
    variants: PostContentVariant[];
};

export type PostDraft = {
    id: string;
    title: string;
    content: string;
    platform: Platform;
    format: PostFormat;
    tone: PostTone;
    language: PostLanguage;
    instructions: string;
    mediaDataUrl?: string;
    mediaFileName?: string;
    createdAt: string;
    updatedAt: string;
};

export type ScheduledPost = {
    id: string;
    draftId: string;
    title: string;
    platform: Platform;
    scheduledAt: string;
    status: ScheduleStatus;
    createdAt: string;
};
