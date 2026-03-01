<script setup lang="ts">
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { computed, reactive, ref, watch } from 'vue';

type Provider = {
    id: number;
    code: string;
    name: string;
    driver: string | null;
    description: string | null;
    is_enabled: boolean;
    is_default: boolean;
    supports_platform_subscriptions: boolean;
    supports_tenant_payments: boolean;
    mode: 'test' | 'live';
    config: Record<string, any>;
    supported_countries: string[];
    supported_currencies: string[];
    sort_order: number;
    last_tested_at: string | null;
    updated_at: string | null;
};

type ProviderConfigField = {
    key: string;
    label: string;
    type?: 'text' | 'password';
    placeholder?: string;
    help?: string;
    advanced?: boolean;
    readonly?: boolean;
};

const props = defineProps<{
    providers: Provider[];
}>();

const page = usePage<any>();
const search = ref('');
const selectedProviderId = ref<number | null>(props.providers[0]?.id ?? null);

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);

const providersSorted = computed(() =>
    [...props.providers].sort((a, b) => {
        if ((a.sort_order ?? 0) !== (b.sort_order ?? 0)) {
            return (a.sort_order ?? 0) - (b.sort_order ?? 0);
        }
        return a.name.localeCompare(b.name);
    }),
);

const filteredProviders = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return providersSorted.value;

    return providersSorted.value.filter((provider) =>
        [provider.name, provider.code, provider.driver ?? '', provider.description ?? '']
            .join(' ')
            .toLowerCase()
            .includes(q),
    );
});

const selectedProvider = computed(
    () => props.providers.find((provider) => provider.id === selectedProviderId.value) ?? null,
);

const form = useForm({
    name: '',
    driver: '',
    description: '',
    is_enabled: false,
    is_default: false,
    supports_platform_subscriptions: false,
    supports_tenant_payments: false,
    mode: 'test' as 'test' | 'live',
    sort_order: 0,
    config: {} as Record<string, any>,
    supported_countries: [] as string[],
    supported_currencies: [] as string[],
});

const uiState = reactive({
    countriesCsv: '',
    currenciesCsv: '',
    configJson: '{}',
    providerConfigFields: {} as Record<string, string>,
    showAdvancedJson: false,
});

const providerConfigSchemas: Record<string, ProviderConfigField[]> = {
    stripe: [
        { key: 'publishable_key', label: 'Publishable Key', placeholder: 'pk_test_...', type: 'text' },
        { key: 'secret_key', label: 'Secret Key', placeholder: 'sk_test_...', type: 'password' },
        { key: 'webhook_secret', label: 'Webhook Secret', placeholder: 'whsec_...', type: 'password' },
        { key: 'webhook_path', label: 'Webhook Path', placeholder: 'stripe/webhook', type: 'text' },
    ],
    myfatoorah: [
        { key: 'country', label: 'Country', placeholder: 'OM', type: 'text' },
        { key: 'api_token', label: 'API Token', placeholder: 'MyFatoorah token', type: 'password' },
        { key: 'webhook_secret', label: 'Webhook Secret (optional)', placeholder: '', type: 'password' },
        { key: 'payment_method_id', label: 'Default Payment Method ID (optional)', placeholder: '2', type: 'text', help: 'Use only as a fallback when payment methods cannot be loaded dynamically.', advanced: true },
        { key: 'api_base_url', label: 'API Base URL (override)', placeholder: 'https://api.myfatoorah.com', type: 'text', advanced: true },
        { key: 'callback_url', label: 'Callback URL (override)', placeholder: 'https://your-domain.com/...', type: 'text', advanced: true },
        { key: 'error_url', label: 'Error URL (override)', placeholder: 'https://your-domain.com/...', type: 'text', advanced: true },
    ],
};

const selectedProviderConfigFields = computed<ProviderConfigField[]>(() => {
    if (!selectedProvider.value) return [];
    return providerConfigSchemas[selectedProvider.value.code] ?? [];
});

const basicProviderConfigFields = computed(() => selectedProviderConfigFields.value.filter((field) => !field.advanced));
const advancedProviderConfigFields = computed(() => selectedProviderConfigFields.value.filter((field) => field.advanced));

const showAdvancedProviderFields = ref(false);

const isMyFatoorahSelected = computed(() => selectedProvider.value?.code === 'myfatoorah');

const myFatoorahDefaultApiBaseUrl = computed(() => {
    return form.mode === 'live' ? 'https://api.myfatoorah.com' : 'https://apitest.myfatoorah.com';
});

function loadProviderIntoForm(provider: Provider | null) {
    if (!provider) {
        form.reset();
        uiState.countriesCsv = '';
        uiState.currenciesCsv = '';
        uiState.configJson = '{}';
        uiState.providerConfigFields = {};
        uiState.showAdvancedJson = false;
        showAdvancedProviderFields.value = false;
        return;
    }

    form.defaults({
        name: provider.name,
        driver: provider.driver ?? '',
        description: provider.description ?? '',
        is_enabled: provider.is_enabled,
        is_default: provider.is_default,
        supports_platform_subscriptions: provider.supports_platform_subscriptions,
        supports_tenant_payments: provider.supports_tenant_payments,
        mode: provider.mode ?? 'test',
        sort_order: provider.sort_order ?? 0,
        config: provider.config ?? {},
        supported_countries: provider.supported_countries ?? [],
        supported_currencies: provider.supported_currencies ?? [],
    });
    form.reset();
    form.clearErrors();

    uiState.countriesCsv = (provider.supported_countries ?? []).join(', ');
    uiState.currenciesCsv = (provider.supported_currencies ?? []).join(', ');
    uiState.configJson = JSON.stringify(provider.config ?? {}, null, 2);
    hydrateProviderConfigInputs(provider.code, provider.config ?? {});
    uiState.showAdvancedJson = false;
    showAdvancedProviderFields.value = false;

    if (provider.code === 'myfatoorah') {
        const current = (uiState.providerConfigFields.api_base_url ?? '').trim();
        if (current === '') {
            uiState.providerConfigFields.api_base_url = provider.mode === 'live'
                ? 'https://api.myfatoorah.com'
                : 'https://apitest.myfatoorah.com';
        }
    }
}

watch(
    selectedProvider,
    (provider) => {
        loadProviderIntoForm(provider);
    },
    { immediate: true },
);

function parseCsv(value: string): string[] {
    return value
        .split(',')
        .map((item) => item.trim())
        .filter((item) => item !== '');
}

function hydrateProviderConfigInputs(providerCode: string, config: Record<string, any>) {
    const fields = providerConfigSchemas[providerCode] ?? [];
    const nextState: Record<string, string> = {};

    for (const field of fields) {
        const value = config[field.key];
        nextState[field.key] = value === null || value === undefined ? '' : String(value);
    }

    uiState.providerConfigFields = nextState;
}

function mergeProviderSpecificInputsIntoConfig(parsedConfig: Record<string, any>): Record<string, any> {
    const provider = selectedProvider.value;
    if (!provider) return parsedConfig;

    const fields = providerConfigSchemas[provider.code] ?? [];
    const merged = { ...parsedConfig };

    for (const field of fields) {
        const raw = (uiState.providerConfigFields[field.key] ?? '').trim();
        merged[field.key] = raw === '' ? null : raw;
    }

    return merged;
}

watch(
    () => [selectedProvider.value?.code, form.mode] as const,
    ([providerCode]) => {
        if (providerCode !== 'myfatoorah') return;

        const current = (uiState.providerConfigFields.api_base_url ?? '').trim();
        const knownDefaults = ['https://apitest.myfatoorah.com', 'https://api.myfatoorah.com'];

        if (current === '' || knownDefaults.includes(current)) {
            uiState.providerConfigFields.api_base_url = myFatoorahDefaultApiBaseUrl.value;
        }
    },
    { immediate: true },
);

function submit() {
    if (!selectedProvider.value) return;

    let parsedConfig: Record<string, any> = {};
    try {
        parsedConfig = uiState.configJson.trim() ? JSON.parse(uiState.configJson) : {};
    } catch {
        form.setError('config', 'Config JSON is invalid.');
        return;
    }

    if (parsedConfig === null || Array.isArray(parsedConfig) || typeof parsedConfig !== 'object') {
        form.setError('config', 'Config JSON must be an object.');
        return;
    }

    parsedConfig = mergeProviderSpecificInputsIntoConfig(parsedConfig);

    if (selectedProvider.value?.code === 'myfatoorah') {
        const callbackUrl = String(parsedConfig.callback_url ?? '').trim();
        const errorUrl = String(parsedConfig.error_url ?? '').trim();

        parsedConfig.api_base_url = String(parsedConfig.api_base_url ?? '').trim() || myFatoorahDefaultApiBaseUrl.value;
        parsedConfig.callback_url = callbackUrl === '' ? null : callbackUrl;
        parsedConfig.error_url = errorUrl === '' ? null : errorUrl;
    }

    uiState.configJson = JSON.stringify(parsedConfig, null, 2);

    form.clearErrors('config');
    form.config = parsedConfig;
    form.supported_countries = parseCsv(uiState.countriesCsv);
    form.supported_currencies = parseCsv(uiState.currenciesCsv).map((item) => item.toUpperCase());

    form.put(`/superadmin/settings/payment-providers/${selectedProvider.value.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Payment Providers" />

    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">Payment Providers</h1>
                    <p class="text-sm text-muted-foreground">
                        Configure approved gateways for platform subscriptions and tenant payments.
                    </p>
                </div>
                <Button :disabled="form.processing || !selectedProvider" @click="submit">
                    {{ form.processing ? 'Saving...' : 'Save Changes' }}
                </Button>
            </div>

            <div v-if="flashSuccess" class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
                {{ flashSuccess }}
            </div>
            <div v-if="flashError" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                {{ flashError }}
            </div>

            <div class="grid gap-6 lg:grid-cols-[320px_minmax(0,1fr)]">
                <Card>
                    <CardHeader>
                        <CardTitle>Providers</CardTitle>
                        <CardDescription>Select a provider to edit its settings.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="space-y-2">
                            <Label for="provider-search">Search</Label>
                            <Input id="provider-search" v-model="search" placeholder="Stripe, MyFatoorah..." />
                        </div>

                        <div class="max-h-[520px] space-y-2 overflow-auto pr-1">
                            <button
                                v-for="provider in filteredProviders"
                                :key="provider.id"
                                type="button"
                                class="w-full rounded-lg border p-3 text-left transition hover:bg-muted/30"
                                :class="provider.id === selectedProviderId ? 'border-primary bg-primary/5' : 'border-border'"
                                @click="selectedProviderId = provider.id"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <div>
                                        <div class="font-medium">{{ provider.name }}</div>
                                        <div class="text-xs text-muted-foreground font-mono">{{ provider.code }}</div>
                                    </div>
                                    <div class="text-right text-xs">
                                        <div :class="provider.is_enabled ? 'text-emerald-600' : 'text-gray-500'">
                                            {{ provider.is_enabled ? 'Enabled' : 'Disabled' }}
                                        </div>
                                        <div v-if="provider.is_default" class="text-amber-600">Default</div>
                                    </div>
                                </div>
                            </button>

                            <div v-if="filteredProviders.length === 0" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                                No providers match your search.
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div v-if="selectedProvider" class="space-y-6">
                    <form class="space-y-6" @submit.prevent="submit">
                        <Card>
                            <CardHeader>
                                <CardTitle>General</CardTitle>
                                <CardDescription>
                                    Basic identity and mode settings for {{ selectedProvider.name }}.
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid gap-4 md:grid-cols-3">
                                    <div class="space-y-2">
                                        <Label for="provider_name">Name</Label>
                                        <Input id="provider_name" v-model="form.name" />
                                        <p v-if="form.errors.name" class="text-sm text-red-600">{{ form.errors.name }}</p>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="provider_driver">Driver</Label>
                                        <Input id="provider_driver" v-model="form.driver" placeholder="myfatoorah" />
                                        <p v-if="form.errors.driver" class="text-sm text-red-600">{{ form.errors.driver }}</p>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="provider_mode">Mode</Label>
                                        <select
                                            id="provider_mode"
                                            v-model="form.mode"
                                            class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                        >
                                            <option value="test">Test</option>
                                            <option value="live">Live</option>
                                        </select>
                                        <p v-if="form.errors.mode" class="text-sm text-red-600">{{ form.errors.mode }}</p>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label for="provider_description">Description</Label>
                                    <textarea
                                        id="provider_description"
                                        v-model="form.description"
                                        rows="3"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    />
                                    <p v-if="form.errors.description" class="text-sm text-red-600">{{ form.errors.description }}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Availability & Usage</CardTitle>
                                <CardDescription>
                                    Control whether this provider is active and where it can be used.
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                                    <label class="flex items-center gap-2 rounded-md border p-3 text-sm">
                                        <input v-model="form.is_enabled" type="checkbox" />
                                        <span>Enabled</span>
                                    </label>

                                    <label class="flex items-center gap-2 rounded-md border p-3 text-sm">
                                        <input v-model="form.is_default" type="checkbox" />
                                        <span>Default</span>
                                    </label>

                                    <label class="flex items-center gap-2 rounded-md border p-3 text-sm">
                                        <input v-model="form.supports_platform_subscriptions" type="checkbox" />
                                        <span>Platform subscriptions</span>
                                    </label>

                                    <label class="flex items-center gap-2 rounded-md border p-3 text-sm">
                                        <input v-model="form.supports_tenant_payments" type="checkbox" />
                                        <span>Tenant payments</span>
                                    </label>

                                    <div class="space-y-2 rounded-md border p-3">
                                        <Label for="sort_order">Sort Order</Label>
                                        <Input id="sort_order" v-model.number="form.sort_order" type="number" min="0" />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Regions & Currencies</CardTitle>
                                <CardDescription>
                                    Use CSV values to control provider availability by country/currency.
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="supported_countries">Supported Countries (CSV)</Label>
                                        <Input id="supported_countries" v-model="uiState.countriesCsv" placeholder="OM, AE, SA, KW" />
                                        <p v-if="form.errors.supported_countries" class="text-sm text-red-600">
                                            {{ form.errors.supported_countries }}
                                        </p>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="supported_currencies">Supported Currencies (CSV)</Label>
                                        <Input id="supported_currencies" v-model="uiState.currenciesCsv" placeholder="OMR, AED, USD" />
                                        <p v-if="form.errors.supported_currencies" class="text-sm text-red-600">
                                            {{ form.errors.supported_currencies }}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Credentials & Webhooks</CardTitle>
                                <CardDescription>
                                    Fill provider-specific keys here. These values are saved into Provider Config JSON automatically.
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div v-if="basicProviderConfigFields.length > 0" class="grid gap-4 md:grid-cols-2">
                                    <div v-for="field in basicProviderConfigFields" :key="field.key" class="space-y-2">
                                        <Label :for="`provider-field-${field.key}`">{{ field.label }}</Label>
                                        <Input
                                            :id="`provider-field-${field.key}`"
                                            v-model="uiState.providerConfigFields[field.key]"
                                            :type="field.type ?? 'text'"
                                            :placeholder="field.placeholder || ''"
                                            :readonly="field.readonly === true"
                                        />
                                        <p v-if="field.help" class="text-xs text-muted-foreground">{{ field.help }}</p>
                                    </div>
                                </div>
                                <div v-else class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                                    No predefined credential fields for this provider yet. Use the JSON section below.
                                </div>

                                <div v-if="isMyFatoorahSelected" class="rounded-md border bg-muted/20 p-4 text-sm space-y-2">
                                    <div class="font-medium">Auto Defaults (MyFatoorah)</div>
                                    <div class="grid gap-2 md:grid-cols-2 text-xs text-muted-foreground">
                                        <div>
                                            <div class="font-medium text-foreground">API Base URL (auto)</div>
                                            <div class="font-mono break-all">{{ myFatoorahDefaultApiBaseUrl }}</div>
                                        </div>
                                        <div>
                                            <div class="font-medium text-foreground">Mode</div>
                                            <div>{{ form.mode === 'live' ? 'Live' : 'Test' }}</div>
                                        </div>
                                        <div class="md:col-span-2">
                                            <div class="font-medium text-foreground">Callback / Error URLs</div>
                                            <div>Generated automatically by the system routes during checkout (no manual input required).</div>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="advancedProviderConfigFields.length > 0" class="rounded-md border p-3 space-y-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <div class="text-sm font-medium">Advanced Provider Fields</div>
                                            <p class="text-xs text-muted-foreground">
                                                Optional overrides (keep hidden unless you need custom behavior).
                                            </p>
                                        </div>
                                        <Button type="button" variant="outline" size="sm" @click="showAdvancedProviderFields = !showAdvancedProviderFields">
                                            {{ showAdvancedProviderFields ? 'Hide Advanced' : 'Show Advanced' }}
                                        </Button>
                                    </div>

                                    <div v-if="showAdvancedProviderFields" class="grid gap-4 md:grid-cols-2">
                                        <div v-for="field in advancedProviderConfigFields" :key="`adv-${field.key}`" class="space-y-2">
                                            <Label :for="`provider-adv-field-${field.key}`">{{ field.label }}</Label>
                                            <Input
                                                :id="`provider-adv-field-${field.key}`"
                                                v-model="uiState.providerConfigFields[field.key]"
                                                :type="field.type ?? 'text'"
                                                :placeholder="field.placeholder || ''"
                                            />
                                            <p v-if="field.help" class="text-xs text-muted-foreground">{{ field.help }}</p>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Provider Config</CardTitle>
                                <CardDescription>
                                    Provider-specific JSON settings (API token, region, callback settings, profile IDs).
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="flex items-center justify-between rounded-md border p-3">
                                    <div>
                                        <div class="text-sm font-medium">Advanced JSON Editor</div>
                                        <p class="text-xs text-muted-foreground">
                                            Keep this hidden unless you need custom keys not available in the fields above.
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="uiState.showAdvancedJson = !uiState.showAdvancedJson"
                                    >
                                        {{ uiState.showAdvancedJson ? 'Hide JSON' : 'Show JSON' }}
                                    </Button>
                                </div>

                                <div class="space-y-2">
                                    <Label for="provider_config_json">Provider Config (JSON)</Label>
                                    <textarea
                                        id="provider_config_json"
                                        v-model="uiState.configJson"
                                        rows="10"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-xs"
                                        :readonly="!uiState.showAdvancedJson"
                                        :class="!uiState.showAdvancedJson ? 'cursor-not-allowed opacity-70' : ''"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        Values are currently stored as JSON and are not encrypted yet.
                                    </p>
                                    <p v-if="!uiState.showAdvancedJson" class="text-xs text-muted-foreground">
                                        This editor is read-only by default. Use the button above to enable editing.
                                    </p>
                                    <p v-if="form.errors.config" class="text-sm text-red-600">{{ form.errors.config }}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <div class="flex justify-end">
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? 'Saving...' : 'Save Changes' }}
                            </Button>
                        </div>
                    </form>
                </div>

                <Card v-else>
                    <CardHeader>
                        <CardTitle>No Provider Selected</CardTitle>
                        <CardDescription>Select a provider from the left list to edit it.</CardDescription>
                    </CardHeader>
                </Card>
            </div>
        </main>
    </SuperAdminLayout>
</template>
