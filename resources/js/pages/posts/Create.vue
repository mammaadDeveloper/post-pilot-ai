<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { create } from '@/routes/posts';
import PageHeader from '@/components/postpilot/PageHeader.vue';
import PostComposer from '@/components/postpilot/PostComposer.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Create post',
                href: create(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Create post" />
    <div
        class="mx-auto flex w-full max-w-[1440px] flex-col gap-7 p-4 sm:p-6 lg:p-8"
    >
        <PageHeader
            title="Create a post"
            description="Write for a channel, refine your voice, and preview the copy before saving."
            eyebrow="POST STUDIO"
        />
        <PostComposer />
    </div>
</template>
