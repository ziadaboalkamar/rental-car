<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type LocalizedText = { en: string | null; ar: string | null };

const props = defineProps<{
    tenant: {
        id: number;
        name: string;
        slug: string;
    };
    settings: {
        site_name: string | null;
        logo_url: string | null;
        primary_color: string;
        secondary_color: string;
        tax_percentage: number;
        hero: {
            title: LocalizedText;
            description: LocalizedText;
            button_text: LocalizedText;
            button_link: string | null;
        };
        about: {
            title: LocalizedText;
            subtitle: LocalizedText;
            story_title: LocalizedText;
            story_p1: LocalizedText;
            story_p2: LocalizedText;
            mission_title: LocalizedText;
            mission_subtitle: LocalizedText;
            cta_title: LocalizedText;
            cta_subtitle: LocalizedText;
            cta_browse_text: LocalizedText;
            cta_contact_text: LocalizedText;
        };
        contact: {
            phone: string | null;
            email: string | null;
            address: LocalizedText;
        };
        contact_page: {
            title: LocalizedText;
            subtitle: LocalizedText;
            form_title: LocalizedText;
            info_title: LocalizedText;
            hours: LocalizedText;
            quick_links_title: LocalizedText;
        };
        footer: {
            description: LocalizedText;
        };
    };
    logoFiles: Array<{ id: number; url: string }>;
    actions: {
        update: string;
    };
}>();

const page = usePage<any>();

const form = useForm({
    site_name: props.settings.site_name ?? '',
    logo_url: props.settings.logo_url ?? '',
    logo_temp_folders: [] as string[],
    logo_removed_files: [] as number[],
    primary_color: props.settings.primary_color || '#f97316',
    secondary_color: props.settings.secondary_color || '#ea580c',
    tax_percentage: props.settings.tax_percentage ?? 7,
    hero: {
        title: {
            en: props.settings.hero?.title?.en ?? '',
            ar: props.settings.hero?.title?.ar ?? '',
        },
        description: {
            en: props.settings.hero?.description?.en ?? '',
            ar: props.settings.hero?.description?.ar ?? '',
        },
        button_text: {
            en: props.settings.hero?.button_text?.en ?? '',
            ar: props.settings.hero?.button_text?.ar ?? '',
        },
        button_link: props.settings.hero?.button_link ?? '',
    },
    about: {
        title: {
            en: props.settings.about?.title?.en ?? '',
            ar: props.settings.about?.title?.ar ?? '',
        },
        subtitle: {
            en: props.settings.about?.subtitle?.en ?? '',
            ar: props.settings.about?.subtitle?.ar ?? '',
        },
        story_title: {
            en: props.settings.about?.story_title?.en ?? '',
            ar: props.settings.about?.story_title?.ar ?? '',
        },
        story_p1: {
            en: props.settings.about?.story_p1?.en ?? '',
            ar: props.settings.about?.story_p1?.ar ?? '',
        },
        story_p2: {
            en: props.settings.about?.story_p2?.en ?? '',
            ar: props.settings.about?.story_p2?.ar ?? '',
        },
        mission_title: {
            en: props.settings.about?.mission_title?.en ?? '',
            ar: props.settings.about?.mission_title?.ar ?? '',
        },
        mission_subtitle: {
            en: props.settings.about?.mission_subtitle?.en ?? '',
            ar: props.settings.about?.mission_subtitle?.ar ?? '',
        },
        cta_title: {
            en: props.settings.about?.cta_title?.en ?? '',
            ar: props.settings.about?.cta_title?.ar ?? '',
        },
        cta_subtitle: {
            en: props.settings.about?.cta_subtitle?.en ?? '',
            ar: props.settings.about?.cta_subtitle?.ar ?? '',
        },
        cta_browse_text: {
            en: props.settings.about?.cta_browse_text?.en ?? '',
            ar: props.settings.about?.cta_browse_text?.ar ?? '',
        },
        cta_contact_text: {
            en: props.settings.about?.cta_contact_text?.en ?? '',
            ar: props.settings.about?.cta_contact_text?.ar ?? '',
        },
    },
    contact: {
        phone: props.settings.contact?.phone ?? '',
        email: props.settings.contact?.email ?? '',
        address: {
            en: props.settings.contact?.address?.en ?? '',
            ar: props.settings.contact?.address?.ar ?? '',
        },
    },
    contact_page: {
        title: {
            en: props.settings.contact_page?.title?.en ?? '',
            ar: props.settings.contact_page?.title?.ar ?? '',
        },
        subtitle: {
            en: props.settings.contact_page?.subtitle?.en ?? '',
            ar: props.settings.contact_page?.subtitle?.ar ?? '',
        },
        form_title: {
            en: props.settings.contact_page?.form_title?.en ?? '',
            ar: props.settings.contact_page?.form_title?.ar ?? '',
        },
        info_title: {
            en: props.settings.contact_page?.info_title?.en ?? '',
            ar: props.settings.contact_page?.info_title?.ar ?? '',
        },
        hours: {
            en: props.settings.contact_page?.hours?.en ?? '',
            ar: props.settings.contact_page?.hours?.ar ?? '',
        },
        quick_links_title: {
            en: props.settings.contact_page?.quick_links_title?.en ?? '',
            ar: props.settings.contact_page?.quick_links_title?.ar ?? '',
        },
    },
    footer: {
        description: {
            en: props.settings.footer?.description?.en ?? '',
            ar: props.settings.footer?.description?.ar ?? '',
        },
    },
});

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
const flashRestrictedAction = computed(() => page.props.flash?.restricted_action ?? null);
const formErrorList = computed(() => Object.values(form.errors ?? {}).filter((value): value is string => typeof value === 'string' && value.length > 0));
const previewName = computed(() => form.site_name || props.tenant.name);
const uploadedLogoUrl = computed(() => props.logoFiles?.[0]?.url || null);
const previewLogoUrl = computed(() => uploadedLogoUrl.value || form.logo_url || null);
const primarySecondaryGradient = computed(
    () => `linear-gradient(135deg, ${form.primary_color || '#f97316'}, ${form.secondary_color || '#ea580c'})`,
);

const fileUploadRef = ref<InstanceType<typeof FileUpload> | null>(null);
const logoTempFolders = ref<string[]>([]);
const logoRemovedFileIds = ref<number[]>([]);
const showAdvancedBranding = ref(false);

watch(
    logoTempFolders,
    (value) => {
        form.logo_temp_folders = [...value];
    },
    { deep: true },
);

watch(
    () => form.errors.logo_url,
    (value) => {
        if (value) {
            showAdvancedBranding.value = true;
        }
    },
);

function handleLogoFileRemoved(data: { type: string; fileId?: number }) {
    if (data.type === 'existing' && data.fileId) {
        logoRemovedFileIds.value.push(data.fileId);
        form.logo_removed_files = [...new Set(logoRemovedFileIds.value)];
    }
}

function submit() {
    form.put(props.actions.update, {
        preserveScroll: true,
        onSuccess: () => {
            logoTempFolders.value = [];
            form.logo_temp_folders = [];
            form.logo_removed_files = [];
            logoRemovedFileIds.value = [];
            fileUploadRef.value?.resetFiles();
        },
    });
}
</script>

<template>
    <Head title="Website Settings" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">Website Settings</h1>
                    <p class="text-sm text-muted-foreground">
                        Customize your tenant website branding and homepage content (Arabic / English).
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
            <div v-if="flashRestrictedAction" class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700">
                {{ flashRestrictedAction }}
            </div>
            <div v-if="formErrorList.length" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                <div class="font-medium">Please fix the following errors:</div>
                <ul class="mt-1 list-disc pl-5">
                    <li v-for="(message, idx) in formErrorList" :key="idx">{{ message }}</li>
                </ul>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <section class="rounded-lg border p-5">
                    <div class="mb-4">
                        <h2 class="text-lg font-semibold">Branding</h2>
                        <p class="text-sm text-muted-foreground">Site identity, logo URL, and brand colors.</p>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2 md:col-span-2">
                                <Label for="site_name">Site Name</Label>
                                <Input id="site_name" v-model="form.site_name" placeholder="Tenant website name" />
                                <p v-if="form.errors.site_name" class="text-sm text-red-600">{{ form.errors.site_name }}</p>
                            </div>

                            <div class="space-y-2 md:col-span-2">
                                <Label>Logo Upload (System)</Label>
                                <FileUpload
                                    ref="fileUploadRef"
                                    v-model="logoTempFolders"
                                    :initial-files="logoFiles || []"
                                    :allow-multiple="false"
                                    :max-files="1"
                                    collection="logo"
                                    theme="light"
                                    width="100%"
                                    @file-removed="handleLogoFileRemoved"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Upload logo to your system. New upload replaces the previous logo.
                                </p>
                            </div>

                            <div class="md:col-span-2 rounded-md border bg-muted/20 p-3 space-y-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-medium">Advanced Branding Options</div>
                                        <p class="text-xs text-muted-foreground">Optional fallback logo URL (used only if no uploaded logo exists).</p>
                                    </div>
                                    <Button type="button" variant="outline" size="sm" @click="showAdvancedBranding = !showAdvancedBranding">
                                        {{ showAdvancedBranding ? 'Hide Advanced' : 'Show Advanced' }}
                                    </Button>
                                </div>

                                <div v-if="showAdvancedBranding" class="space-y-2">
                                    <Label for="logo_url">Fallback Logo URL</Label>
                                    <Input id="logo_url" v-model="form.logo_url" placeholder="https://example.com/logo.png" />
                                    <p class="text-xs text-muted-foreground">
                                        This URL is used only when no uploaded logo exists in the system.
                                    </p>
                                    <p v-if="form.errors.logo_url" class="text-sm text-red-600">{{ form.errors.logo_url }}</p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="primary_color">Primary Color</Label>
                                <div class="flex items-center gap-2">
                                    <input id="primary_color" v-model="form.primary_color" type="color" class="h-10 w-14 rounded border border-input bg-white p-1" />
                                    <Input v-model="form.primary_color" placeholder="#f97316" />
                                </div>
                                <p v-if="form.errors.primary_color" class="text-sm text-red-600">{{ form.errors.primary_color }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="secondary_color">Secondary Color</Label>
                                <div class="flex items-center gap-2">
                                    <input id="secondary_color" v-model="form.secondary_color" type="color" class="h-10 w-14 rounded border border-input bg-white p-1" />
                                    <Input v-model="form.secondary_color" placeholder="#ea580c" />
                                </div>
                                <p v-if="form.errors.secondary_color" class="text-sm text-red-600">{{ form.errors.secondary_color }}</p>
                            </div>

                            <div class="space-y-2 md:col-span-2">
                                <Label for="tax_percentage">Booking Tax Percentage</Label>
                                <Input
                                    id="tax_percentage"
                                    v-model.number="form.tax_percentage"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    placeholder="7"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Set `0` to hide tax in booking page.
                                </p>
                                <p v-if="form.errors.tax_percentage" class="text-sm text-red-600">{{ form.errors.tax_percentage }}</p>
                            </div>
                        </div>

                        <div class="rounded-lg border p-4">
                            <div class="text-sm font-medium mb-3">Preview</div>
                            <div class="rounded-xl border overflow-hidden bg-white">
                                <div class="h-20" :style="{ background: primarySecondaryGradient }"></div>
                                <div class="p-4 space-y-3">
                                    <div class="flex items-center gap-3">
                                        <img
                                            v-if="previewLogoUrl"
                                            :src="previewLogoUrl"
                                            alt="logo preview"
                                            class="h-10 w-10 rounded object-contain border bg-white p-1"
                                        />
                                        <div
                                            v-else
                                            class="h-10 w-10 rounded flex items-center justify-center text-white font-bold"
                                            :style="{ background: form.primary_color }"
                                        >
                                            {{ previewName.charAt(0).toUpperCase() }}
                                        </div>
                                        <div class="font-semibold truncate">{{ previewName }}</div>
                                    </div>
                                    <button
                                        type="button"
                                        class="w-full rounded-md px-3 py-2 text-sm font-semibold text-white"
                                        :style="{ background: primarySecondaryGradient }"
                                    >
                                        CTA Preview
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border p-5 space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold">Hero Section</h2>
                        <p class="text-sm text-muted-foreground">Main banner texts for the tenant homepage.</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="hero_title_en">Hero Title (EN)</Label>
                            <Input id="hero_title_en" v-model="form.hero.title.en" placeholder="Rent the perfect car today" />
                            <p v-if="form.errors['hero.title.en']" class="text-sm text-red-600">{{ form.errors['hero.title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="hero_title_ar">Hero Title (AR)</Label>
                            <Input id="hero_title_ar" v-model="form.hero.title.ar" placeholder="استأجر السيارة المناسبة اليوم" dir="rtl" />
                            <p v-if="form.errors['hero.title.ar']" class="text-sm text-red-600">{{ form.errors['hero.title.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="hero_desc_en">Hero Description (EN)</Label>
                            <textarea id="hero_desc_en" v-model="form.hero.description.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['hero.description.en']" class="text-sm text-red-600">{{ form.errors['hero.description.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="hero_desc_ar">Hero Description (AR)</Label>
                            <textarea id="hero_desc_ar" v-model="form.hero.description.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['hero.description.ar']" class="text-sm text-red-600">{{ form.errors['hero.description.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="hero_button_text_en">Button Text (EN)</Label>
                            <Input id="hero_button_text_en" v-model="form.hero.button_text.en" placeholder="Browse Fleet" />
                            <p v-if="form.errors['hero.button_text.en']" class="text-sm text-red-600">{{ form.errors['hero.button_text.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="hero_button_text_ar">Button Text (AR)</Label>
                            <Input id="hero_button_text_ar" v-model="form.hero.button_text.ar" placeholder="تصفح السيارات" dir="rtl" />
                            <p v-if="form.errors['hero.button_text.ar']" class="text-sm text-red-600">{{ form.errors['hero.button_text.ar'] }}</p>
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <Label for="hero_button_link">Button Link</Label>
                            <Input id="hero_button_link" v-model="form.hero.button_link" placeholder="/fleet" />
                            <p class="text-xs text-muted-foreground">Example: `/fleet` or `https://...`</p>
                            <p v-if="form.errors['hero.button_link']" class="text-sm text-red-600">{{ form.errors['hero.button_link'] }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border p-5 space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold">About Page</h2>
                        <p class="text-sm text-muted-foreground">Editable content for public About page.</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="about_title_en">Page Title (EN)</Label>
                            <Input id="about_title_en" v-model="form.about.title.en" />
                            <p v-if="form.errors['about.title.en']" class="text-sm text-red-600">{{ form.errors['about.title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_title_ar">Page Title (AR)</Label>
                            <Input id="about_title_ar" v-model="form.about.title.ar" dir="rtl" />
                            <p v-if="form.errors['about.title.ar']" class="text-sm text-red-600">{{ form.errors['about.title.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_subtitle_en">Subtitle (EN)</Label>
                            <textarea id="about_subtitle_en" v-model="form.about.subtitle.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.subtitle.en']" class="text-sm text-red-600">{{ form.errors['about.subtitle.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_subtitle_ar">Subtitle (AR)</Label>
                            <textarea id="about_subtitle_ar" v-model="form.about.subtitle.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.subtitle.ar']" class="text-sm text-red-600">{{ form.errors['about.subtitle.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_story_title_en">Story Title (EN)</Label>
                            <Input id="about_story_title_en" v-model="form.about.story_title.en" />
                            <p v-if="form.errors['about.story_title.en']" class="text-sm text-red-600">{{ form.errors['about.story_title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_story_title_ar">Story Title (AR)</Label>
                            <Input id="about_story_title_ar" v-model="form.about.story_title.ar" dir="rtl" />
                            <p v-if="form.errors['about.story_title.ar']" class="text-sm text-red-600">{{ form.errors['about.story_title.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_story_p1_en">Story Paragraph 1 (EN)</Label>
                            <textarea id="about_story_p1_en" v-model="form.about.story_p1.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.story_p1.en']" class="text-sm text-red-600">{{ form.errors['about.story_p1.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_story_p1_ar">Story Paragraph 1 (AR)</Label>
                            <textarea id="about_story_p1_ar" v-model="form.about.story_p1.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.story_p1.ar']" class="text-sm text-red-600">{{ form.errors['about.story_p1.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_story_p2_en">Story Paragraph 2 (EN)</Label>
                            <textarea id="about_story_p2_en" v-model="form.about.story_p2.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.story_p2.en']" class="text-sm text-red-600">{{ form.errors['about.story_p2.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_story_p2_ar">Story Paragraph 2 (AR)</Label>
                            <textarea id="about_story_p2_ar" v-model="form.about.story_p2.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.story_p2.ar']" class="text-sm text-red-600">{{ form.errors['about.story_p2.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_mission_title_en">Mission Title (EN)</Label>
                            <Input id="about_mission_title_en" v-model="form.about.mission_title.en" />
                            <p v-if="form.errors['about.mission_title.en']" class="text-sm text-red-600">{{ form.errors['about.mission_title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_mission_title_ar">Mission Title (AR)</Label>
                            <Input id="about_mission_title_ar" v-model="form.about.mission_title.ar" dir="rtl" />
                            <p v-if="form.errors['about.mission_title.ar']" class="text-sm text-red-600">{{ form.errors['about.mission_title.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_mission_subtitle_en">Mission Subtitle (EN)</Label>
                            <textarea id="about_mission_subtitle_en" v-model="form.about.mission_subtitle.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.mission_subtitle.en']" class="text-sm text-red-600">{{ form.errors['about.mission_subtitle.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_mission_subtitle_ar">Mission Subtitle (AR)</Label>
                            <textarea id="about_mission_subtitle_ar" v-model="form.about.mission_subtitle.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.mission_subtitle.ar']" class="text-sm text-red-600">{{ form.errors['about.mission_subtitle.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_cta_title_en">CTA Title (EN)</Label>
                            <Input id="about_cta_title_en" v-model="form.about.cta_title.en" />
                            <p v-if="form.errors['about.cta_title.en']" class="text-sm text-red-600">{{ form.errors['about.cta_title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_cta_title_ar">CTA Title (AR)</Label>
                            <Input id="about_cta_title_ar" v-model="form.about.cta_title.ar" dir="rtl" />
                            <p v-if="form.errors['about.cta_title.ar']" class="text-sm text-red-600">{{ form.errors['about.cta_title.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_cta_subtitle_en">CTA Subtitle (EN)</Label>
                            <textarea id="about_cta_subtitle_en" v-model="form.about.cta_subtitle.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.cta_subtitle.en']" class="text-sm text-red-600">{{ form.errors['about.cta_subtitle.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_cta_subtitle_ar">CTA Subtitle (AR)</Label>
                            <textarea id="about_cta_subtitle_ar" v-model="form.about.cta_subtitle.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.cta_subtitle.ar']" class="text-sm text-red-600">{{ form.errors['about.cta_subtitle.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_cta_browse_text_en">CTA Browse Button (EN)</Label>
                            <Input id="about_cta_browse_text_en" v-model="form.about.cta_browse_text.en" />
                            <p v-if="form.errors['about.cta_browse_text.en']" class="text-sm text-red-600">{{ form.errors['about.cta_browse_text.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_cta_browse_text_ar">CTA Browse Button (AR)</Label>
                            <Input id="about_cta_browse_text_ar" v-model="form.about.cta_browse_text.ar" dir="rtl" />
                            <p v-if="form.errors['about.cta_browse_text.ar']" class="text-sm text-red-600">{{ form.errors['about.cta_browse_text.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_cta_contact_text_en">CTA Contact Button (EN)</Label>
                            <Input id="about_cta_contact_text_en" v-model="form.about.cta_contact_text.en" />
                            <p v-if="form.errors['about.cta_contact_text.en']" class="text-sm text-red-600">{{ form.errors['about.cta_contact_text.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_cta_contact_text_ar">CTA Contact Button (AR)</Label>
                            <Input id="about_cta_contact_text_ar" v-model="form.about.cta_contact_text.ar" dir="rtl" />
                            <p v-if="form.errors['about.cta_contact_text.ar']" class="text-sm text-red-600">{{ form.errors['about.cta_contact_text.ar'] }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border p-5 space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold">Contact Page</h2>
                        <p class="text-sm text-muted-foreground">Editable titles and business hours for public Contact page.</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="contact_page_title_en">Page Title (EN)</Label>
                            <Input id="contact_page_title_en" v-model="form.contact_page.title.en" />
                            <p v-if="form.errors['contact_page.title.en']" class="text-sm text-red-600">{{ form.errors['contact_page.title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact_page_title_ar">Page Title (AR)</Label>
                            <Input id="contact_page_title_ar" v-model="form.contact_page.title.ar" dir="rtl" />
                            <p v-if="form.errors['contact_page.title.ar']" class="text-sm text-red-600">{{ form.errors['contact_page.title.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="contact_page_subtitle_en">Subtitle (EN)</Label>
                            <textarea id="contact_page_subtitle_en" v-model="form.contact_page.subtitle.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['contact_page.subtitle.en']" class="text-sm text-red-600">{{ form.errors['contact_page.subtitle.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact_page_subtitle_ar">Subtitle (AR)</Label>
                            <textarea id="contact_page_subtitle_ar" v-model="form.contact_page.subtitle.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['contact_page.subtitle.ar']" class="text-sm text-red-600">{{ form.errors['contact_page.subtitle.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="contact_page_form_title_en">Form Title (EN)</Label>
                            <Input id="contact_page_form_title_en" v-model="form.contact_page.form_title.en" />
                            <p v-if="form.errors['contact_page.form_title.en']" class="text-sm text-red-600">{{ form.errors['contact_page.form_title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact_page_form_title_ar">Form Title (AR)</Label>
                            <Input id="contact_page_form_title_ar" v-model="form.contact_page.form_title.ar" dir="rtl" />
                            <p v-if="form.errors['contact_page.form_title.ar']" class="text-sm text-red-600">{{ form.errors['contact_page.form_title.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="contact_page_info_title_en">Sidebar Title (EN)</Label>
                            <Input id="contact_page_info_title_en" v-model="form.contact_page.info_title.en" />
                            <p v-if="form.errors['contact_page.info_title.en']" class="text-sm text-red-600">{{ form.errors['contact_page.info_title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact_page_info_title_ar">Sidebar Title (AR)</Label>
                            <Input id="contact_page_info_title_ar" v-model="form.contact_page.info_title.ar" dir="rtl" />
                            <p v-if="form.errors['contact_page.info_title.ar']" class="text-sm text-red-600">{{ form.errors['contact_page.info_title.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="contact_page_hours_en">Business Hours (EN)</Label>
                            <textarea id="contact_page_hours_en" v-model="form.contact_page.hours.en" rows="4" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p class="text-xs text-muted-foreground">Use new line for each row.</p>
                            <p v-if="form.errors['contact_page.hours.en']" class="text-sm text-red-600">{{ form.errors['contact_page.hours.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact_page_hours_ar">Business Hours (AR)</Label>
                            <textarea id="contact_page_hours_ar" v-model="form.contact_page.hours.ar" rows="4" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p class="text-xs text-muted-foreground">Use new line for each row.</p>
                            <p v-if="form.errors['contact_page.hours.ar']" class="text-sm text-red-600">{{ form.errors['contact_page.hours.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="contact_page_quick_links_title_en">Quick Links Title (EN)</Label>
                            <Input id="contact_page_quick_links_title_en" v-model="form.contact_page.quick_links_title.en" />
                            <p v-if="form.errors['contact_page.quick_links_title.en']" class="text-sm text-red-600">{{ form.errors['contact_page.quick_links_title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact_page_quick_links_title_ar">Quick Links Title (AR)</Label>
                            <Input id="contact_page_quick_links_title_ar" v-model="form.contact_page.quick_links_title.ar" dir="rtl" />
                            <p v-if="form.errors['contact_page.quick_links_title.ar']" class="text-sm text-red-600">{{ form.errors['contact_page.quick_links_title.ar'] }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border p-5 space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold">Contact & Footer (MVP)</h2>
                        <p class="text-sm text-muted-foreground">Basic public contact info and footer description.</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="contact_phone">Phone</Label>
                            <Input id="contact_phone" v-model="form.contact.phone" placeholder="+965 ..." />
                            <p v-if="form.errors['contact.phone']" class="text-sm text-red-600">{{ form.errors['contact.phone'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact_email">Email</Label>
                            <Input id="contact_email" v-model="form.contact.email" type="email" placeholder="hello@example.com" />
                            <p v-if="form.errors['contact.email']" class="text-sm text-red-600">{{ form.errors['contact.email'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="contact_address_en">Address (EN)</Label>
                            <Input id="contact_address_en" v-model="form.contact.address.en" />
                            <p v-if="form.errors['contact.address.en']" class="text-sm text-red-600">{{ form.errors['contact.address.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact_address_ar">Address (AR)</Label>
                            <Input id="contact_address_ar" v-model="form.contact.address.ar" dir="rtl" />
                            <p v-if="form.errors['contact.address.ar']" class="text-sm text-red-600">{{ form.errors['contact.address.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="footer_desc_en">Footer Description (EN)</Label>
                            <textarea id="footer_desc_en" v-model="form.footer.description.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['footer.description.en']" class="text-sm text-red-600">{{ form.errors['footer.description.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="footer_desc_ar">Footer Description (AR)</Label>
                            <textarea id="footer_desc_ar" v-model="form.footer.description.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['footer.description.ar']" class="text-sm text-red-600">{{ form.errors['footer.description.ar'] }}</p>
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
