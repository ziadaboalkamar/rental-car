<script setup lang="ts">
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { CarFront, Search, Building2, Tag, Calendar, MoreHorizontal } from 'lucide-vue-next';
import { type Car } from '@/types';
import { ref, watch, computed } from 'vue';
import { debounce } from 'lodash';
import { useTrans } from '@/composables/useTrans';

const props = defineProps<{
    cars: {
        data: Car[];
        links: any[];
        last_page: number;
    };
    filters: {
        search?: string;
        status?: string;
    };
    statuses: Record<string, {
        label: string;
        count: number;
        color: string;
    }>;
}>();
const { t } = useTrans();

const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || 'all');

const doSearch = () => {
    router.get('/superadmin/cars', { 
        search: search.value,
        status: statusFilter.value === 'all' ? null : statusFilter.value
    }, {
        preserveState: true,
        replace: true,
    });
};

watch(search, debounce(() => doSearch(), 300));

// Generate status colors based on the colors from the backend
const statusStyles = computed(() => {
    const styles: Record<string, { bg: string; text: string; dot: string }> = {};
    
    for (const [status, data] of Object.entries(props.statuses || {})) {
        // Convert hex to RGB for the background with opacity
        const hex = data.color.replace('#', '');
        const r = parseInt(hex.substring(0, 2), 16);
        const g = parseInt(hex.substring(2, 4), 16);
        const b = parseInt(hex.substring(4, 6), 16);
        
        styles[status] = {
            bg: `rgba(${r}, ${g}, ${b}, 0.1)`,
            text: `text-[${data.color}]`,
            dot: data.color,
        };
    }
    
    return styles;
});

const getStatusStyle = (status: string) => {
    return statusStyles.value[status] || { 
        bg: 'rgba(107, 114, 128, 0.1)', 
        text: 'text-gray-500', 
        dot: '#6B7280' 
    };
};
</script>

<template>
    <Head :title="t('dashboard.super_admin.cars.index.head_title')" />
    <SuperAdminLayout>
        <main class="flex-1 p-8 space-y-6">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">{{ t('dashboard.super_admin.cars.index.title') }}</h1>
                <div class="relative w-64">
                    <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input
                        v-model="search"
                        type="search"
                        :placeholder="t('dashboard.super_admin.cars.index.search_placeholder')"
                        class="pl-8"
                    />
                </div>
            </div>

            <div class="flex flex-col gap-4">
                <!-- Status Filter -->
                <div class="flex flex-wrap items-center gap-2">
                    <label class="inline-flex items-center">
                        <input 
                            type="radio" 
                            class="hidden" 
                            v-model="statusFilter" 
                            value="all"
                            @change="doSearch"
                        >
                        <span 
                            class="px-3 py-1.5 text-sm rounded-full cursor-pointer transition-colors"
                            :class="{
                                'bg-primary text-primary-foreground': statusFilter === 'all',
                                'bg-muted text-muted-foreground hover:bg-muted/80': statusFilter !== 'all'
                            }"
                        >
                            {{ t('dashboard.common.all') }} ({{ Object.values(statuses).reduce((acc, curr) => acc + curr.count, 0) }})
                        </span>
                    </label>
                    
                    <template v-for="(status, key) in statuses" :key="key">
                        <label class="inline-flex items-center">
                            <input 
                                type="radio" 
                                class="hidden" 
                                v-model="statusFilter" 
                                :value="key"
                                @change="doSearch"
                            >
                            <span 
                                class="px-3 py-1.5 text-sm rounded-full cursor-pointer transition-colors flex items-center gap-1.5"
                                :class="{
                                    'bg-primary text-primary-foreground': statusFilter === key,
                                    'bg-muted text-muted-foreground hover:bg-muted/80': statusFilter !== key
                                }"
                            >
                                <span 
                                    class="w-2 h-2 rounded-full" 
                                    :style="{ backgroundColor: status.color }"
                                ></span>
                                {{ status.label }} ({{ status.count }})
                            </span>
                        </label>
                    </template>
                </div>
            </div>

            <div class="overflow-x-auto rounded-md border">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.common.car') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.common.license_plate') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.common.price_per_day') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.common.tenant') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.common.status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="car in cars.data" :key="car.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="h-12 w-16 bg-gray-100 rounded overflow-hidden flex-shrink-0">
                                        <img v-if="car.image_url" :src="car.image_url" :alt="car.make" class="h-full w-full object-cover" />
                                        <div v-else class="h-full w-full flex items-center justify-center text-gray-400">
                                            <CarFront class="h-6 w-6" />
                                        </div>
                                    </div>
                                    <div>
                                        <div class="font-medium text-sm">{{ car.year }} {{ car.make }} {{ car.model }}</div>
                                        <div class="text-xs text-muted-foreground">{{ car.color }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm font-mono truncate max-w-[120px]">
                                {{ car.license_plate }}
                            </td>
                            <td class="px-4 py-3 text-sm font-semibold">
                                ${{ car.price_per_day }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1.5 text-sm">
                                    <Building2 class="h-3.5 w-3.5 text-muted-foreground" />
                                    {{ car.tenant?.name || t('dashboard.common.unknown') }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span 
                                    class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-medium"
                                    :style="{
                                        backgroundColor: getStatusStyle(car.status).bg,
                                        color: getStatusStyle(car.status).dot
                                    }"
                                >
                                    <span 
                                        class="size-2 rounded-full" 
                                        :style="{ backgroundColor: getStatusStyle(car.status).dot }"
                                    />
                                    {{ car.status }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="cars.data.length === 0">
                            <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">
                                {{ t('dashboard.super_admin.cars.index.empty') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="cars.last_page > 1" class="flex items-center justify-center gap-2 mt-4">
                <Component
                    :is="link.url ? Link : 'span'"
                    v-for="(link, index) in cars.links"
                    :key="index"
                    :href="link.url"
                    v-html="link.label"
                    class="px-3 py-1 text-sm rounded border transition-colors inline-block"
                    :class="{
                        'bg-primary text-white border-primary': link.active,
                        'text-muted-foreground pointer-events-none opacity-50': !link.url,
                        'hover:bg-gray-50': link.url && !link.active
                    }"
                />
            </div>
        </main>
    </SuperAdminLayout>
</template>
