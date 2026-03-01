<script setup lang="ts">
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { type Plan } from '@/types';

const props = defineProps<{
    plans: Plan[];
}>();

const form = useForm({
    plan_id: '',
    name: '',
    code: '',
    type: 'percentage',
    value: 0,
    start_date: new Date().toISOString().split('T')[0],
    end_date: '',
    is_active: true,
});

const submit = () => {
    form.post('/superadmin/discounts', { preserveScroll: true });
};
</script>

<template>
    <Head title="Create Discount" />
    <SuperAdminLayout>
        <main class="flex-1 p-8 space-y-6">
            <div class="flex items-center gap-4">
                <Link href="/superadmin/discounts">
                    <Button variant="outline">← Back</Button>
                </Link>
                <h1 class="text-2xl font-semibold">Create Subscription Discount</h1>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Discount Details</CardTitle>
                                <CardDescription>Basic information about the discount.</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="plan_id">Target Plan *</Label>
                                    <select
                                        id="plan_id"
                                        v-model="form.plan_id"
                                        class="flex h-9 w-full items-center justify-between rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary disabled:cursor-not-allowed disabled:opacity-50"
                                        required
                                    >
                                        <option value="" disabled>Select a plan</option>
                                        <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                                            {{ plan.name }}
                                        </option>
                                    </select>
                                    <div v-if="form.errors.plan_id" class="text-sm text-red-600">{{ form.errors.plan_id }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label for="name">Discount Name *</Label>
                                    <Input id="name" v-model="form.name" required placeholder="e.g. Summer Special" />
                                    <div v-if="form.errors.name" class="text-sm text-red-600">{{ form.errors.name }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label for="code">Discount Code (Optional)</Label>
                                    <Input id="code" v-model="form.code" placeholder="e.g. SUMMER50" />
                                    <div v-if="form.errors.code" class="text-sm text-red-600">{{ form.errors.code }}</div>
                                </div>
                                <div class="flex items-center justify-between space-x-2 py-2">
                                    <div class="space-y-0.5">
                                        <Label for="is_active">Active Status</Label>
                                        <p class="text-xs text-muted-foreground">Whether this discount is currently active.</p>
                                    </div>
                                    <Switch
                                        id="is_active"
                                        :checked="form.is_active"
                                        @update:checked="(val: boolean) => form.is_active = val"
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div class="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Discount Configuration</CardTitle>
                                <CardDescription>Set the discount value and type.</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="type">Discount Type *</Label>
                                    <select
                                        id="type"
                                        v-model="form.type"
                                        class="flex h-9 w-full items-center justify-between rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary disabled:cursor-not-allowed disabled:opacity-50"
                                        required
                                    >
                                        <option value="percentage">Percentage (%)</option>
                                        <option value="fixed">Fixed Amount ($)</option>
                                    </select>
                                    <div v-if="form.errors.type" class="text-sm text-red-600">{{ form.errors.type }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label for="value">Value *</Label>
                                    <Input id="value" v-model.number="form.value" type="number" step="0.01" required />
                                    <div v-if="form.errors.value" class="text-sm text-red-600">{{ form.errors.value }}</div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Validity Period</CardTitle>
                                <CardDescription>Define when the discount is applicable.</CardDescription>
                                </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="start_date">Start Date *</Label>
                                    <Input id="start_date" v-model="form.start_date" type="date" required />
                                    <div v-if="form.errors.start_date" class="text-sm text-red-600">{{ form.errors.start_date }}</div>
                                </div>
                                <div class="space-y-2">
                                    <Label for="end_date">End Date *</Label>
                                    <Input id="end_date" v-model="form.end_date" type="date" required />
                                    <div v-if="form.errors.end_date" class="text-sm text-red-600">{{ form.errors.end_date }}</div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <div class="flex gap-2">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Creating...' : 'Create Discount' }}
                    </Button>
                    <Link href="/superadmin/discounts">
                        <Button type="button" variant="outline">Cancel</Button>
                    </Link>
                </div>
            </form>
        </main>
    </SuperAdminLayout>
</template>
