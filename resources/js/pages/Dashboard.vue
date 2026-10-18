<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    ArrowUpRight,
    CalendarDays,
    CircleCheck,
    CircleUserRound,
    Palette,
    Settings2,
    Sparkles,
} from '@lucide/vue';
import { dashboard } from '@/routes';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const user = usePage().props.auth.user;
const firstName = user.name.trim().split(/\s+/)[0] || 'there';
const today = new Intl.DateTimeFormat('en', {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
}).format(new Date());
const memberSince = new Intl.DateTimeFormat('en', {
    month: 'long',
    year: 'numeric',
}).format(new Date(user.created_at));
</script>

<template>
    <Head title="Dashboard" />

    <main
        class="mx-auto flex w-full max-w-[1440px] flex-col gap-8 p-4 sm:p-6 lg:p-8"
        aria-labelledby="dashboard-title"
    >
        <header
            class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between"
        >
            <div class="flex flex-col gap-2">
                <p
                    class="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                >
                    {{ today }}
                </p>
                <h1
                    id="dashboard-title"
                    class="text-3xl font-semibold tracking-tight text-foreground sm:text-4xl"
                >
                    Welcome back, {{ firstName }}
                </h1>
                <p class="max-w-2xl text-sm leading-6 text-muted-foreground">
                    A thoughtful home for your PostPilot workspace.
                </p>
            </div>

            <Link
                :href="editProfile()"
                class="inline-flex min-h-11 w-fit items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
            >
                <CircleUserRound class="size-4" aria-hidden="true" />
                Manage profile
                <ArrowUpRight class="size-4" aria-hidden="true" />
            </Link>
        </header>

        <section
            class="relative isolate overflow-hidden rounded-2xl bg-[#3f091d] p-6 text-white shadow-sm sm:p-8 lg:p-10"
            aria-labelledby="workspace-heading"
        >
            <div
                class="pointer-events-none absolute -top-24 -right-14 -z-10 size-72 rounded-full border border-white/10"
                aria-hidden="true"
            >
                <div
                    class="absolute inset-8 rounded-full border border-white/10"
                ></div>
            </div>

            <div class="flex max-w-2xl flex-col items-start gap-5">
                <span
                    class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-medium text-white/90"
                >
                    <Sparkles
                        class="size-3.5 text-[#e0c295]"
                        aria-hidden="true"
                    />
                    Your workspace is ready
                </span>
                <div class="flex flex-col gap-3">
                    <h2
                        id="workspace-heading"
                        class="max-w-xl text-2xl leading-tight font-semibold tracking-tight sm:text-3xl"
                    >
                        Your next great idea starts here.
                    </h2>
                    <p
                        class="max-w-xl text-sm leading-6 text-white/75 sm:text-base"
                    >
                        Publishing activity and analytics will appear here when
                        they’re available for your workspace. Until then, keep
                        your account details and preferences up to date.
                    </p>
                </div>
                <Link
                    :href="editProfile()"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-[#3f091d] transition-colors hover:bg-white/90 focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#3f091d] focus-visible:outline-none"
                >
                    Review account details
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Link>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-12">
            <section
                class="flex flex-col gap-5 rounded-2xl border border-border bg-card p-6 sm:p-7 lg:col-span-7"
                aria-labelledby="account-heading"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="flex flex-col gap-1">
                        <h2
                            id="account-heading"
                            class="text-lg font-semibold tracking-tight"
                        >
                            Account at a glance
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            The details connected to your workspace.
                        </p>
                    </div>
                    <span
                        v-if="user.email_verified_at"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200"
                    >
                        <CircleCheck class="size-3.5" aria-hidden="true" />
                        Verified
                    </span>
                </div>

                <dl class="grid gap-4 sm:grid-cols-2">
                    <div
                        class="flex min-w-0 flex-col gap-1 rounded-xl bg-muted/60 p-4"
                    >
                        <dt
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Name
                        </dt>
                        <dd
                            class="truncate text-sm font-medium text-foreground"
                        >
                            {{ user.name }}
                        </dd>
                    </div>
                    <div
                        class="flex min-w-0 flex-col gap-1 rounded-xl bg-muted/60 p-4"
                    >
                        <dt
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Email address
                        </dt>
                        <dd
                            class="truncate text-sm font-medium text-foreground"
                        >
                            {{ user.email }}
                        </dd>
                    </div>
                    <div
                        class="flex min-w-0 flex-col gap-1 rounded-xl bg-muted/60 p-4 sm:col-span-2"
                    >
                        <dt
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Member since
                        </dt>
                        <dd
                            class="flex items-center gap-2 text-sm font-medium text-foreground"
                        >
                            <CalendarDays
                                class="size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                            {{ memberSince }}
                        </dd>
                    </div>
                </dl>

                <Link
                    :href="editProfile()"
                    class="group inline-flex w-fit items-center gap-2 text-sm font-medium text-primary hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                >
                    Edit account details
                    <ArrowRight
                        class="size-4 transition-transform group-hover:translate-x-0.5"
                        aria-hidden="true"
                    />
                </Link>
            </section>

            <section
                class="flex flex-col gap-5 rounded-2xl border border-border bg-card p-6 sm:p-7 lg:col-span-5"
                aria-labelledby="preferences-heading"
            >
                <div class="flex items-start gap-3">
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#f5ebf3] text-[#5a1f32] dark:bg-[#5a1f32]/40 dark:text-[#ffd9e0]"
                    >
                        <Settings2 class="size-5" aria-hidden="true" />
                    </span>
                    <div class="flex flex-col gap-1">
                        <h2
                            id="preferences-heading"
                            class="text-lg font-semibold tracking-tight"
                        >
                            Make it yours
                        </h2>
                        <p class="text-sm leading-5 text-muted-foreground">
                            Set the details and look that feel right to you.
                        </p>
                    </div>
                </div>

                <nav aria-label="Workspace preferences">
                    <ul class="flex flex-col divide-y divide-border">
                        <li>
                            <Link
                                :href="editProfile()"
                                class="group flex min-h-16 items-center justify-between gap-4 py-3 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <span class="flex items-center gap-3">
                                    <CircleUserRound
                                        class="size-4 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <span class="flex flex-col gap-0.5">
                                        <span class="text-sm font-medium"
                                            >Profile</span
                                        >
                                        <span
                                            class="text-xs text-muted-foreground"
                                            >Name and email address</span
                                        >
                                    </span>
                                </span>
                                <ArrowRight
                                    class="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-foreground"
                                    aria-hidden="true"
                                />
                            </Link>
                        </li>
                        <li>
                            <Link
                                :href="editAppearance()"
                                class="group flex min-h-16 items-center justify-between gap-4 py-3 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <span class="flex items-center gap-3">
                                    <Palette
                                        class="size-4 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <span class="flex flex-col gap-0.5">
                                        <span class="text-sm font-medium"
                                            >Appearance</span
                                        >
                                        <span
                                            class="text-xs text-muted-foreground"
                                            >Choose your display theme</span
                                        >
                                    </span>
                                </span>
                                <ArrowRight
                                    class="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-foreground"
                                    aria-hidden="true"
                                />
                            </Link>
                        </li>
                    </ul>
                </nav>
            </section>
        </div>
    </main>
</template>
