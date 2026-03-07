<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    tickets: {
        data: Array<{
            id: number;
            ticket_number: string;
            subject: string;
            status: string;
            created_at: string;
            tenant: { id: number; name: string; slug: string } | null;
            requester: { name: string; email: string } | null;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: {
        search?: string;
        status?: string;
        tenant_id?: number | null;
    };
    statuses: Array<{ value: string; label: string; color: string }>;
    tenants: Array<{ id: number; name: string }>;
    urls: {
        index: string;
    };
}>();

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? 'all');
const tenantId = ref(props.filters.tenant_id ? String(props.filters.tenant_id) : 'all');

function applyFilters() {
    router.get(
        props.urls.index,
        {
            search: search.value || null,
            status: status.value === 'all' ? null : status.value,
            tenant_id: tenantId.value === 'all' ? null : Number(tenantId.value),
        },
        { preserveState: true, replace: true },
    );
}

function formatDate(value: string): string {
    return new Date(value).toLocaleString();
}
</script>

<template>
    <Head title="Tenant Support" />
    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div>
                <h1 class="text-2xl font-semibold">Tenant Support Inbox</h1>
                <p class="text-sm text-muted-foreground">Tickets opened by tenant admins to contact platform support.</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <Input v-model="search" placeholder="Search by ticket, tenant, or user..." class="max-w-md" @keyup.enter="applyFilters" />
                <select v-model="status" class="h-10 rounded-md border border-input bg-background px-3 text-sm" @change="applyFilters">
                    <option value="all">All statuses</option>
                    <option v-for="item in statuses" :key="item.value" :value="item.value">{{ item.label }}</option>
                </select>
                <select v-model="tenantId" class="h-10 rounded-md border border-input bg-background px-3 text-sm" @change="applyFilters">
                    <option value="all">All tenants</option>
                    <option v-for="tenant in tenants" :key="tenant.id" :value="String(tenant.id)">{{ tenant.name }}</option>
                </select>
                <Button @click="applyFilters">Search</Button>
            </div>

            <div class="overflow-x-auto rounded-lg border bg-card">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b bg-muted/30 text-left text-xs uppercase text-muted-foreground">
                            <th class="px-4 py-3">Ticket</th>
                            <th class="px-4 py-3">Tenant</th>
                            <th class="px-4 py-3">Requester</th>
                            <th class="px-4 py-3">Subject</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Created</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="ticket in tickets.data" :key="ticket.id" class="border-b last:border-b-0">
                            <td class="px-4 py-3 text-sm font-medium">{{ ticket.ticket_number }}</td>
                            <td class="px-4 py-3 text-sm">
                                {{ ticket.tenant?.name || '-' }}
                                <div class="text-xs text-muted-foreground">{{ ticket.tenant?.slug || '-' }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                {{ ticket.requester?.name || '-' }}
                                <div class="text-xs text-muted-foreground">{{ ticket.requester?.email || '-' }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ ticket.subject }}</td>
                            <td class="px-4 py-3 text-sm">
                                {{ statuses.find((item) => item.value === ticket.status)?.label || ticket.status }}
                            </td>
                            <td class="px-4 py-3 text-sm text-muted-foreground">{{ formatDate(ticket.created_at) }}</td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="`${urls.index}/${ticket.id}`" class="text-sm font-medium text-primary hover:underline">Open</Link>
                            </td>
                        </tr>
                        <tr v-if="tickets.data.length === 0">
                            <td colspan="7" class="px-4 py-6 text-center text-sm text-muted-foreground">No tenant support tickets found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </SuperAdminLayout>
</template>

