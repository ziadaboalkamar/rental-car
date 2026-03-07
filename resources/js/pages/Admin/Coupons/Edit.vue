<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    coupon: {
        id: number;
        car_id: number | null;
        name: string;
        code: string;
        description: string | null;
        type: string;
        value: number;
        max_discount_amount: number | null;
        min_total_amount: number | null;
        min_days: number | null;
        starts_at: string | null;
        ends_at: string | null;
        usage_limit: number | null;
        is_active: boolean;
    } | null;
    cars: Array<{ id: number; label: string }>;
    types: Array<{ value: string; label: string }>;
    indexUrl: string;
    submitUrl: string;
    method: 'post' | 'put';
}>();

const isEdit = computed(() => !!props.coupon);

const form = useForm({
    car_id: props.coupon?.car_id ?? '',
    name: props.coupon?.name ?? '',
    code: props.coupon?.code ?? '',
    description: props.coupon?.description ?? '',
    type: props.coupon?.type ?? 'percentage',
    value: props.coupon?.value ?? '',
    max_discount_amount: props.coupon?.max_discount_amount ?? '',
    min_total_amount: props.coupon?.min_total_amount ?? '',
    min_days: props.coupon?.min_days ?? '',
    starts_at: props.coupon?.starts_at ?? '',
    ends_at: props.coupon?.ends_at ?? '',
    usage_limit: props.coupon?.usage_limit ?? '',
    is_active: props.coupon?.is_active ?? true,
});

function submit() {
    if (props.method === 'put') {
        form.put(props.submitUrl);
        return;
    }

    form.post(props.submitUrl);
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Coupon' : 'Create Coupon'" />
    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">{{ isEdit ? 'Edit Coupon' : 'Create Coupon' }}</h1>
                <Link :href="indexUrl">
                    <Button variant="outline">Back</Button>
                </Link>
            </div>

            <form class="space-y-6 rounded-lg border bg-card p-6" @submit.prevent="submit">
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <Label for="name">Name</Label>
                        <Input id="name" v-model="form.name" />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>

                    <div>
                        <Label for="code">Code</Label>
                        <Input id="code" v-model="form.code" placeholder="SAVE10" />
                        <InputError :message="form.errors.code" class="mt-1" />
                    </div>

                    <div>
                        <Label for="car_id">Car Scope</Label>
                        <select id="car_id" v-model="form.car_id" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm">
                            <option value="">All cars</option>
                            <option v-for="car in cars" :key="car.id" :value="car.id">{{ car.label }}</option>
                        </select>
                        <InputError :message="form.errors.car_id" class="mt-1" />
                    </div>

                    <div>
                        <Label for="type">Type</Label>
                        <select id="type" v-model="form.type" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm">
                            <option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option>
                        </select>
                        <InputError :message="form.errors.type" class="mt-1" />
                    </div>

                    <div>
                        <Label for="value">Value</Label>
                        <Input id="value" v-model="form.value" type="number" step="0.01" min="0.01" />
                        <InputError :message="form.errors.value" class="mt-1" />
                    </div>

                    <div>
                        <Label for="max_discount_amount">Max Discount Amount (optional)</Label>
                        <Input id="max_discount_amount" v-model="form.max_discount_amount" type="number" step="0.01" min="0.01" />
                        <InputError :message="form.errors.max_discount_amount" class="mt-1" />
                    </div>

                    <div>
                        <Label for="min_total_amount">Min Order Amount (optional)</Label>
                        <Input id="min_total_amount" v-model="form.min_total_amount" type="number" step="0.01" min="0" />
                        <InputError :message="form.errors.min_total_amount" class="mt-1" />
                    </div>

                    <div>
                        <Label for="min_days">Min Rental Days (optional)</Label>
                        <Input id="min_days" v-model="form.min_days" type="number" min="1" />
                        <InputError :message="form.errors.min_days" class="mt-1" />
                    </div>

                    <div>
                        <Label for="starts_at">Starts At (optional)</Label>
                        <Input id="starts_at" v-model="form.starts_at" type="datetime-local" />
                        <InputError :message="form.errors.starts_at" class="mt-1" />
                    </div>

                    <div>
                        <Label for="ends_at">Ends At (optional)</Label>
                        <Input id="ends_at" v-model="form.ends_at" type="datetime-local" />
                        <InputError :message="form.errors.ends_at" class="mt-1" />
                    </div>

                    <div>
                        <Label for="usage_limit">Usage Limit (optional)</Label>
                        <Input id="usage_limit" v-model="form.usage_limit" type="number" min="1" />
                        <InputError :message="form.errors.usage_limit" class="mt-1" />
                    </div>

                    <div class="flex items-center gap-2 pt-7">
                        <input id="is_active" v-model="form.is_active" type="checkbox" />
                        <Label for="is_active">Active</Label>
                        <InputError :message="form.errors.is_active" class="mt-1" />
                    </div>
                </div>

                <div>
                    <Label for="description">Description (optional)</Label>
                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="4"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                    <InputError :message="form.errors.description" class="mt-1" />
                </div>

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : isEdit ? 'Save Changes' : 'Create Coupon' }}
                    </Button>
                    <Link :href="indexUrl">
                        <Button type="button" variant="outline">Cancel</Button>
                    </Link>
                </div>
            </form>
        </main>
    </AdminLayout>
</template>

