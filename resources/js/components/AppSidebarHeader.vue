<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { useTrans } from '@/composables/useTrans';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItemType } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { Languages } from 'lucide-vue-next';
import { computed } from 'vue';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage<any>();
const { locale, t } = useTrans();

const localeSwitcherUrl = (targetLocale: string) =>
    `/locale/${targetLocale}?redirect=${encodeURIComponent(page.url || '/')}`;

const nextLocale = computed(() => (locale.value === 'ar' ? 'en' : 'ar'));
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <a
                :href="localeSwitcherUrl(nextLocale)"
                :title="t('language.label')"
                class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-sidebar-border/70 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground sm:hidden"
            >
                <Languages class="h-4 w-4" />
            </a>

            <div
                class="hidden items-center gap-1 rounded-md border border-sidebar-border/70 px-2 py-1 sm:flex"
            >
                <Languages class="h-4 w-4 text-muted-foreground" />
                <a
                    :href="localeSwitcherUrl('en')"
                    class="rounded px-2 py-0.5 text-xs font-semibold transition-colors"
                    :class="
                        locale === 'en'
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                >
                    EN
                </a>
                <a
                    :href="localeSwitcherUrl('ar')"
                    class="rounded px-2 py-0.5 text-xs font-semibold transition-colors"
                    :class="
                        locale === 'ar'
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                >
                    AR
                </a>
            </div>
        </div>
    </header>
</template>
