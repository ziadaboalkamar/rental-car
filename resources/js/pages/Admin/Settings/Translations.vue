<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type LocaleMeta = {
    code: string;
    name: string;
    native: string;
};

type TranslationRow = {
    key: string;
    defaults: Record<string, string>;
    values: Record<string, string>;
};

const props = defineProps<{
    tenant: {
        id: number;
        name: string;
        slug: string;
    };
    supported_locales: LocaleMeta[];
    enabled_locales: string[];
    rows: TranslationRow[];
    actions: {
        update: string;
    };
}>();

const page = usePage<any>();
const search = ref('');
const focusedLocale = ref<string>('');
const onlyCustomized = ref(false);
const onlyEmptyForFocusedLocale = ref(false);
const localeCodes = computed(() => props.supported_locales.map((item) => item.code));
const localeMetaByCode = computed(() =>
    props.supported_locales.reduce<Record<string, LocaleMeta>>((acc, item) => {
        acc[item.code] = item;
        return acc;
    }, {})
);

const form = useForm({
    enabled_locales: Array.isArray(props.enabled_locales) && props.enabled_locales.length
        ? [...props.enabled_locales]
        : [...localeCodes.value],
    rows: props.rows.map((row) => ({
        key: row.key,
        values: { ...row.values },
    })),
});

if (!focusedLocale.value) {
    focusedLocale.value = localeCodes.value[0] || 'en';
}

const rowsWithDefaults = computed(() =>
    props.rows.map((row, index) => ({
        ...row,
        formRow: form.rows[index],
    }))
);

const filteredRows = computed(() => {
    const query = search.value.trim().toLowerCase();

    return rowsWithDefaults.value.filter((row) => {
        const matchesSearch = !query || row.key.toLowerCase().includes(query) || localeCodes.value.some((locale) =>
            String(row.defaults?.[locale] || '').toLowerCase().includes(query)
            || String(row.formRow?.values?.[locale] || '').toLowerCase().includes(query)
        );

        if (!matchesSearch) {
            return false;
        }

        if (onlyCustomized.value && !isRowCustomized(row)) {
            return false;
        }

        if (onlyEmptyForFocusedLocale.value && !isFocusedLocaleEmpty(row)) {
            return false;
        }

        return true;
    });
});

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
const formErrorList = computed(() => Object.values(form.errors ?? {}).filter((value): value is string => typeof value === 'string' && value.length > 0));

function isEmpty(value: unknown): boolean {
    return String(value ?? '').trim() === '';
}

function isRowCustomized(row: (typeof rowsWithDefaults.value)[number]): boolean {
    return localeCodes.value.some((locale) => !isEmpty(row.formRow?.values?.[locale]));
}

function isFocusedLocaleEmpty(row: (typeof rowsWithDefaults.value)[number]): boolean {
    const locale = focusedLocale.value;
    if (!locale) return true;

    return isEmpty(row.formRow?.values?.[locale]);
}

function copyDefaultToLocale(row: (typeof rowsWithDefaults.value)[number], locale: string) {
    row.formRow.values[locale] = String(row.defaults?.[locale] || '');
}

function clearLocaleValue(row: (typeof rowsWithDefaults.value)[number], locale: string) {
    row.formRow.values[locale] = '';
}

function fillEmptyFromDefaultsForFocusedLocale() {
    const locale = focusedLocale.value;
    if (!locale) return;

    rowsWithDefaults.value.forEach((row) => {
        if (isEmpty(row.formRow.values[locale])) {
            row.formRow.values[locale] = String(row.defaults?.[locale] || '');
        }
    });
}

function clearFocusedLocaleValues() {
    const locale = focusedLocale.value;
    if (!locale) return;

    rowsWithDefaults.value.forEach((row) => {
        row.formRow.values[locale] = '';
    });
}

function submit() {
    if (!Array.isArray(form.enabled_locales) || form.enabled_locales.length === 0) {
        form.enabled_locales = [localeCodes.value[0] || 'en'];
    }

    form.put(props.actions.update, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Translations Settings" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">Translations Settings</h1>
                    <p class="text-sm text-muted-foreground">Enable languages and edit words in table format for this tenant website.</p>
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
            <div v-if="formErrorList.length" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                <div class="font-medium">Please fix the following errors:</div>
                <ul class="mt-1 list-disc pl-5">
                    <li v-for="(message, idx) in formErrorList" :key="idx">{{ message }}</li>
                </ul>
            </div>

            <section class="rounded-lg border p-5 space-y-4">
                <div>
                    <h2 class="text-lg font-semibold">Language Activation</h2>
                </div>

                <div class="flex flex-wrap items-center gap-6 rounded-md border p-3">
                    <label v-for="locale in supported_locales" :key="locale.code" class="flex items-center gap-2 text-sm">
                        <input v-model="form.enabled_locales" type="checkbox" :value="locale.code" />
                        {{ locale.native }} ({{ locale.code.toUpperCase() }})
                    </label>
                </div>
                <p class="text-xs text-muted-foreground">At least one language must stay enabled.</p>
                <p v-if="form.errors['enabled_locales']" class="text-sm text-red-600">{{ form.errors['enabled_locales'] }}</p>
                <p v-if="form.errors['enabled_locales.0']" class="text-sm text-red-600">{{ form.errors['enabled_locales.0'] }}</p>
            </section>

            <section class="rounded-lg border p-5 space-y-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">Translations Table</h2>
                        <p class="text-sm text-muted-foreground">Edit only the words you need. Empty field uses default system translation.</p>
                    </div>
                    <div class="w-full space-y-3 md:w-auto">
                        <div class="w-full md:w-80">
                            <Label class="sr-only" for="translation_search">Search</Label>
                            <Input id="translation_search" v-model="search" placeholder="Search key or value..." />
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <select
                                v-model="focusedLocale"
                                class="h-9 rounded-md border border-input bg-transparent px-3 text-sm dark:bg-input/30"
                            >
                                <option v-for="locale in localeCodes" :key="`focus-${locale}`" :value="locale">
                                    Focus: {{ locale.toUpperCase() }}
                                </option>
                            </select>
                            <label class="inline-flex items-center gap-2 rounded-md border px-2 py-1 text-xs">
                                <input v-model="onlyCustomized" type="checkbox" />
                                Only customized
                            </label>
                            <label class="inline-flex items-center gap-2 rounded-md border px-2 py-1 text-xs">
                                <input v-model="onlyEmptyForFocusedLocale" type="checkbox" />
                                Only empty in focus locale
                            </label>
                            <Button type="button" variant="outline" size="sm" @click="fillEmptyFromDefaultsForFocusedLocale">
                                Fill Empty From Default
                            </Button>
                            <Button type="button" variant="outline" size="sm" @click="clearFocusedLocaleValues">
                                Clear Focus Locale
                            </Button>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-md border">
                    <table class="min-w-full text-sm">
                        <thead class="bg-muted/40 text-left">
                            <tr>
                                <th class="px-3 py-2 font-semibold">Key</th>
                                <template v-for="localeCode in localeCodes" :key="`h-${localeCode}`">
                                    <th class="px-3 py-2 font-semibold">
                                        Default {{ localeMetaByCode[localeCode]?.code?.toUpperCase() || localeCode.toUpperCase() }}
                                    </th>
                                    <th class="px-3 py-2 font-semibold">
                                        Edit {{ localeMetaByCode[localeCode]?.code?.toUpperCase() || localeCode.toUpperCase() }}
                                    </th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in filteredRows" :key="row.key" class="border-t align-top">
                                <td class="px-3 py-2">
                                    <div class="font-mono text-xs">{{ row.key }}</div>
                                </td>
                                <template v-for="localeCode in localeCodes" :key="`${row.key}-${localeCode}`">
                                    <td class="px-3 py-2">
                                        <div
                                            class="max-w-[260px] whitespace-pre-wrap text-xs text-muted-foreground"
                                            :dir="localeCode === 'ar' ? 'rtl' : 'ltr'"
                                        >
                                            {{ row.defaults?.[localeCode] || '' }}
                                        </div>
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="space-y-2">
                                            <Input
                                                v-model="row.formRow.values[localeCode]"
                                                placeholder="Use default"
                                                :dir="localeCode === 'ar' ? 'rtl' : 'ltr'"
                                            />
                                            <div class="flex gap-2">
                                                <button
                                                    type="button"
                                                    class="text-xs text-primary hover:underline"
                                                    @click="copyDefaultToLocale(row, localeCode)"
                                                >
                                                    Copy default
                                                </button>
                                                <button
                                                    type="button"
                                                    class="text-xs text-muted-foreground hover:underline"
                                                    @click="clearLocaleValue(row, localeCode)"
                                                >
                                                    Clear
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </template>
                            </tr>
                            <tr v-if="filteredRows.length === 0">
                                <td class="px-3 py-5 text-center text-muted-foreground" :colspan="1 + (localeCodes.length * 2)">
                                    No translation rows found for this search.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </AdminLayout>
</template>
