<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ref } from 'vue';

const props = defineProps<{
    contracts: {
        data: Array<{
            id: number;
            contract_number: string;
            status: string;
            reservation_number?: string | null;
            renter_name?: string | null;
            branch_name?: string | null;
            start_date?: string | null;
            end_date?: string | null;
            total_amount?: string | number | null;
            currency?: string | null;
            has_start_contract: boolean;
            has_end_contract: boolean;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: {
        search?: string;
        status?: string;
        branch_id?: number | null;
    };
    statuses: string[];
    branches: Array<{ id: number; name: string }>;
    canAccessAllBranches: boolean;
    actions: {
        index: string;
        create: string;
    };
}>();

const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? 'all');
const branchId = ref<string>(props.filters?.branch_id ? String(props.filters.branch_id) : 'all');

const submitFilters = () => {
    router.get(
        props.actions.index,
        {
            search: search.value || undefined,
            status: status.value === 'all' ? undefined : status.value,
            branch_id: branchId.value === 'all' ? undefined : branchId.value,
        },
        { preserveState: true, replace: true },
    );
};
</script>

<template>
    <Head title="Contracts" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">Contracts</h1>
                <Link :href="actions.create">
                    <Button>Create Contract</Button>
                </Link>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                <Input v-model="search" placeholder="Search by contract/reservation/renter..." @keyup.enter="submitFilters" />
                <select
                    v-model="status"
                    class="rounded-md border border-input bg-transparent px-3 py-2"
                    @change="submitFilters"
                >
                    <option value="all">All statuses</option>
                    <option v-for="item in statuses" :key="item" :value="item">{{ item }}</option>
                </select>

                <select
                    v-if="canAccessAllBranches"
                    v-model="branchId"
                    class="rounded-md border border-input bg-transparent px-3 py-2"
                    @change="submitFilters"
                >
                    <option value="all">All branches</option>
                    <option v-for="branch in branches" :key="branch.id" :value="String(branch.id)">{{ branch.name }}</option>
                </select>

                <Button variant="outline" @click="submitFilters">Filter</Button>
            </div>

            <div class="overflow-x-auto rounded-md border">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Contract #</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Reservation</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Renter</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Branch</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Files</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="item in contracts.data" :key="item.id">
                            <td class="px-4 py-3 font-medium">{{ item.contract_number }}</td>
                            <td class="px-4 py-3">{{ item.reservation_number || '—' }}</td>
                            <td class="px-4 py-3">{{ item.renter_name || '—' }}</td>
                            <td class="px-4 py-3">{{ item.branch_name || '—' }}</td>
                            <td class="px-4 py-3 capitalize">{{ item.status }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                Start: {{ item.has_start_contract ? 'Yes' : 'No' }} / End: {{ item.has_end_contract ? 'Yes' : 'No' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2">
                                    <Link :href="`${actions.index}/${item.id}`">
                                        <Button size="sm" variant="outline">View</Button>
                                    </Link>
                                    <Link :href="`${actions.index}/${item.id}/edit`">
                                        <Button size="sm" variant="outline">Edit</Button>
                                    </Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="contracts.data.length === 0">
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">No contracts found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap gap-2">
                <template v-for="link in contracts.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded border px-3 py-1 text-sm"
                        :class="link.active ? 'bg-primary text-primary-foreground' : 'bg-white'"
                        v-html="link.label"
                    />
                    <span v-else class="rounded border px-3 py-1 text-sm text-gray-400" v-html="link.label" />
                </template>
            </div>
        </main>
    </AdminLayout>
</template>
