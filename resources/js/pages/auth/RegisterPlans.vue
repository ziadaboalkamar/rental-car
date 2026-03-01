<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

type BillingCycle = 'monthly' | 'yearly' | 'one_time';

interface PlanOption {
    id: number;
    name: string;
    description: string | null;
    features: string[] | null;
    monthly_price: number | string;
    monthly_price_id: string | null;
    yearly_price: number | string;
    yearly_price_id: string | null;
    one_time_price: number | string | null;
    one_time_price_id: string | null;
}

const props = defineProps<{
    plans: PlanOption[];
    selection: {
        plan_id: number | null;
        billing_cycle: BillingCycle;
    };
    urls: {
        register: string;
        plansStore: string;
        checkout: string;
    };
}>();

const form = useForm<{
    plan_id: number | null;
    billing_cycle: BillingCycle;
}>({
    plan_id: props.selection.plan_id ?? props.plans[0]?.id ?? null,
    billing_cycle: props.selection.billing_cycle ?? 'monthly',
});

const selectedPlan = computed(() => {
    return props.plans.find((plan) => plan.id === form.plan_id) ?? null;
});

const priceFor = (plan: PlanOption): number => {
    if (form.billing_cycle === 'yearly') {
        return Number(plan.yearly_price);
    }

    if (form.billing_cycle === 'one_time') {
        return Number(plan.one_time_price ?? plan.monthly_price);
    }

    return Number(plan.monthly_price);
};

const supportsCycle = (plan: PlanOption, cycle: BillingCycle): boolean => {
    const hasPriceId = (value: string | null): boolean => {
        return typeof value === 'string' && value.trim().startsWith('price_');
    };

    if (cycle === 'monthly') {
        return hasPriceId(plan.monthly_price_id);
    }

    if (cycle === 'yearly') {
        return hasPriceId(plan.yearly_price_id);
    }

    if (cycle === 'one_time') {
        return plan.one_time_price !== null && hasPriceId(plan.one_time_price_id);
    }

    return true;
};

const submit = () => {
    form.post(props.urls.plansStore);
};
</script>

<template>
    <Head title="Choose Plan" />

    <main class="min-h-screen bg-slate-50 py-10">
        <div class="mx-auto max-w-6xl px-4">
            <div class="mb-6">
                <Link :href="urls.register" class="text-sm font-medium text-slate-700 hover:underline">
                    Back to registration
                </Link>
            </div>

            <div class="mb-8">
                <p class="text-sm font-semibold text-blue-700">Step 2 of 3</p>
                <h1 class="mt-2 text-3xl font-bold text-slate-900">Choose your plan</h1>
                <p class="mt-2 text-slate-600">Select a plan before payment and tenant activation.</p>
            </div>

            <form @submit.prevent="submit">
                <div class="mb-8 rounded-2xl border bg-white p-5">
                    <p class="mb-3 text-sm font-semibold text-slate-800">Billing cycle</p>
                    <div class="flex flex-wrap gap-3">
                        <label class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm">
                            <input v-model="form.billing_cycle" type="radio" value="monthly">
                            Monthly
                        </label>
                        <label class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm">
                            <input v-model="form.billing_cycle" type="radio" value="yearly">
                            Yearly
                        </label>
                        <label class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm">
                            <input v-model="form.billing_cycle" type="radio" value="one_time">
                            One time
                        </label>
                    </div>
                    <p v-if="form.errors.billing_cycle" class="mt-2 text-sm text-red-600">{{ form.errors.billing_cycle }}</p>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <button
                        v-for="plan in plans"
                        :key="plan.id"
                        type="button"
                        class="rounded-2xl border bg-white p-5 text-left shadow-sm"
                        :class="[
                            form.plan_id === plan.id ? 'border-blue-600 ring-2 ring-blue-200' : 'border-slate-200',
                            !supportsCycle(plan, form.billing_cycle) ? 'opacity-50' : '',
                        ]"
                        @click="form.plan_id = supportsCycle(plan, form.billing_cycle) ? plan.id : form.plan_id"
                    >
                        <div class="mb-3 flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-slate-900">{{ plan.name }}</h2>
                            <span
                                v-if="form.plan_id === plan.id"
                                class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700"
                            >
                                Selected
                            </span>
                        </div>
                        <p class="mb-4 min-h-10 text-sm text-slate-600">{{ plan.description }}</p>
                        <p class="mb-4 text-3xl font-bold text-slate-900">${{ priceFor(plan).toFixed(2) }}</p>

                        <ul class="space-y-2 text-sm text-slate-700">
                            <li v-for="feature in (plan.features || [])" :key="feature">- {{ feature }}</li>
                        </ul>

                        <p
                            v-if="!supportsCycle(plan, form.billing_cycle)"
                            class="mt-4 text-xs font-medium text-amber-700"
                        >
                            This billing cycle is not available for this plan.
                        </p>
                    </button>
                </div>

                <p v-if="form.errors.plan_id" class="mt-3 text-sm text-red-600">{{ form.errors.plan_id }}</p>

                <div class="mt-8 flex items-center gap-3">
                    <Button type="submit" :disabled="form.processing || !selectedPlan">
                        {{ form.processing ? 'Saving...' : 'Continue to payment' }}
                    </Button>
                    <Link :href="urls.register" class="text-sm font-medium text-slate-700 hover:underline">Edit account details</Link>
                </div>
            </form>
        </div>
    </main>
</template>
