<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    violation: {
        id: number;
        car_id: number;
        reservation_id: number | null;
        issued_to_user_id: number | null;
        violation_number: string | null;
        violation_date: string | null;
        type: string;
        amount: number;
        status: string;
        due_date: string | null;
        paid_at: string | null;
        payment_reference: string | null;
        authority: string | null;
        location: string | null;
        description: string | null;
        notes: string | null;
    } | null;
    cars: Array<{ id: number; label: string }>;
    clients: Array<{ id: number; label: string }>;
    reservations: Array<{ id: number; label: string; car_id: number | null }>;
    statuses: Array<{ value: string; label: string; color: string }>;
    indexUrl: string;
    submitUrl: string;
    method: 'post' | 'put';
}>();

const isEdit = computed(() => !!props.violation);

const form = useForm({
    car_id: props.violation?.car_id ? String(props.violation.car_id) : '',
    reservation_id: props.violation?.reservation_id ? String(props.violation.reservation_id) : '',
    issued_to_user_id: props.violation?.issued_to_user_id ? String(props.violation.issued_to_user_id) : '',
    violation_number: props.violation?.violation_number ?? '',
    violation_date: props.violation?.violation_date ?? '',
    type: props.violation?.type ?? '',
    amount: props.violation?.amount ?? '',
    status: props.violation?.status ?? 'pending',
    due_date: props.violation?.due_date ?? '',
    paid_at: props.violation?.paid_at ?? '',
    payment_reference: props.violation?.payment_reference ?? '',
    authority: props.violation?.authority ?? '',
    location: props.violation?.location ?? '',
    description: props.violation?.description ?? '',
    notes: props.violation?.notes ?? '',
});

const filteredReservations = computed(() => {
    if (!form.car_id) {
        return props.reservations;
    }

    return props.reservations.filter((item) => String(item.car_id ?? '') === form.car_id);
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
    <Head :title="isEdit ? 'Edit Car Violation' : 'Create Car Violation'" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">
                    {{ isEdit ? 'Edit Car Violation' : 'Create Car Violation' }}
                </h1>
                <Link :href="indexUrl">
                    <Button variant="outline">Back</Button>
                </Link>
            </div>

            <div class="max-w-4xl">
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
                                <option value="" disabled>Select car</option>
                                <option v-for="car in cars" :key="car.id" :value="String(car.id)">
                                    {{ car.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.car_id" />
                        </div>

                        <div class="space-y-2">
                            <Label for="reservation_id">Reservation (optional)</Label>
                            <select
                                id="reservation_id"
                                v-model="form.reservation_id"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option value="">No reservation</option>
                                <option v-for="reservation in filteredReservations" :key="reservation.id" :value="String(reservation.id)">
                                    {{ reservation.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.reservation_id" />
                        </div>

                        <div class="space-y-2">
                            <Label for="issued_to_user_id">Issued To (Client)</Label>
                            <select
                                id="issued_to_user_id"
                                v-model="form.issued_to_user_id"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option value="">Not specified</option>
                                <option v-for="client in clients" :key="client.id" :value="String(client.id)">
                                    {{ client.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.issued_to_user_id" />
                        </div>

                        <div class="space-y-2">
                            <Label for="violation_number">Violation Number</Label>
                            <Input id="violation_number" v-model="form.violation_number" placeholder="Optional unique number" />
                            <InputError :message="form.errors.violation_number" />
                        </div>

                        <div class="space-y-2">
                            <Label for="violation_date">Violation Date</Label>
                            <Input id="violation_date" v-model="form.violation_date" required type="date" />
                            <InputError :message="form.errors.violation_date" />
                        </div>

                        <div class="space-y-2">
                            <Label for="type">Type</Label>
                            <Input id="type" v-model="form.type" placeholder="Speeding, Parking, ..." required />
                            <InputError :message="form.errors.type" />
                        </div>

                        <div class="space-y-2">
                            <Label for="amount">Amount</Label>
                            <Input id="amount" v-model="form.amount" min="0" step="0.01" required type="number" />
                            <InputError :message="form.errors.amount" />
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
                            <Label for="due_date">Due Date</Label>
                            <Input id="due_date" v-model="form.due_date" type="date" />
                            <InputError :message="form.errors.due_date" />
                        </div>

                        <div class="space-y-2">
                            <Label for="paid_at">Paid At</Label>
                            <Input id="paid_at" v-model="form.paid_at" type="datetime-local" />
                            <InputError :message="form.errors.paid_at" />
                        </div>

                        <div class="space-y-2">
                            <Label for="payment_reference">Payment Reference</Label>
                            <Input id="payment_reference" v-model="form.payment_reference" />
                            <InputError :message="form.errors.payment_reference" />
                        </div>

                        <div class="space-y-2">
                            <Label for="authority">Authority</Label>
                            <Input id="authority" v-model="form.authority" placeholder="Traffic Police, Municipality..." />
                            <InputError :message="form.errors.authority" />
                        </div>

                        <div class="space-y-2">
                            <Label for="location">Location</Label>
                            <Input id="location" v-model="form.location" />
                            <InputError :message="form.errors.location" />
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <Label for="description">Description</Label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 dark:bg-input/30"
                            />
                            <InputError :message="form.errors.description" />
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <Label for="notes">Notes</Label>
                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="3"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 dark:bg-input/30"
                            />
                            <InputError :message="form.errors.notes" />
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <Button :disabled="form.processing" type="submit">
                            {{ form.processing ? 'Saving...' : isEdit ? 'Save Changes' : 'Create Violation' }}
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

