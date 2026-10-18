<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BookOpen,
    CalendarDays,
    ChartNoAxesCombined,
    FileText,
    FolderGit2,
    LayoutGrid,
    PenLine,
    Settings,
    UserRound,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import * as postsRoute from '@/routes/posts';
import * as scheduleRoute from '@/routes/schedule';
import * as analyticsRoute from '@/routes/analytics';
import * as profileRoute from '@/routes/profile';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Create Post',
        href: postsRoute.create(),
        icon: PenLine,
    },
    {
        title: 'Saved Drafts',
        href: postsRoute.drafts(),
        icon: FileText,
    },
    {
        title: 'Schedule',
        href: scheduleRoute.index(),
        icon: CalendarDays,
    },
    {
        title: 'Analytics',
        href: analyticsRoute.index(),
        icon: ChartNoAxesCombined,
    },
    {
        title: 'My Profile',
        href: profileRoute.show(),
        icon: UserRound,
    },
    {
        title: 'Settings',
        href: profileRoute.edit(),
        icon: Settings,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
