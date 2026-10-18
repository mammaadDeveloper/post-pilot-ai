<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    BadgeCheck,
    CalendarDays,
    KeyRound,
    Palette,
    UserRound,
} from '@lucide/vue';
import type { User } from '@/types/auth';
import { show } from '@/routes/profile';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editSecurity } from '@/routes/security';
import { edit as editProfile } from '@/routes/profile';
import PageHeader from '@/components/postpilot/PageHeader.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'My profile',
                href: show(),
            },
        ],
    },
});

const props = defineProps<{
    user: User;
}>();

const initials = computed(() =>
    props.user.name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase(),
);
const memberSince = computed(() =>
    new Intl.DateTimeFormat(undefined, {
        month: 'long',
        year: 'numeric',
    }).format(new Date(props.user.created_at)),
);
</script>

<template>
    <Head title="My profile" />
    <div
        class="mx-auto flex w-full max-w-[1200px] flex-col gap-7 p-4 sm:p-6 lg:p-8"
    >
        <PageHeader
            title="My profile"
            description="Your creator identity and the account details connected to this workspace."
            eyebrow="ACCOUNT"
        />

        <section
            class="flex flex-col gap-6 rounded-2xl border border-border bg-card p-6 shadow-sm sm:flex-row sm:items-center sm:p-8"
        >
            <div
                class="relative flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary text-2xl font-semibold text-primary-foreground"
            >
                <img
                    v-if="user.avatar"
                    :src="user.avatar"
                    alt=""
                    class="size-full object-cover"
                />
                <span v-else aria-hidden="true">{{ initials }}</span>
                <BadgeCheck
                    v-if="user.email_verified_at"
                    class="absolute right-0 bottom-0 size-6 rounded-full bg-card text-emerald-700 dark:text-emerald-300"
                    aria-label="Verified email"
                />
            </div>
            <div class="flex min-w-0 flex-1 flex-col gap-2">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-2xl font-semibold tracking-tight">
                        {{ user.name }}
                    </h2>
                    <span
                        v-if="user.email_verified_at"
                        class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200"
                    >
                        Verified
                    </span>
                </div>
                <p class="text-sm break-all text-muted-foreground">
                    {{ user.email }}
                </p>
                <p class="text-sm text-muted-foreground">
                    PostPilot workspace member
                </p>
                <div
                    class="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                >
                    <CalendarDays class="size-4" aria-hidden="true" />
                    Member since {{ memberSince }}
                </div>
            </div>
            <Link
                :href="editProfile()"
                class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
            >
                Edit account
                <ArrowUpRight class="size-4" aria-hidden="true" />
            </Link>
        </section>

        <section
            class="grid gap-5 lg:grid-cols-3"
            aria-label="Account settings"
        >
            <Link
                :href="editProfile()"
                class="group flex min-h-48 flex-col gap-4 rounded-2xl border border-border bg-card p-5 transition-colors hover:border-primary/30 hover:bg-primary/[0.02] focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:p-6"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-xl bg-primary/5 text-primary"
                >
                    <UserRound class="size-5" aria-hidden="true" />
                </span>
                <span class="flex flex-1 flex-col gap-1">
                    <span class="font-semibold">Profile details</span>
                    <span class="text-sm leading-5 text-muted-foreground"
                        >Update the name and email on your account.</span
                    >
                </span>
                <span
                    class="inline-flex items-center gap-1 text-sm font-medium text-primary"
                >
                    Manage profile
                    <ArrowUpRight
                        class="size-4 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
                        aria-hidden="true"
                    />
                </span>
            </Link>
            <Link
                :href="editAppearance()"
                class="group flex min-h-48 flex-col gap-4 rounded-2xl border border-border bg-card p-5 transition-colors hover:border-primary/30 hover:bg-primary/[0.02] focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:p-6"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-xl bg-primary/5 text-primary"
                >
                    <Palette class="size-5" aria-hidden="true" />
                </span>
                <span class="flex flex-1 flex-col gap-1">
                    <span class="font-semibold">Appearance</span>
                    <span class="text-sm leading-5 text-muted-foreground"
                        >Choose light, dark, or system display mode.</span
                    >
                </span>
                <span
                    class="inline-flex items-center gap-1 text-sm font-medium text-primary"
                >
                    Change appearance
                    <ArrowUpRight
                        class="size-4 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
                        aria-hidden="true"
                    />
                </span>
            </Link>
            <Link
                :href="editSecurity()"
                class="group flex min-h-48 flex-col gap-4 rounded-2xl border border-border bg-card p-5 transition-colors hover:border-primary/30 hover:bg-primary/[0.02] focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:p-6"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-xl bg-primary/5 text-primary"
                >
                    <KeyRound class="size-5" aria-hidden="true" />
                </span>
                <span class="flex flex-1 flex-col gap-1">
                    <span class="font-semibold">Security</span>
                    <span class="text-sm leading-5 text-muted-foreground"
                        >Manage password and account security options.</span
                    >
                </span>
                <span
                    class="inline-flex items-center gap-1 text-sm font-medium text-primary"
                >
                    Review security
                    <ArrowUpRight
                        class="size-4 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
                        aria-hidden="true"
                    />
                </span>
            </Link>
        </section>

        <aside
            class="rounded-xl border border-dashed border-border bg-muted/30 p-5"
        >
            <h2 class="text-sm font-semibold">
                Creator voice and publishing preferences
            </h2>
            <p class="mt-1 max-w-3xl text-sm leading-6 text-muted-foreground">
                Biographical details, channel preferences, subscription
                information, and AI voice calibration are not part of the
                current account data model. No profile settings are simulated
                here.
            </p>
        </aside>
    </div>
</template>
