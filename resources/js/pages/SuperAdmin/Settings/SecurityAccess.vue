<script setup lang="ts">
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

const props = defineProps<{
    settings: {
        superadmin_allowed_countries: string[];
        superadmin_allowed_ips: string;
        superadmin_blocked_ips: string;
        website_blocked_ips: string;
    };
    countries: Array<{
        iso2: string;
        name_en: string;
        name_ar: string;
        dial_code: string;
    }>;
    currentRequest: {
        ip: string | null;
        country: string | null;
    };
    actions: {
        update: string;
    };
}>();

const form = useForm({
    settings: { ...props.settings },
});

const selectedCountryToAdd = ref<string>('');

const availableCountries = computed(() =>
    props.countries.filter((country) => !form.settings.superadmin_allowed_countries.includes(country.iso2)),
);

const selectedCountries = computed(() =>
    form.settings.superadmin_allowed_countries
        .map((iso2) => props.countries.find((country) => country.iso2 === iso2))
        .filter(Boolean) as Array<{ iso2: string; name_en: string; name_ar: string; dial_code: string }>,
);

const addCountry = () => {
    if (!selectedCountryToAdd.value || form.settings.superadmin_allowed_countries.includes(selectedCountryToAdd.value)) {
        return;
    }

    form.settings.superadmin_allowed_countries.push(selectedCountryToAdd.value);
    form.settings.superadmin_allowed_countries.sort((a, b) => a.localeCompare(b));
    selectedCountryToAdd.value = '';
};

const removeCountry = (iso2: string) => {
    form.settings.superadmin_allowed_countries = form.settings.superadmin_allowed_countries.filter((item) => item !== iso2);
};

const submit = () => {
    form.put(props.actions.update, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Security Access" />

    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">Security Access</h1>
                    <p class="text-sm text-muted-foreground">
                        Restrict Super Admin access by country and IP, and block abusive IPs from the website.
                    </p>
                </div>
                <Button :disabled="form.processing" @click="submit">
                    {{ form.processing ? 'Saving...' : 'Save Changes' }}
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Current Request</CardTitle>
                    <CardDescription>
                        Use this to avoid locking yourself out when adding allowlists.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-lg border p-4">
                        <div class="text-xs uppercase text-muted-foreground">Detected IP</div>
                        <div class="mt-1 font-medium">{{ currentRequest.ip || 'Unknown' }}</div>
                    </div>
                    <div class="rounded-lg border p-4">
                        <div class="text-xs uppercase text-muted-foreground">Detected Country</div>
                        <div class="mt-1 font-medium">{{ currentRequest.country || 'Unknown' }}</div>
                    </div>
                </CardContent>
            </Card>

            <div class="grid gap-6 xl:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Super Admin Access Rules</CardTitle>
                        <CardDescription>
                            If an allowlist field is filled, anything not matching it will be denied.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="superadmin_allowed_countries">Allowed Countries</Label>
                            <div class="flex gap-2">
                                <Select v-model="selectedCountryToAdd">
                                    <SelectTrigger id="superadmin_allowed_countries" class="flex-1">
                                        <SelectValue placeholder="Select a country" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="country in availableCountries"
                                            :key="country.iso2"
                                            :value="country.iso2"
                                        >
                                            {{ country.name_en }} ({{ country.iso2 }})
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <Button type="button" variant="outline" :disabled="!selectedCountryToAdd" @click="addCountry">
                                    Add
                                </Button>
                            </div>
                            <div v-if="selectedCountries.length" class="flex flex-wrap gap-2 pt-2">
                                <div
                                    v-for="country in selectedCountries"
                                    :key="country.iso2"
                                    class="inline-flex items-center gap-2 rounded-full border bg-background px-3 py-1 text-sm"
                                >
                                    <span>{{ country.name_en }} ({{ country.iso2 }})</span>
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:text-foreground"
                                        @click="removeCountry(country.iso2)"
                                    >
                                        x
                                    </button>
                                </div>
                            </div>
                            <p v-else class="text-xs text-muted-foreground">
                                No country restrictions. Leave empty to allow all countries.
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Headers like <code>CF-IPCountry</code> are used when available.
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="superadmin_allowed_ips">Allowed IPs / CIDR</Label>
                            <Textarea
                                id="superadmin_allowed_ips"
                                v-model="form.settings.superadmin_allowed_ips"
                                rows="8"
                                placeholder="203.0.113.10&#10;198.51.100.0/24&#10;2001:db8::/32"
                            />
                            <p class="text-xs text-muted-foreground">
                                One IP or CIDR per line. Useful for office IPs or VPN ranges.
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="superadmin_blocked_ips">Blocked IPs / CIDR</Label>
                            <Textarea
                                id="superadmin_blocked_ips"
                                v-model="form.settings.superadmin_blocked_ips"
                                rows="8"
                                placeholder="192.0.2.15&#10;203.0.113.0/24"
                            />
                            <p class="text-xs text-muted-foreground">
                                These are blocked from all <code>/superadmin</code> routes, including the login page.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Website-wide IP Blocklist</CardTitle>
                        <CardDescription>
                            These IPs are denied before the application continues, on all public and tenant routes.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="website_blocked_ips">Blocked IPs / CIDR</Label>
                            <Textarea
                                id="website_blocked_ips"
                                v-model="form.settings.website_blocked_ips"
                                rows="12"
                                placeholder="198.51.100.7&#10;198.51.100.0/24"
                            />
                            <p class="text-xs text-muted-foreground">
                                Use this for abusive IPs. It applies to the whole website, not only Super Admin.
                            </p>
                        </div>

                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                            Keep your current IP in the allowlist before tightening Super Admin rules. In local and testing environments, these restrictions are bypassed automatically.
                        </div>
                    </CardContent>
                </Card>
            </div>
        </main>
    </SuperAdminLayout>
</template>
