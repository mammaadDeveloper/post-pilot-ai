<script setup lang="ts">
import { BriefcaseBusiness, Camera, Send } from '@lucide/vue';
import type { Platform } from '@/types/postpilot';

defineProps<{
    platform: Platform;
    compact?: boolean;
}>();

const styles = {
    LinkedIn:
        'bg-sky-50 text-sky-800 ring-sky-700/15 dark:bg-sky-950 dark:text-sky-200',
    Instagram:
        'bg-rose-50 text-rose-800 ring-rose-700/15 dark:bg-rose-950 dark:text-rose-200',
    X: 'bg-neutral-100 text-neutral-800 ring-neutral-700/15 dark:bg-neutral-800 dark:text-neutral-200',
};
</script>

<template>
    <span
        class="inline-flex w-fit items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
        :class="styles[platform]"
    >
        <BriefcaseBusiness
            v-if="platform === 'LinkedIn'"
            class="size-3.5"
            aria-hidden="true"
        />
        <Camera
            v-else-if="platform === 'Instagram'"
            class="size-3.5"
            aria-hidden="true"
        />
        <Send v-else class="size-3.5" aria-hidden="true" />
        <span v-if="!compact">{{ platform }}</span>
        <span v-else class="sr-only">{{ platform }}</span>
    </span>
</template>
