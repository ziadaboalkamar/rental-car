<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps<{
    mode: 'create' | 'edit';
    contract: any | null;
    reservationOptions: Array<{
        id: number;
        reservation_number: string;
        label: string;
        car?: string | null;
        has_contract?: boolean;
    }>;
    startContractFiles: Array<{ id: number; url: string }>;
    endContractFiles: Array<{ id: number; url: string }>;
    ai: {
        contracts_extraction_enabled: boolean;
    };
    actions: {
        index: string;
        store?: string;
        update?: string;
        show?: string;
        extract?: string;
    };
}>();

const page = usePage<any>();

const form = useForm({
    reservation_id: props.contract?.reservation_id ?? '',
    contract_number: props.contract?.contract_number ?? '',
    status: props.contract?.status ?? 'draft',
    contract_date: props.contract?.contract_date ?? '',
    renter_name: props.contract?.renter_name ?? '',
    renter_id_number: props.contract?.renter_id_number ?? '',
    renter_phone: props.contract?.renter_phone ?? '',
    car_details: props.contract?.car_details ?? '',
    plate_number: props.contract?.plate_number ?? '',
    start_date: props.contract?.start_date ?? '',
    end_date: props.contract?.end_date ?? '',
    total_amount: props.contract?.total_amount ?? '',
    currency: props.contract?.currency ?? 'USD',
    notes: props.contract?.notes ?? '',
    ai_extracted_data: props.contract?.ai_extracted_data ?? null,
    start_contract_temp_folders: [] as string[],
    start_contract_removed_files: [] as number[],
    end_contract_temp_folders: [] as string[],
    end_contract_removed_files: [] as number[],
});

const startTempFolders = ref<string[]>([]);
const endTempFolders = ref<string[]>([]);
const startRemoved = ref<number[]>([]);
const endRemoved = ref<number[]>([]);
const aiExtracting = ref(false);
const aiMessage = ref('');
const aiError = ref('');
const extractedFields = ref<Record<string, any> | null>(props.contract?.ai_extracted_data ?? null);
const lastExtractionKey = ref('');
const lastFoldersForRetry = ref<string[]>([]);
const contractAllowedFileTypes = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/jpg',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];

watch(startTempFolders, (value) => (form.start_contract_temp_folders = [...value]), { deep: true });
watch(endTempFolders, (value) => (form.end_contract_temp_folders = [...value]), { deep: true });

watch(
    [startTempFolders, endTempFolders],
    async () => {
        if (!props.ai.contracts_extraction_enabled) return;
        if (aiExtracting.value) return;

        const folders = Array.from(
            new Set([
                ...startTempFolders.value,
                ...endTempFolders.value,
            ].filter((folder) => !!folder && String(folder).trim() !== ''))
        ) as string[];

        if (folders.length === 0) return;

        const key = [...folders].sort().join('|');
        if (key === lastExtractionKey.value) return;
        lastExtractionKey.value = key;

        await runAiExtraction(folders);
    },
    { deep: true }
);

function isBlank(value: unknown): boolean {
    if (value === null || value === undefined) return true;
    if (typeof value === 'string') return value.trim() === '';
    return false;
}

function applyExtractedFields(fields: Record<string, any>, force = false) {
    const map: Array<{ formKey: string; dataKey: string }> = [
        { formKey: 'contract_number', dataKey: 'contract_number' },
        { formKey: 'status', dataKey: 'status' },
        { formKey: 'contract_date', dataKey: 'contract_date' },
        { formKey: 'renter_name', dataKey: 'renter_name' },
        { formKey: 'renter_id_number', dataKey: 'renter_id_number' },
        { formKey: 'renter_phone', dataKey: 'renter_phone' },
        { formKey: 'car_details', dataKey: 'car_details' },
        { formKey: 'plate_number', dataKey: 'plate_number' },
        { formKey: 'start_date', dataKey: 'start_date' },
        { formKey: 'end_date', dataKey: 'end_date' },
        { formKey: 'total_amount', dataKey: 'total_amount' },
        { formKey: 'currency', dataKey: 'currency' },
        { formKey: 'notes', dataKey: 'notes' },
    ];
    const formState = form as Record<string, any>;

    map.forEach(({ formKey, dataKey }) => {
        const value = fields[dataKey];
        if (value === null || value === undefined || value === '') return;

        const shouldApply = force || isBlank(formState[formKey]);
        if (!shouldApply) return;

        if (formKey === 'total_amount') {
            formState[formKey] = String(value);
            return;
        }

        formState[formKey] = value;
    });

    extractedFields.value = fields;
    form.ai_extracted_data = fields;
}

async function runAiExtraction(folders: string[]) {
    aiExtracting.value = true;
    aiError.value = '';
    aiMessage.value = '';
    lastFoldersForRetry.value = [...folders];

    try {
        const response = await fetch(props.actions.extract || '/admin/contracts/extract', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': String(page.props.csrf_token || ''),
                Accept: 'application/json',
            },
            body: JSON.stringify({ temp_folders: folders }),
        });

        const payload = await response.json();
        if (!response.ok) {
        aiError.value = payload?.message || 'AI extraction failed.';
        return;
        }

        const fields = (payload?.fields || {}) as Record<string, any>;
        applyExtractedFields(fields, false);
        aiMessage.value = payload?.message || 'AI extraction completed.';
    } catch (error) {
        aiError.value = 'AI extraction request failed.';
    } finally {
        aiExtracting.value = false;
    }
}

function retryAiExtraction() {
    if (aiExtracting.value || lastFoldersForRetry.value.length === 0) return;
    aiError.value = '';
    aiMessage.value = '';
    // Force retry even if folders did not change.
    lastExtractionKey.value = '';
    runAiExtraction([...lastFoldersForRetry.value]);
}

function onFileRemoved(type: 'start' | 'end', data: { type: string; fileId?: number }) {
    if (data.type !== 'existing' || !data.fileId) return;

    if (type === 'start') {
        startRemoved.value.push(data.fileId);
        form.start_contract_removed_files = [...startRemoved.value];
        return;
    }

    endRemoved.value.push(data.fileId);
    form.end_contract_removed_files = [...endRemoved.value];
}

function submit() {
    if (props.mode === 'create') {
        form.post(props.actions.store || '/admin/contracts');
        return;
    }

    form.put(props.actions.update || '/admin/contracts');
}
</script>

<template>
    <Head :title="mode === 'create' ? 'Create Contract' : 'Edit Contract'" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">
                        {{ mode === 'create' ? 'Create Contract' : 'Edit Contract' }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        AI extraction is
                        <strong>{{ ai.contracts_extraction_enabled ? 'enabled' : 'disabled' }}</strong>.
                        When disabled, files are stored only.
                    </p>
                </div>
                <Link :href="actions.index">
                    <Button variant="outline">Back</Button>
                </Link>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <div class="space-y-4 rounded-md border p-4">
                    <h2 class="text-sm font-semibold">Contract Files (Upload First)</h2>

                    <div>
                        <Label class="mb-2 block">Start Rental Contract File</Label>
                        <FileUpload
                            v-model="startTempFolders"
                            :initial-files="startContractFiles || []"
                            :allowed-file-types="contractAllowedFileTypes"
                            :allow-multiple="false"
                            :max-files="1"
                            collection="start_contract"
                            theme="light"
                            width="100%"
                            @file-removed="(data: { type: string; fileId?: number }) => onFileRemoved('start', data)"
                        />
                        <InputError :message="form.errors.start_contract_temp_folders" class="mt-1" />
                    </div>

                    <div>
                        <Label class="mb-2 block">End Rental Contract File</Label>
                        <FileUpload
                            v-model="endTempFolders"
                            :initial-files="endContractFiles || []"
                            :allowed-file-types="contractAllowedFileTypes"
                            :allow-multiple="false"
                            :max-files="1"
                            collection="end_contract"
                            theme="light"
                            width="100%"
                            @file-removed="(data: { type: string; fileId?: number }) => onFileRemoved('end', data)"
                        />
                        <InputError :message="form.errors.end_contract_temp_folders" class="mt-1" />
                    </div>

                    <div v-if="aiExtracting" class="rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-blue-700">
                        Reading file and extracting data with AI...
                    </div>

                    <div v-if="aiError" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                        <p>{{ aiError }}</p>
                        <div class="mt-2">
                            <Button type="button" variant="outline" size="sm" @click="retryAiExtraction">
                                Retry AI Extraction
                            </Button>
                        </div>
                    </div>

                    <div v-if="aiMessage" class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-700">
                        {{ aiMessage }}
                    </div>

                    <div v-if="extractedFields" class="flex items-center justify-between rounded-md border bg-muted/30 p-3">
                        <p class="text-sm text-muted-foreground">AI found data. Missing fields were auto-filled.</p>
                        <Button type="button" variant="outline" size="sm" @click="applyExtractedFields(extractedFields, true)">
                            Apply To All Fields
                        </Button>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <Label for="reservation_id">Reservation</Label>
                        <select
                            id="reservation_id"
                            v-model="form.reservation_id"
                            class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2"
                        >
                            <option value="">No linked reservation</option>
                            <option
                                v-for="reservation in reservationOptions"
                                :key="reservation.id"
                                :value="reservation.id"
                                :disabled="reservation.has_contract && reservation.id !== form.reservation_id"
                            >
                                {{ reservation.label }}{{ reservation.has_contract ? ' (has contract)' : '' }}
                            </option>
                        </select>
                        <InputError :message="form.errors.reservation_id" class="mt-1" />
                    </div>

                    <div>
                        <Label for="contract_number">Contract Number</Label>
                        <Input id="contract_number" v-model="form.contract_number" />
                        <InputError :message="form.errors.contract_number" class="mt-1" />
                    </div>

                    <div>
                        <Label for="status">Status</Label>
                        <select
                            id="status"
                            v-model="form.status"
                            class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2"
                        >
                            <option value="draft">draft</option>
                            <option value="active">active</option>
                            <option value="completed">completed</option>
                            <option value="cancelled">cancelled</option>
                        </select>
                        <InputError :message="form.errors.status" class="mt-1" />
                    </div>

                    <div>
                        <Label for="contract_date">Contract Date</Label>
                        <Input id="contract_date" v-model="form.contract_date" type="date" />
                        <InputError :message="form.errors.contract_date" class="mt-1" />
                    </div>

                    <div>
                        <Label for="renter_name">Renter Name</Label>
                        <Input id="renter_name" v-model="form.renter_name" />
                        <InputError :message="form.errors.renter_name" class="mt-1" />
                    </div>

                    <div>
                        <Label for="renter_id_number">Renter ID Number</Label>
                        <Input id="renter_id_number" v-model="form.renter_id_number" />
                        <InputError :message="form.errors.renter_id_number" class="mt-1" />
                    </div>

                    <div>
                        <Label for="renter_phone">Renter Phone</Label>
                        <Input id="renter_phone" v-model="form.renter_phone" />
                        <InputError :message="form.errors.renter_phone" class="mt-1" />
                    </div>

                    <div>
                        <Label for="car_details">Car Details</Label>
                        <Input id="car_details" v-model="form.car_details" />
                        <InputError :message="form.errors.car_details" class="mt-1" />
                    </div>

                    <div>
                        <Label for="plate_number">Plate Number</Label>
                        <Input id="plate_number" v-model="form.plate_number" />
                        <InputError :message="form.errors.plate_number" class="mt-1" />
                    </div>

                    <div>
                        <Label for="start_date">Rental Start Date</Label>
                        <Input id="start_date" v-model="form.start_date" type="date" />
                        <InputError :message="form.errors.start_date" class="mt-1" />
                    </div>

                    <div>
                        <Label for="end_date">Rental End Date</Label>
                        <Input id="end_date" v-model="form.end_date" type="date" />
                        <InputError :message="form.errors.end_date" class="mt-1" />
                    </div>

                    <div>
                        <Label for="total_amount">Total Amount</Label>
                        <Input id="total_amount" v-model="form.total_amount" type="number" min="0" step="0.01" />
                        <InputError :message="form.errors.total_amount" class="mt-1" />
                    </div>

                    <div>
                        <Label for="currency">Currency (3 letters)</Label>
                        <Input id="currency" v-model="form.currency" maxlength="3" />
                        <InputError :message="form.errors.currency" class="mt-1" />
                    </div>

                    <div class="md:col-span-2">
                        <Label for="notes">Notes</Label>
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="3"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2"
                        />
                        <InputError :message="form.errors.notes" class="mt-1" />
                    </div>
                </div>

                <div class="flex gap-3">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : 'Save Contract' }}
                    </Button>
                    <Link :href="actions.index">
                        <Button type="button" variant="outline">Cancel</Button>
                    </Link>
                </div>
            </form>
        </main>
    </AdminLayout>
</template>
