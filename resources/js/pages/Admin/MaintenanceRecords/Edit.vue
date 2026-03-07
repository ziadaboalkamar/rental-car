<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    record: {
        id: number;
        car_id: number;
        maintenance_type_id: number | null;
        status: string;
        scheduled_date: string | null;
        started_at: string | null;
        completed_at: string | null;
        cost: number | null;
        odometer: number | null;
        workshop_name: string | null;
        notes: string | null;
    } | null;
    cars: Array<{ id: number; label: string }>;
    maintenanceTypes: Array<{ id: number; name: string }>;
    statuses: Array<{ value: string; label: string; color: string }>;
    indexUrl: string;
    submitUrl: string;
    method: 'post' | 'put';
}>();

const isEdit = computed(() => !!props.record);

const form = useForm({
    car_id: props.record?.car_id ? String(props.record.car_id) : '',
    maintenance_type_id: props.record?.maintenance_type_id ? String(props.record.maintenance_type_id) : '',
    status: props.record?.status ?? 'scheduled',
    scheduled_date: props.record?.scheduled_date ?? '',
    started_at: props.record?.started_at ?? '',
    completed_at: props.record?.completed_at ?? '',
    cost: props.record?.cost ?? '',
    odometer: props.record?.odometer ?? '',
    workshop_name: props.record?.workshop_name ?? '',
    notes: props.record?.notes ?? '',
});

function submit() {
    if (props.method === 'put') {
        form.put(props.submitUrl, { preserveScroll: true });
        return;
    }

    form.post(props.submitUrl, { preserveScroll: true });
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Maintenance Record' : 'Create Maintenance Record'" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">
                    {{ isEdit ? 'Edit Maintenance Record' : 'Create Maintenance Record' }}
                </h1>
                <Link :href="indexUrl">
                    <Button variant="outline">Back</Button>
                </Link>
            </div>

            <div class="max-w-3xl">
                <form class="space-y-6" @submit.prevent="submit">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="car_id">Car</Label>
                            <select
                                id="car_id"
                                v-model="form.car_id"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                required
                            >
                                <option value="" disabled>Select a car</option>
                                <option v-for="car in cars" :key="car.id" :value="String(car.id)">
                                    {{ car.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.car_id" />
                        </div>

                        <div class="space-y-2">
                            <Label for="maintenance_type_id">Maintenance Type</Label>
                            <select
                                id="maintenance_type_id"
                                v-model="form.maintenance_type_id"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option value="">Select type</option>
                                <option v-for="type in maintenanceTypes" :key="type.id" :value="String(type.id)">
                                    {{ type.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors.maintenance_type_id" />
                        </div>

                        <div class="space-y-2">
                            <Label for="status">Status</Label>
                            <select
                                id="status"
                                v-model="form.status"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                required
                            >
                                <option v-for="statusItem in statuses" :key="statusItem.value" :value="statusItem.value">
                                    {{ statusItem.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.status" />
                        </div>

                        <div class="space-y-2">
                            <Label for="scheduled_date">Scheduled Date</Label>
                            <Input id="scheduled_date" v-model="form.scheduled_date" type="date" />
                            <InputError :message="form.errors.scheduled_date" />
                        </div>

                        <div class="space-y-2">
                            <Label for="started_at">Started At</Label>
                            <Input id="started_at" v-model="form.started_at" type="datetime-local" />
                            <InputError :message="form.errors.started_at" />
                        </div>

                        <div class="space-y-2">
                            <Label for="completed_at">Completed At</Label>
                            <Input id="completed_at" v-model="form.completed_at" type="datetime-local" />
                            <InputError :message="form.errors.completed_at" />
                        </div>

                        <div class="space-y-2">
                            <Label for="cost">Cost</Label>
                            <Input id="cost" v-model="form.cost" min="0" step="0.01" type="number" />
                            <InputError :message="form.errors.cost" />
                        </div>

                        <div class="space-y-2">
                            <Label for="odometer">Odometer</Label>
                            <Input id="odometer" v-model="form.odometer" min="0" step="1" type="number" />
                            <InputError :message="form.errors.odometer" />
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <Label for="workshop_name">Workshop Name</Label>
                            <Input id="workshop_name" v-model="form.workshop_name" />
                            <InputError :message="form.errors.workshop_name" />
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <Label for="notes">Notes</Label>
                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="4"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 dark:bg-input/30"
                            />
                            <InputError :message="form.errors.notes" />
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <Button :disabled="form.processing" type="submit">
                            {{ form.processing ? 'Saving...' : isEdit ? 'Save Changes' : 'Create Record' }}
                        </Button>
                        <Link :href="indexUrl">
                            <Button type="button" variant="outline">Cancel</Button>
                        </Link>
                    </div>
                </form>
            </div>
        </main>
    </AdminLayout>
</template>

