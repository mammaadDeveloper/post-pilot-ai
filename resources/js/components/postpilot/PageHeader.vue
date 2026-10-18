<script setup lang="ts">
defineProps<{
    title: string;
    description: string;
    eyebrow?: string;
}>();
</script>

<template>
    <header
        class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
    >
        <div class="flex flex-col gap-2">
            <p
                v-if="eyebrow"
                class="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
            >
                {{ eyebrow }}
            </p>
            <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">
                {{ title }}
            </h1>
            <p class="max-w-2xl text-sm leading-6 text-muted-foreground">
                {{ description }}
            </p>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
