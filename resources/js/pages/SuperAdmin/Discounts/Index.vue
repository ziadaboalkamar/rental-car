<script setup lang="ts">
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Tag, Edit, Trash2, CheckCircle, XCircle, Percent, Calendar } from 'lucide-vue-next';
import { type Discount } from '@/types';
import { useTrans } from '@/composables/useTrans';

const props = defineProps<{
    discounts: Discount[];
}>();
const { t, locale } = useTrans();

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString(locale === 'ar' ? 'ar' : 'en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    });
};

const deleteDiscount = (id: number, name: string) => {
    if (confirm(`Are you sure you want to delete the discount "${name}"?`)) {
        router.delete(`/superadmin/discounts/${id}`, {
            onSuccess: () => {
                // Flash message handled by backend
            },
        });
    }
};
</script>

<template>
    <Head :title="t('dashboard.super_admin.discounts.index.head_title')" />
    <SuperAdminLayout>
        <main class="flex-1 p-8 space-y-6">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">{{ t('dashboard.super_admin.discounts.index.title') }}</h1>
                <Link href="/superadmin/discounts/create">
                    <Button>+ {{ t('dashboard.super_admin.discounts.index.create_discount') }}</Button>
                </Link>
            </div>

            <p class="text-muted-foreground text-sm">
                {{ t('dashboard.super_admin.discounts.index.subtitle') }}
            </p>

            <div class="overflow-x-auto rounded-md border">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.super_admin.discounts.index.discount') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.common.plan') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.super_admin.discounts.index.value') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.super_admin.discounts.index.validity') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.common.status') }}</th>
                            <th class="px-4 py-3 text-right">{{ t('dashboard.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="discount in discounts" :key="discount.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                                        <Percent class="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <div class="font-medium">{{ discount.name }}</div>
                                        <div v-if="discount.code" class="text-xs font-mono bg-gray-100 px-1 rounded">{{ discount.code }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                {{ discount.plan?.name || t('dashboard.super_admin.discounts.index.all_plans') }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-semibold text-sm">
                                    {{ discount.type === 'percentage' ? `${discount.value}%` : `$${discount.value}` }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2 text-xs text-gray-600">
                                    <Calendar class="h-3 w-3" />
                                    {{ formatDate(discount.start_date) }} - {{ formatDate(discount.end_date) }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span v-if="discount.is_active" class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">
                                    <CheckCircle class="h-3 w-3" />
                                    {{ t('dashboard.common.active') }}
                                </span>
                                <span v-else class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-700">
                                    <XCircle class="h-3 w-3" />
                                    {{ t('dashboard.common.inactive') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <Link :href="`/superadmin/discounts/${discount.id}/edit`">
                                    <Button variant="outline" size="sm">
                                        <Edit class="h-4 w-4 mr-1" />
                                        {{ t('dashboard.common.edit') }}
                                    </Button>
                                </Link>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    @click="deleteDiscount(discount.id, discount.name)"
                                >
                                    <Trash2 class="h-4 w-4 mr-1" />
                                    {{ t('dashboard.common.delete') }}
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="discounts.length === 0">
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500">
                                {{ t('dashboard.super_admin.discounts.index.empty') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </SuperAdminLayout>
</template>
