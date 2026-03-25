<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
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
const { t } = useTrans();
const pageTitle = computed(() =>
    isEdit.value
        ? t('dashboard.admin.car_violations.edit.head_title_edit')
        : t('dashboard.admin.car_violations.edit.head_title_create'),
);

const form = useForm({
    car_id: props.violation?.car_id ? String(props.violation.car_id) : '',
    reservation_id: props.violation?.reservation_id
        ? String(props.violation.reservation_id)
        : '',
    issued_to_user_id: props.violation?.issued_to_user_id
        ? String(props.violation.issued_to_user_id)
        : '',
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

    return props.reservations.filter(
        (item) => String(item.car_id ?? '') === form.car_id,
    );
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
    <Head :title="pageTitle" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">{{ pageTitle }}</h1>
                <Link :href="indexUrl">
                    <Button variant="outline">{{
                        t('dashboard.admin.common.back')
                    }}</Button>
                </Link>
            </div>

            <div class="max-w-4xl">
                <form class="space-y-6" @submit.prevent="submit">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="car_id">{{
                                t('dashboard.admin.car_violations.edit.fields.car')
                            }}</Label>
                            <select
                                id="car_id"
                                v-model="form.car_id"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                required
                            >
                                <option value="" disabled>
                                    {{
                                        t(
                                            'dashboard.admin.car_violations.edit.select_car',
                                        )
                                    }}
                                </option>
                                <option
                                    v-for="car in cars"
                                    :key="car.id"
                                    :value="String(car.id)"
                                >
                                    {{ car.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.car_id" />
                        </div>

                        <div class="space-y-2">
                            <Label for="reservation_id">{{
                                t(
                                    'dashboard.admin.car_violations.edit.fields.reservation_optional',
                                )
                            }}</Label>
                            <select
                                id="reservation_id"
                                v-model="form.reservation_id"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option value="">
                                    {{
                                        t(
                                            'dashboard.admin.car_violations.edit.no_reservation',
                                        )
                                    }}
                                </option>
                                <option
                                    v-for="reservation in filteredReservations"
                                    :key="reservation.id"
                                    :value="String(reservation.id)"
                                >
                                    {{ reservation.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.reservation_id" />
                        </div>

                        <div class="space-y-2">
                            <Label for="issued_to_user_id">{{
                                t(
                                    'dashboard.admin.car_violations.edit.fields.issued_to_client',
                                )
                            }}</Label>
                            <select
                                id="issued_to_user_id"
                                v-model="form.issued_to_user_id"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option value="">
                                    {{
                                        t(
                                            'dashboard.admin.car_violations.edit.not_specified',
                                        )
                                    }}
                                </option>
                                <option
                                    v-for="client in clients"
                                    :key="client.id"
                                    :value="String(client.id)"
                                >
                                    {{ client.label }}
                                </option>
                            </select>
                            <InputError
                                :message="form.errors.issued_to_user_id"
                            />
                        </div>

                        <div class="space-y-2">
                            <Label for="violation_number">{{
                                t(
                                    'dashboard.admin.car_violations.edit.fields.violation_number',
                                )
                            }}</Label>
                            <Input
                                id="violation_number"
                                v-model="form.violation_number"
                                :placeholder="
                                    t(
                                        'dashboard.admin.car_violations.edit.placeholders.unique_number',
                                    )
                                "
                            />
                            <InputError
                                :message="form.errors.violation_number"
                            />
                        </div>

                        <div class="space-y-2">
                            <Label for="violation_date">{{
                                t(
                                    'dashboard.admin.car_violations.edit.fields.violation_date',
                                )
                            }}</Label>
                            <Input
                                id="violation_date"
                                v-model="form.violation_date"
                                required
                                type="date"
                            />
                            <InputError :message="form.errors.violation_date" />
                        </div>

                        <div class="space-y-2">
                            <Label for="type">{{
                                t('dashboard.admin.car_violations.edit.fields.type')
                            }}</Label>
                            <Input
                                id="type"
                                v-model="form.type"
                                :placeholder="
                                    t(
                                        'dashboard.admin.car_violations.edit.placeholders.type',
                                    )
                                "
                                required
                            />
                            <InputError :message="form.errors.type" />
                        </div>

                        <div class="space-y-2">
                            <Label for="amount">{{
                                t(
                                    'dashboard.admin.car_violations.edit.fields.amount',
                                )
                            }}</Label>
                            <Input
                                id="amount"
                                v-model="form.amount"
                                min="0"
                                step="0.01"
                                required
                                type="number"
                            />
                            <InputError :message="form.errors.amount" />
                        </div>

                        <div class="space-y-2">
                            <Label for="status">{{
                                t(
                                    'dashboard.admin.car_violations.edit.fields.status',
                                )
                            }}</Label>
                            <select
                                id="status"
                                v-model="form.status"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                required
                            >
                                <option
                                    v-for="statusItem in statuses"
                                    :key="statusItem.value"
                                    :value="statusItem.value"
                                >
                                    {{ statusItem.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.status" />
                        </div>

                        <div class="space-y-2">
                            <Label for="due_date">{{
                                t(
                                    'dashboard.admin.car_violations.edit.fields.due_date',
                                )
                            }}</Label>
                            <Input id="due_date" v-model="form.due_date" type="date" />
                            <InputError :message="form.errors.due_date" />
                        </div>

                        <div class="space-y-2">
                            <Label for="paid_at">{{
                                t(
                                    'dashboard.admin.car_violations.edit.fields.paid_at',
                                )
                            }}</Label>
                            <Input
                                id="paid_at"
                                v-model="form.paid_at"
                                type="datetime-local"
                            />
                            <InputError :message="form.errors.paid_at" />
                        </div>

                        <div class="space-y-2">
                            <Label for="payment_reference">{{
                                t(
                                    'dashboard.admin.car_violations.edit.fields.payment_reference',
                                )
                            }}</Label>
                            <Input
                                id="payment_reference"
                                v-model="form.payment_reference"
                            />
                            <InputError
                                :message="form.errors.payment_reference"
                            />
                        </div>

                        <div class="space-y-2">
                            <Label for="authority">{{
                                t(
                                    'dashboard.admin.car_violations.edit.fields.authority',
                                )
                            }}</Label>
                            <Input
                                id="authority"
                                v-model="form.authority"
                                :placeholder="
                                    t(
                                        'dashboard.admin.car_violations.edit.placeholders.authority',
                                    )
                                "
                            />
                            <InputError :message="form.errors.authority" />
                        </div>

                        <div class="space-y-2">
                            <Label for="location">{{
                                t(
                                    'dashboard.admin.car_violations.edit.fields.location',
                                )
                            }}</Label>
                            <Input id="location" v-model="form.location" />
                            <InputError :message="form.errors.location" />
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <Label for="description">{{
                                t(
                                    'dashboard.admin.car_violations.edit.fields.description',
                                )
                            }}</Label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 dark:bg-input/30"
                            />
                            <InputError :message="form.errors.description" />
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <Label for="notes">{{
                                t(
                                    'dashboard.admin.car_violations.edit.fields.notes',
                                )
                            }}</Label>
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
                            {{
                                form.processing
                                    ? t(
                                          'dashboard.admin.car_violations.edit.saving',
                                      )
                                    : isEdit
                                      ? t('dashboard.admin.common.save_changes')
                                      : t(
                                            'dashboard.admin.car_violations.edit.create_violation',
                                        )
                            }}
                        </Button>
                        <Link :href="indexUrl">
                            <Button type="button" variant="outline">{{
                                t('dashboard.admin.common.cancel')
                            }}</Button>
                        </Link>
                    </div>
                </form>
            </div>
        </main>
    </AdminLayout>
</template>
