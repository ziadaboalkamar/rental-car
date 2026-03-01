<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    tenant: {
        id: number;
        name: string;
        slug: string;
        stripe_account_id: string | null;
        stripe_onboarded_at: string | null;
        stripe_details_submitted: boolean;
        stripe_charges_enabled: boolean;
        stripe_payouts_enabled: boolean;
        stripe_currency: string | null;
    };
    stripe: {
        platform_configured: boolean;
        can_accept_checkout: boolean;
    };
    actions: {
        connect: string;
        refresh: string;
        login_link: string;
    };
}>();

const page = usePage<any>();
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);

function connectStripe() {
    router.post(props.actions.connect);
}

function openStripeDashboard() {
    router.post(props.actions.login_link);
}
</script>

<template>
    <Head title="Stripe Connect" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">Stripe Connect</h1>
                    <p class="text-sm text-muted-foreground">
                        Connect this tenant to Stripe so client bookings can be paid online.
                    </p>
                </div>
            </div>

            <div v-if="flashSuccess" class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
                {{ flashSuccess }}
            </div>
            <div v-if="flashError" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                {{ flashError }}
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="rounded-lg border p-5 lg:col-span-2">
                    <h2 class="mb-4 text-lg font-semibold">Connection Status</h2>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="rounded-md border p-4">
                            <div class="text-xs uppercase tracking-wide text-muted-foreground">Tenant</div>
                            <div class="mt-1 font-medium">{{ tenant.name }}</div>
                            <div class="text-sm text-muted-foreground">{{ tenant.slug }}</div>
                        </div>

                        <div class="rounded-md border p-4">
                            <div class="text-xs uppercase tracking-wide text-muted-foreground">Stripe Account ID</div>
                            <div class="mt-1 break-all font-mono text-sm">
                                {{ tenant.stripe_account_id || 'Not connected' }}
                            </div>
                        </div>

                        <div class="rounded-md border p-4">
                            <div class="text-xs uppercase tracking-wide text-muted-foreground">Charges Enabled</div>
                            <div class="mt-1 font-medium" :class="tenant.stripe_charges_enabled ? 'text-emerald-600' : 'text-amber-600'">
                                {{ tenant.stripe_charges_enabled ? 'Yes' : 'No' }}
                            </div>
                        </div>

                        <div class="rounded-md border p-4">
                            <div class="text-xs uppercase tracking-wide text-muted-foreground">Payouts Enabled</div>
                            <div class="mt-1 font-medium" :class="tenant.stripe_payouts_enabled ? 'text-emerald-600' : 'text-amber-600'">
                                {{ tenant.stripe_payouts_enabled ? 'Yes' : 'No' }}
                            </div>
                        </div>

                        <div class="rounded-md border p-4">
                            <div class="text-xs uppercase tracking-wide text-muted-foreground">Details Submitted</div>
                            <div class="mt-1 font-medium" :class="tenant.stripe_details_submitted ? 'text-emerald-600' : 'text-amber-600'">
                                {{ tenant.stripe_details_submitted ? 'Yes' : 'No' }}
                            </div>
                        </div>

                        <div class="rounded-md border p-4">
                            <div class="text-xs uppercase tracking-wide text-muted-foreground">Default Currency</div>
                            <div class="mt-1 font-medium uppercase">{{ tenant.stripe_currency || 'Not set' }}</div>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 rounded-lg border p-5">
                    <h2 class="text-lg font-semibold">Actions</h2>

                    <div class="rounded-md border p-3 text-sm">
                        <div class="font-medium">Platform Stripe</div>
                        <div :class="stripe.platform_configured ? 'text-emerald-600' : 'text-red-600'">
                            {{ stripe.platform_configured ? 'Configured' : 'Not configured' }}
                        </div>
                    </div>

                    <div class="rounded-md border p-3 text-sm">
                        <div class="font-medium">Checkout Ready</div>
                        <div :class="stripe.can_accept_checkout ? 'text-emerald-600' : 'text-amber-600'">
                            {{ stripe.can_accept_checkout ? 'Ready for booking payments' : 'Not ready yet' }}
                        </div>
                    </div>

                    <Button class="w-full" :disabled="!stripe.platform_configured" @click="connectStripe">
                        {{ tenant.stripe_account_id ? 'Continue Stripe Onboarding' : 'Connect Stripe' }}
                    </Button>

                    <a
                        :href="actions.refresh"
                        class="block w-full rounded-md border px-4 py-2 text-center text-sm"
                    >
                        Refresh Onboarding Link
                    </a>

                    <Button
                        type="button"
                        variant="outline"
                        class="w-full"
                        :disabled="!tenant.stripe_account_id"
                        @click="openStripeDashboard"
                    >
                        Open Stripe Express Dashboard
                    </Button>
                </div>
            </div>
        </main>
    </AdminLayout>
</template>
