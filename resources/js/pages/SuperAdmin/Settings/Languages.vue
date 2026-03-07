<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type LocaleRow = {
    code: string;
    name: string;
    native: string;
    regional: string;
    script: string;
    direction: 'ltr' | 'rtl';
};

const languagePresets: LocaleRow[] = [
    { code: 'en', name: 'English', native: 'English', regional: 'en_US', script: 'Latn', direction: 'ltr' },
    { code: 'ar', name: 'Arabic', native: 'Arabic', regional: 'ar_AE', script: 'Arab', direction: 'rtl' },
    { code: 'fr', name: 'French', native: 'Francais', regional: 'fr_FR', script: 'Latn', direction: 'ltr' },
    { code: 'de', name: 'German', native: 'Deutsch', regional: 'de_DE', script: 'Latn', direction: 'ltr' },
    { code: 'es', name: 'Spanish', native: 'Espanol', regional: 'es_ES', script: 'Latn', direction: 'ltr' },
    { code: 'it', name: 'Italian', native: 'Italiano', regional: 'it_IT', script: 'Latn', direction: 'ltr' },
    { code: 'pt-BR', name: 'Portuguese (Brazil)', native: 'Portugues', regional: 'pt_BR', script: 'Latn', direction: 'ltr' },
    { code: 'tr', name: 'Turkish', native: 'Turkce', regional: 'tr_TR', script: 'Latn', direction: 'ltr' },
    { code: 'ru', name: 'Russian', native: 'Russkiy', regional: 'ru_RU', script: 'Cyrl', direction: 'ltr' },
    { code: 'fa', name: 'Persian', native: 'Farsi', regional: 'fa_IR', script: 'Arab', direction: 'rtl' },
];

const props = defineProps<{
    settings: {
        default_locale: string;
        locales: LocaleRow[];
    };
    actions: {
        update: string;
    };
}>();

const form = useForm({
    default_locale: props.settings.default_locale,
    locales: props.settings.locales.map((locale) => ({
        code: locale.code,
        name: locale.name,
        native: locale.native,
        regional: locale.regional,
        script: locale.script,
        direction: locale.direction,
    })),
});

const selectedPresetCode = ref(languagePresets[0]?.code || 'en');

const existingLocaleCodes = computed(() =>
    form.locales.map((item) => String(item.code || '').toLowerCase())
);

const selectedPreset = computed(() =>
    languagePresets.find((item) => item.code === selectedPresetCode.value) || null
);

const canInsertSelectedPreset = computed(() => {
    if (!selectedPreset.value) {
        return false;
    }

    return !existingLocaleCodes.value.includes(selectedPreset.value.code.toLowerCase());
});

function addLanguage() {
    form.locales.push({
        code: '',
        name: '',
        native: '',
        regional: '',
        script: '',
        direction: 'ltr',
    });
}

function insertPresetLanguage() {
    if (!selectedPreset.value || !canInsertSelectedPreset.value) {
        return;
    }

    form.locales.push({
        code: selectedPreset.value.code,
        name: selectedPreset.value.name,
        native: selectedPreset.value.native,
        regional: selectedPreset.value.regional,
        script: selectedPreset.value.script,
        direction: selectedPreset.value.direction,
    });
}

function removeLanguage(index: number) {
    const removed = form.locales[index];
    form.locales.splice(index, 1);

    if (form.locales.length === 0) {
        addLanguage();
    }

    if (removed && form.default_locale === removed.code) {
        form.default_locale = form.locales[0]?.code || 'en';
    }
}

function submit() {
    if (!form.default_locale && form.locales.length > 0) {
        form.default_locale = form.locales[0].code || 'en';
    }

    form.put(props.actions.update, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Language Settings" />

    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">Language Settings</h1>
                    <p class="text-sm text-muted-foreground">
                        Add or edit platform languages. Tenants can enable and customize translations per language.
                    </p>
                </div>
                <Button :disabled="form.processing" @click="submit">
                    {{ form.processing ? 'Saving...' : 'Save Changes' }}
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Default Locale</CardTitle>
                    <CardDescription>
                        This locale is used as system fallback when translation key is missing.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-2">
                    <Label for="default_locale">Default locale</Label>
                    <select
                        id="default_locale"
                        v-model="form.default_locale"
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 dark:bg-input/30"
                    >
                        <option
                            v-for="locale in form.locales"
                            :key="`default-${locale.code || 'new'}`"
                            :value="locale.code"
                        >
                            {{ locale.code || 'new-locale' }} - {{ locale.name || 'Unnamed' }}
                        </option>
                    </select>
                    <p v-if="form.errors.default_locale" class="text-sm text-red-600">
                        {{ form.errors.default_locale }}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <CardTitle>Languages</CardTitle>
                            <CardDescription>
                                Use locale code like <code>en</code>, <code>ar</code>, <code>fr</code>, <code>pt-BR</code>.
                            </CardDescription>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <select
                                v-model="selectedPresetCode"
                                class="h-9 rounded-md border border-input bg-transparent px-3 text-sm dark:bg-input/30"
                            >
                                <option v-for="preset in languagePresets" :key="preset.code" :value="preset.code">
                                    {{ preset.name }} ({{ preset.code }})
                                </option>
                            </select>
                            <Button type="button" variant="outline" :disabled="!canInsertSelectedPreset" @click="insertPresetLanguage">
                                Insert From Dropdown
                            </Button>
                            <Button type="button" variant="outline" @click="addLanguage">
                                Add Empty Row
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div
                        v-for="(locale, index) in form.locales"
                        :key="`locale-row-${index}`"
                        class="space-y-3 rounded-lg border p-4"
                    >
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                            <div class="space-y-1">
                                <Label :for="`locale-code-${index}`">Code</Label>
                                <Input :id="`locale-code-${index}`" v-model="locale.code" placeholder="en" />
                                <p v-if="form.errors[`locales.${index}.code`]" class="text-xs text-red-600">
                                    {{ form.errors[`locales.${index}.code`] }}
                                </p>
                            </div>

                            <div class="space-y-1">
                                <Label :for="`locale-name-${index}`">Name</Label>
                                <Input :id="`locale-name-${index}`" v-model="locale.name" placeholder="English" />
                                <p v-if="form.errors[`locales.${index}.name`]" class="text-xs text-red-600">
                                    {{ form.errors[`locales.${index}.name`] }}
                                </p>
                            </div>

                            <div class="space-y-1">
                                <Label :for="`locale-native-${index}`">Native</Label>
                                <Input :id="`locale-native-${index}`" v-model="locale.native" placeholder="English / العربية" />
                                <p v-if="form.errors[`locales.${index}.native`]" class="text-xs text-red-600">
                                    {{ form.errors[`locales.${index}.native`] }}
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                            <div class="space-y-1">
                                <Label :for="`locale-regional-${index}`">Regional</Label>
                                <Input :id="`locale-regional-${index}`" v-model="locale.regional" placeholder="en_US" />
                            </div>

                            <div class="space-y-1">
                                <Label :for="`locale-script-${index}`">Script</Label>
                                <Input :id="`locale-script-${index}`" v-model="locale.script" placeholder="Latn / Arab" />
                            </div>

                            <div class="space-y-1">
                                <Label :for="`locale-direction-${index}`">Direction</Label>
                                <select
                                    :id="`locale-direction-${index}`"
                                    v-model="locale.direction"
                                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 dark:bg-input/30"
                                >
                                    <option value="ltr">LTR</option>
                                    <option value="rtl">RTL</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <Button type="button" variant="destructive" @click="removeLanguage(index)">
                                Remove
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </main>
    </SuperAdminLayout>
</template>
