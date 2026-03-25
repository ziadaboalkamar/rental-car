<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import ClientLayout from '@/layouts/ClientLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { show } from '@/routes/client/reservations';

const props = defineProps<{
    reservations: {
        data: Array<{
            id: number;
            reservation_number: string;
            car: {
                id: number;
                make: string;
                model: string;
                year: number;
                license_plate: string;
            } | null;
            start_date: string;
            end_date: string;
            total_days: number;
            total_amount: number | string;
            status: string;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    currency: { symbol: string; code: string };
}>();

const { t } = useTrans();

const navigateToReservation = (id: number) => {
    router.visit(show(id).url);
};
</script>

<template>
    <Head :title="t('client_pages.reservations.index.head_title')" />
    <ClientLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">
                    {{ t('client_pages.reservations.index.title') }}
                </h1>
            </div>

            <div class="overflow-x-auto rounded-md border">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                #
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                {{
                                    t(
                                        'client_pages.reservations.index.table.car',
                                    )
                                }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                {{
                                    t(
                                        'client_pages.reservations.index.table.dates',
                                    )
                                }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                {{
                                    t(
                                        'client_pages.reservations.index.table.total',
                                    )
                                }}
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                {{
                                    t(
                                        'client_pages.reservations.index.table.status',
                                    )
                                }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr
                            v-for="res in props.reservations.data"
                            :key="res.id"
                            class="cursor-pointer transition-colors hover:bg-gray-50"
                            @click="navigateToReservation(res.id)"
                        >
                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    {{ res.reservation_number }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    {{
                                        res.car
                                            ? `${res.car.year} ${res.car.make} ${res.car.model}`
                                            : '-'
                                    }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ res.car?.license_plate }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    {{
                                        new Date(
                                            res.start_date,
                                        ).toLocaleDateString()
                                    }}
                                    ->
                                    {{
                                        new Date(
                                            res.end_date,
                                        ).toLocaleDateString()
                                    }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{
                                        t(
                                            'client_pages.reservations.index.days',
                                            { count: res.total_days },
                                        )
                                    }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                {{ props.currency.symbol }}
                                {{ Number(res.total_amount).toFixed(2) }}
                            </td>
                            <td class="px-4 py-3">{{ res.status }}</td>
                        </tr>
                        <tr v-if="props.reservations.data.length === 0">
                            <td
                                colspan="7"
                                class="px-4 py-6 text-center text-gray-500"
                            >
                                {{ t('client_pages.reservations.index.empty') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="props.reservations.links?.length" class="flex gap-2">
                <Link
                    v-for="(link, i) in props.reservations.links"
                    :key="i"
                    :href="link.url || ''"
                    :class="[
                        'rounded px-3 py-1 text-sm',
                        link.active
                            ? 'bg-gray-900 text-white'
                            : 'bg-gray-100 text-gray-700',
                        !link.url && 'pointer-events-none opacity-50',
                    ]"
                >
                    <span v-html="link.label" />
                </Link>
            </nav>
        </main>
    </ClientLayout>
</template>
