<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    contract: {
        id: number;
        contract_number: string;
        status: string;
        contract_date?: string | null;
        renter_name?: string | null;
        renter_id_number?: string | null;
        renter_phone?: string | null;
        car_details?: string | null;
        plate_number?: string | null;
        start_date?: string | null;
        end_date?: string | null;
        total_amount?: string | number | null;
        currency?: string | null;
        notes?: string | null;
        ai_extraction_status?: string | null;
        ai_extracted_data?: Record<string, unknown> | null;
        reservation?: {
            id: number;
            reservation_number: string;
            user_name?: string | null;
            car?: string | null;
        } | null;
        branch_name?: string | null;
    };
    startRentalDocument?: { id: number; name: string; url: string } | null;
    endRentalDocument?: { id: number; name: string; url: string } | null;
    actions: {
        index: string;
        edit: string;
    };
}>();
</script>

<template>
    <Head :title="`Contract ${contract.contract_number}`" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">Contract {{ contract.contract_number }}</h1>
                <div class="flex gap-2">
                    <Link :href="actions.index">
                        <Button variant="outline">Back</Button>
                    </Link>
                    <Link :href="actions.edit">
                        <Button variant="outline">Edit</Button>
                    </Link>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div class="rounded-md border p-4">
                    <h2 class="mb-3 font-semibold">Contract Details</h2>
                    <div class="space-y-2 text-sm">
                        <div><strong>Status:</strong> {{ contract.status }}</div>
                        <div><strong>Date:</strong> {{ contract.contract_date || '—' }}</div>
                        <div><strong>Branch:</strong> {{ contract.branch_name || '—' }}</div>
                        <div><strong>Amount:</strong> {{ contract.total_amount || '0.00' }} {{ contract.currency || '' }}</div>
                    </div>
                </div>

                <div class="rounded-md border p-4">
                    <h2 class="mb-3 font-semibold">Renter</h2>
                    <div class="space-y-2 text-sm">
                        <div><strong>Name:</strong> {{ contract.renter_name || '—' }}</div>
                        <div><strong>ID:</strong> {{ contract.renter_id_number || '—' }}</div>
                        <div><strong>Phone:</strong> {{ contract.renter_phone || '—' }}</div>
                    </div>
                </div>

                <div class="rounded-md border p-4">
                    <h2 class="mb-3 font-semibold">Vehicle</h2>
                    <div class="space-y-2 text-sm">
                        <div><strong>Details:</strong> {{ contract.car_details || '—' }}</div>
                        <div><strong>Plate:</strong> {{ contract.plate_number || '—' }}</div>
                        <div><strong>Start:</strong> {{ contract.start_date || '—' }}</div>
                        <div><strong>End:</strong> {{ contract.end_date || '—' }}</div>
                    </div>
                </div>

                <div class="rounded-md border p-4">
                    <h2 class="mb-3 font-semibold">Reservation Link</h2>
                    <div class="space-y-2 text-sm">
                        <div><strong>Reservation #:</strong> {{ contract.reservation?.reservation_number || '—' }}</div>
                        <div><strong>Client:</strong> {{ contract.reservation?.user_name || '—' }}</div>
                        <div><strong>Car:</strong> {{ contract.reservation?.car || '—' }}</div>
                    </div>
                </div>

                <div class="rounded-md border p-4 md:col-span-2">
                    <h2 class="mb-3 font-semibold">Legacy Files</h2>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 text-sm">
                        <div class="rounded border p-3">
                            <div class="mb-1 font-medium">Start Rental Contract</div>
                            <a
                                v-if="startRentalDocument?.url"
                                :href="startRentalDocument.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-blue-600 hover:text-blue-700"
                            >
                                {{ startRentalDocument.name }}
                            </a>
                            <div v-else class="text-gray-500">No file uploaded.</div>
                        </div>

                        <div class="rounded border p-3">
                            <div class="mb-1 font-medium">End Rental Contract</div>
                            <a
                                v-if="endRentalDocument?.url"
                                :href="endRentalDocument.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-blue-600 hover:text-blue-700"
                            >
                                {{ endRentalDocument.name }}
                            </a>
                            <div v-else class="text-gray-500">No file uploaded.</div>
                        </div>
                    </div>
                </div>

                <div class="rounded-md border p-4 md:col-span-2">
                    <h2 class="mb-3 font-semibold">AI Extraction</h2>
                    <div class="text-sm"><strong>Status:</strong> {{ contract.ai_extraction_status || 'disabled' }}</div>
                    <pre
                        v-if="contract.ai_extracted_data"
                        class="mt-3 overflow-auto rounded bg-gray-100 p-3 text-xs"
                    >{{ JSON.stringify(contract.ai_extracted_data, null, 2) }}</pre>
                    <div v-else class="mt-2 text-sm text-gray-500">No extracted data yet.</div>
                </div>

                <div class="rounded-md border p-4 md:col-span-2" v-if="contract.notes">
                    <h2 class="mb-2 font-semibold">Notes</h2>
                    <p class="text-sm whitespace-pre-line">{{ contract.notes }}</p>
                </div>
            </div>
        </main>
    </AdminLayout>
</template>

