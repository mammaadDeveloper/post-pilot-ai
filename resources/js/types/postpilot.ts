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
