<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type PlatformProvider = {
    id: number;
    code: 'stripe' | 'myfatoorah';
    name: string;
    description: string | null;
    is_enabled: boolean;
    mode: 'test' | 'live';
    config: Record<string, any>;
    supported_countries: string[];
    supported_currencies: string[];
};

const props = defineProps<{
    tenant: {
        id: number;
        name: string;
        slug: string;
        settings: {
            default_provider: string | null;
            stripe: { enabled: boolean };
            myfatoorah: {
                enabled: boolean;
                country: string;
                api_token: string;
                api_base_url: string;
                payment_method_id: string;
                callback_url: string;
                error_url: string;
                webhook_secret: string;
            };
        };
        stripe_connect: {
            stripe_account_id: string | null;
            stripe_charges_enabled: boolean;
            stripe_payouts_enabled: boolean;
            stripe_details_submitted: boolean;
            stripe_currency: string | null;
        };
    };
    platformProviders: PlatformProvider[];
    actions: {
        update: string;
        stripe_connect: string;
    };
}>();

const page = usePage<any>();
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);

const providerMap = computed<Record<string, PlatformProvider>>(() =>
    Object.fromEntries((props.platformProviders || []).map((provider) => [provider.code, provider])),
);

const stripePlatform = computed(() => providerMap.value.stripe ?? null);
const myfatoorahPlatform = computed(() => providerMap.value.myfatoorah ?? null);
const showMyFatoorahAdvanced = ref(false);

const myfatoorahPlatformMode = computed<'test' | 'live'>(() => myfatoorahPlatform.value?.mode === 'live' ? 'live' : 'test');
const myfatoorahAutoBaseUrl = computed(() => myfatoorahPlatformMode.value === 'live'
    ? 'https://api.myfatoorah.com'
    : 'https://apitest.myfatoorah.com');

const form = useForm({
    default_provider: props.tenant.settings?.default_provider ?? null,
    stripe: {
        enabled: !!props.tenant.settings?.stripe?.enabled,
    },
    myfatoorah: {
        enabled: !!props.tenant.settings?.myfatoorah?.enabled,
        country: props.tenant.settings?.myfatoorah?.country ?? 'KW',
        api_token: props.tenant.settings?.myfatoorah?.api_token ?? '',
        api_base_url: props.tenant.settings?.myfatoorah?.api_base_url ?? '',
        payment_method_id: props.tenant.settings?.myfatoorah?.payment_method_id ?? '',
        callback_url: props.tenant.settings?.myfatoorah?.callback_url ?? '',
        error_url: props.tenant.settings?.myfatoorah?.error_url ?? '',
        webhook_secret: props.tenant.settings?.myfatoorah?.webhook_secret ?? '',
    },
});

function submit() {
    if (!form.myfatoorah.api_base_url?.trim()) {
        form.myfatoorah.api_base_url = myfatoorahAutoBaseUrl.value;
    }

    form.put(props.actions.update, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Payment Providers" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">Payment Providers</h1>
                    <p class="text-sm text-muted-foreground">
                        Manage tenant booking payment providers. Only providers approved by Super Admin can be enabled.
                    </p>
                </div>
                <Button :disabled="form.processing" @click="submit">
                    {{ form.processing ? 'Saving...' : 'Save Changes' }}
                </Button>
            </div>

            <div v-if="flashSuccess" class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
                {{ flashSuccess }}
            </div>
            <div v-if="flashError" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                {{ flashError }}
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <section class="rounded-lg border p-5">
                    <h2 class="mb-4 text-lg font-semibold">Provider Availability (Platform Approval)</h2>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-md border p-4">
                            <div class="flex items-center justify-between gap-2">
                                <div>
                                    <div class="font-medium">Stripe</div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ stripePlatform?.description || 'Stripe Connect / platform-managed Stripe payments' }}
                                    </div>
                                </div>
                                <span
                                    class="rounded px-2 py-1 text-xs"
                                    :class="stripePlatform?.is_enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'"
                                >
                                    {{ stripePlatform?.is_enabled ? 'Approved by Super Admin' : 'Disabled by Super Admin' }}
                                </span>
                            </div>
                            <div class="mt-2 text-xs text-muted-foreground">
                                Mode: {{ stripePlatform?.mode || '-' }}
                            </div>
                        </div>

                        <div class="rounded-md border p-4">
                            <div class="flex items-center justify-between gap-2">
                                <div>
                                    <div class="font-medium">MyFatoorah</div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ myfatoorahPlatform?.description || 'Hosted checkout for GCC/MENA' }}
                                    </div>
                                </div>
                                <span
                                    class="rounded px-2 py-1 text-xs"
                                    :class="myfatoorahPlatform?.is_enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'"
                                >
                                    {{ myfatoorahPlatform?.is_enabled ? 'Approved by Super Admin' : 'Disabled by Super Admin' }}
                                </span>
                            </div>
                            <div class="mt-2 text-xs text-muted-foreground">
                                Mode: {{ myfatoorahPlatform?.mode || '-' }}
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border p-5">
                    <h2 class="mb-4 text-lg font-semibold">Default Booking Provider</h2>
                    <div class="space-y-2">
                        <Label for="default_provider">Default Provider</Label>
                        <select
                            id="default_provider"
                            v-model="form.default_provider"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm md:max-w-md"
                        >
                            <option :value="null">None (manual / fallback)</option>
                            <option v-if="stripePlatform?.is_enabled" value="stripe">Stripe</option>
                            <option v-if="myfatoorahPlatform?.is_enabled" value="myfatoorah">MyFatoorah</option>
                        </select>
                        <p class="text-xs text-muted-foreground">
                            This will be used by tenant booking checkout when multiple tenant providers are enabled.
                        </p>
                        <p v-if="form.errors.default_provider" class="text-sm text-red-600">{{ form.errors.default_provider }}</p>
                    </div>
                </section>

                <section class="rounded-lg border p-5">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold">Stripe (Tenant)</h2>
                            <p class="text-sm text-muted-foreground">
                                Uses Stripe Connect. Manage onboarding and account status in the Stripe Connect page.
                            </p>
                        </div>
                        <Link :href="actions.stripe_connect">
                            <Button type="button" variant="outline">Open Stripe Connect</Button>
                        </Link>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="flex items-center gap-2 rounded-md border p-3 text-sm">
                            <input
                                v-model="form.stripe.enabled"
                                type="checkbox"
                                :disabled="!stripePlatform?.is_enabled"
                            />
                            <span>Enable Stripe for tenant bookings</span>
                        </label>

                        <div class="rounded-md border p-3 text-sm">
                            <div class="font-medium">Stripe Connect Status</div>
                            <div class="mt-1 text-xs text-muted-foreground break-all">
                                Account ID: {{ tenant.stripe_connect.stripe_account_id || 'Not connected' }}
                            </div>
                            <div class="mt-2 grid grid-cols-2 gap-2 text-xs">
                                <span :class="tenant.stripe_connect.stripe_charges_enabled ? 'text-emerald-600' : 'text-amber-600'">
                                    Charges: {{ tenant.stripe_connect.stripe_charges_enabled ? 'Enabled' : 'Disabled' }}
                                </span>
                                <span :class="tenant.stripe_connect.stripe_payouts_enabled ? 'text-emerald-600' : 'text-amber-600'">
                                    Payouts: {{ tenant.stripe_connect.stripe_payouts_enabled ? 'Enabled' : 'Disabled' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <p v-if="!stripePlatform?.is_enabled" class="mt-3 text-sm text-amber-700">
                        Stripe is currently disabled by Super Admin.
                    </p>
                </section>

                <section class="rounded-lg border p-5">
                    <div class="mb-4">
                        <h2 class="text-lg font-semibold">MyFatoorah (Tenant)</h2>
                        <p class="text-sm text-muted-foreground">
                            Store tenant MyFatoorah credentials for booking payments (separate from SaaS subscription payments).
                        </p>
                    </div>

                    <div class="space-y-4">
                        <label class="flex items-center gap-2 rounded-md border p-3 text-sm">
                            <input
                                v-model="form.myfatoorah.enabled"
                                type="checkbox"
                                :disabled="!myfatoorahPlatform?.is_enabled"
                            />
                            <span>Enable MyFatoorah for tenant bookings</span>
                        </label>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="mf_country">Country</Label>
                                <Input id="mf_country" v-model="form.myfatoorah.country" placeholder="KW" />
                                <p v-if="form.errors['myfatoorah.country']" class="text-sm text-red-600">{{ form.errors['myfatoorah.country'] }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label>Environment (from Super Admin)</Label>
                                <div class="h-10 w-full rounded-md border border-input bg-muted/30 px-3 py-2 text-sm flex items-center justify-between">
                                    <span>{{ myfatoorahPlatformMode === 'live' ? 'Live' : 'Test' }}</span>
                                    <span class="text-xs text-muted-foreground">Provider Mode</span>
                                </div>
                                <p class="text-xs text-muted-foreground">Tenant uses the platform MyFatoorah mode selected by Super Admin.</p>
                            </div>

                            <div class="space-y-2 md:col-span-2">
                                <Label for="mf_api_token">API Token</Label>
                                <Input id="mf_api_token" v-model="form.myfatoorah.api_token" type="password" placeholder="MyFatoorah token" />
                                <p v-if="form.errors['myfatoorah.api_token']" class="text-sm text-red-600">{{ form.errors['myfatoorah.api_token'] }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="mf_payment_method_id">Payment Method ID (Required for current booking flow)</Label>
                                <Input id="mf_payment_method_id" v-model="form.myfatoorah.payment_method_id" placeholder="2" />
                                <p class="text-xs text-muted-foreground">
                                    Use a valid MyFatoorah method ID (example: Visa/Mastercard). We can remove this later when booking methods are loaded dynamically.
                                </p>
                                <p v-if="form.errors['myfatoorah.payment_method_id']" class="text-sm text-red-600">{{ form.errors['myfatoorah.payment_method_id'] }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="mf_webhook_secret">Webhook Secret (Optional)</Label>
                                <Input id="mf_webhook_secret" v-model="form.myfatoorah.webhook_secret" type="password" placeholder="" />
                                <p v-if="form.errors['myfatoorah.webhook_secret']" class="text-sm text-red-600">{{ form.errors['myfatoorah.webhook_secret'] }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label>API Base URL (Auto)</Label>
                                <div class="h-10 w-full rounded-md border border-input bg-muted/30 px-3 py-2 text-sm font-mono">
                                    {{ myfatoorahAutoBaseUrl }}
                                </div>
                                <p class="text-xs text-muted-foreground">Auto-selected from Super Admin mode (Test/Live).</p>
                                <p v-if="form.errors['myfatoorah.api_base_url']" class="text-sm text-red-600">{{ form.errors['myfatoorah.api_base_url'] }}</p>
                            </div>
                        </div>

                        <div class="rounded-md border bg-muted/20 p-3 space-y-3">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="text-sm font-medium">Advanced MyFatoorah Options</div>
                                    <p class="text-xs text-muted-foreground">Use only if you need overrides or a fixed default method.</p>
                                </div>
                                <Button type="button" variant="outline" size="sm" @click="showMyFatoorahAdvanced = !showMyFatoorahAdvanced">
                                    {{ showMyFatoorahAdvanced ? 'Hide Advanced' : 'Show Advanced' }}
                                </Button>
                            </div>

                            <div v-if="showMyFatoorahAdvanced" class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="mf_api_base_url">API Base URL (Override)</Label>
                                    <Input id="mf_api_base_url" v-model="form.myfatoorah.api_base_url" placeholder="Auto from mode" />
                                    <p class="text-xs text-muted-foreground">Leave empty to use the automatic URL shown above.</p>
                                    <p v-if="form.errors['myfatoorah.api_base_url']" class="text-sm text-red-600">{{ form.errors['myfatoorah.api_base_url'] }}</p>
                                </div>

                                <div class="space-y-2 md:col-span-2">
                                    <Label for="mf_callback_url">Callback URL (Optional Override)</Label>
                                    <Input id="mf_callback_url" v-model="form.myfatoorah.callback_url" placeholder="Auto-generated by booking route" />
                                    <p v-if="form.errors['myfatoorah.callback_url']" class="text-sm text-red-600">{{ form.errors['myfatoorah.callback_url'] }}</p>
                                </div>

                                <div class="space-y-2 md:col-span-2">
                                    <Label for="mf_error_url">Error URL (Optional Override)</Label>
                                    <Input id="mf_error_url" v-model="form.myfatoorah.error_url" placeholder="Auto-generated by booking route" />
                                    <p v-if="form.errors['myfatoorah.error_url']" class="text-sm text-red-600">{{ form.errors['myfatoorah.error_url'] }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-md border border-dashed p-3 text-xs text-muted-foreground">
                            Notes:
                            <div>1. Super Admin must enable MyFatoorah in platform Payment Providers first.</div>
                            <div>2. Use the correct token for the correct environment (Test/Live) configured by Super Admin.</div>
                            <div>3. This page currently stores values inside tenant `settings` JSON.</div>
                        </div>
                    </div>
                </section>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : 'Save Changes' }}
                    </Button>
                </div>
            </form>
        </main>
    </AdminLayout>
</template>
