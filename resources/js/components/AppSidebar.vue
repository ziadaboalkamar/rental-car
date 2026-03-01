<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import SuperAdminNav from '@/components/SuperAdminNav.vue';
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
import { index as carsIndex } from "@/routes/admin/cars/index";
import { index as reservationsIndex } from "@/routes/admin/reservations/index";
import { index as clientsIndex } from "@/routes/admin/clients/index";
import { index as paymentsIndex } from "@/routes/admin/payments/index";
import { index as reportsIndex } from "@/routes/admin/reports/index";
import { index as supportIndex } from "@/routes/admin/support/index";
import { index as branchesIndex } from "@/routes/admin/branches/index";
import { index as employeesIndex } from "@/routes/admin/employees/index";
import { index as rolesIndex } from "@/routes/admin/roles/index";
import { index as contractsIndex } from "@/routes/admin/contracts/index";
import { type NavItem } from '@/types';
import { useTrans } from '@/composables/useTrans';
import { Link, usePage } from '@inertiajs/vue3';
import { Car, Calendar, User, CreditCard, BarChart, LifeBuoy, MapPin, Users, Shield, FileText } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { home } from '@/routes';
import { computed } from 'vue';

const page = usePage<any>();
const { t } = useTrans();
const isRtl = computed(() => page.props.direction === 'rtl' || page.props.locale === 'ar');
const stripLocalePrefix = (path: string) => {
    const locales = Array.isArray(page.props?.available_locales) ? page.props.available_locales : ['en', 'ar'];
    const escapedLocales = locales.map((locale: string) => locale.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
    const localeRegex = new RegExp(`^\\/(?:${escapedLocales.join('|')})(?=\\/|$)`);

    return path.replace(localeRegex, '') || '/';
};
const isSuperAdmin = computed(() => stripLocalePrefix(String(page.url || '/')).startsWith('/superadmin'));
const currentTenant = computed(() => page.props.current_tenant);
const authPermissions = computed<string[]>(() =>
    Array.isArray(page.props?.auth?.permissions) ? page.props.auth.permissions : [],
);

const mainNavItems = computed<NavItem[]>(() => {
    const slug = currentTenant.value?.slug;
    if (!slug) return [];

    return [
        {
            title: t('dashboard.sidebar.admin.cars'),
            href: carsIndex(slug).url,
            icon: Car,
            permission: 'tenant-manage-cars',
        },
        {
            title: t('dashboard.sidebar.admin.reservations'),
            href: reservationsIndex(slug).url,
            icon: Calendar,
            permission: 'tenant-manage-reservations',
        },
        {
            title: 'Contracts',
            href: contractsIndex(slug).url,
            icon: FileText,
            permission: 'tenant-manage-reservations',
        },
        {
            title: t('dashboard.sidebar.admin.clients'),
            href: clientsIndex(slug).url,
            icon: User,
            permission: 'tenant-manage-clients',
        },
        {
            title: t('dashboard.sidebar.admin.payments'),
            href: paymentsIndex(slug).url,
            icon: CreditCard,
            permission: 'tenant-manage-payments',
        },
        {
            title: t('dashboard.sidebar.admin.reports'),
            href: reportsIndex(slug).url,
            icon: BarChart,
            permission: 'tenant-view-reports',
        },
        {
            title: t('dashboard.sidebar.admin.support'),
            href: supportIndex(slug).url,
            icon: LifeBuoy,
            permission: 'tenant-manage-support',
        },
        {
            title: t('dashboard.sidebar.admin.branches'),
            href: branchesIndex(slug).url,
            icon: MapPin,
            permission: 'tenant-manage-branches',
        },
        {
            title: t('dashboard.sidebar.admin.employees'),
            href: employeesIndex(slug).url,
            icon: Users,
            permission: 'tenant-manage-employees',
        },
        {
            title: t('dashboard.sidebar.admin.roles'),
            href: rolesIndex(slug).url,
            icon: Shield,
            permission: 'tenant-manage-employees',
        },
        {
            title: 'Payment Providers',
            href: '/admin/settings/payment-providers',
            icon: CreditCard,
            permission: 'tenant-manage-settings',
        },
        {
            title: 'Website Settings',
            href: '/admin/settings/website',
            icon: Shield,
            permission: 'tenant-manage-settings',
        },
    ].filter((item) => !item.permission || authPermissions.value.includes(item.permission));
});
</script>

<template>
    <Sidebar :side="isRtl ? 'right' : 'left'" collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="isSuperAdmin ? '/superadmin' : (typeof home === 'function' ? home().url : '/')">
                            <div v-if="currentTenant && currentTenant.name" class="flex items-center gap-2">
                                <div class="bg-primary text-primary-foreground rounded-md p-1 font-bold text-xl h-8 w-8 flex items-center justify-center">
                                    {{ currentTenant.name.charAt(0) }}
                                </div>
                                <span class="font-semibold truncate">{{ currentTenant.name }}</span>
                            </div>
                            <AppLogo v-else />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <SuperAdminNav v-if="isSuperAdmin" />
            <NavMain v-else :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
