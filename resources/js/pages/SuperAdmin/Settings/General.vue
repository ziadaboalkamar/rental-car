<script setup lang="ts">
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ref } from 'vue';

interface FeatureCard {
    title: string;
    image_url: string;
    content: string;
}

interface StepItem {
    title: string;
    description: string;
}

interface FaqItem {
    question: string;
    answer: string;
}

interface LandingSettings {
    hero: {
        title: string;
        description: string;
        features: string[];
        image_url: string;
    };
    features_section: {
        title: string;
        description: string;
        cards: FeatureCard[];
    };
    getting_started: {
        title: string;
        description: string;
        items: StepItem[];
    };
    plans_section: {
        title: string;
        description: string;
    };
    faq_section: {
        title: string;
        description: string;
        items: FaqItem[];
    };
    footer: {
        title: string;
        description: string;
    };
}

interface AiProviderSettings {
    provider: 'openai' | 'google_document_ai';
    openai: {
        api_key: string;
        organization: string;
        project: string;
        base_uri: string;
        model: string;
        temperature: number;
        max_output_tokens: number;
        system_prompt: string;
    };
    google_document_ai: {
        enabled: boolean;
        project_id: string;
        location: string;
        processor_id: string;
        service_account_json: string;
    };
    meta?: {
        has_openai_api_key?: boolean;
        has_google_credentials?: boolean;
    };
}

interface SocialLoginSettings {
    google: {
        enabled: boolean;
        client_id: string;
        client_secret: string;
    };
    apple: {
        enabled: boolean;
        client_id: string;
        client_secret: string;
    };
}

const props = defineProps<{
    settings: LandingSettings;
    aiSettings: {
        enabled: boolean;
        contracts_extraction_enabled: boolean;
    };
    aiProviderSettings: AiProviderSettings;
    socialLoginSettings: SocialLoginSettings;
}>();

const form = useForm<{
    settings: LandingSettings;
    ai: {
        enabled: boolean;
        contracts_extraction_enabled: boolean;
    };
    ai_provider: AiProviderSettings;
    social_login: SocialLoginSettings;
}>({
    settings: JSON.parse(JSON.stringify(props.settings)),
    ai: {
        enabled: !!props.aiSettings?.enabled,
        contracts_extraction_enabled: !!props.aiSettings?.contracts_extraction_enabled,
    },
    ai_provider: JSON.parse(JSON.stringify(props.aiProviderSettings || {})),
    social_login: JSON.parse(JSON.stringify(props.socialLoginSettings || {
        google: { enabled: false, client_id: '', client_secret: '' },
        apple: { enabled: false, client_id: '', client_secret: '' },
    })),
});

const addHeroFeature = () => {
    form.settings.hero.features.push('');
};

const removeHeroFeature = (index: number) => {
    form.settings.hero.features.splice(index, 1);
};

const addFeatureCard = () => {
    form.settings.features_section.cards.push({
        title: '',
        image_url: '',
        content: '',
    });
};

const removeFeatureCard = (index: number) => {
    form.settings.features_section.cards.splice(index, 1);
};

const addStepItem = () => {
    form.settings.getting_started.items.push({
        title: '',
        description: '',
    });
};

const removeStepItem = (index: number) => {
    form.settings.getting_started.items.splice(index, 1);
};

const addFaqItem = () => {
    form.settings.faq_section.items.push({
        question: '',
        answer: '',
    });
};

const removeFaqItem = (index: number) => {
    form.settings.faq_section.items.splice(index, 1);
};

const submit = () => {
    const updateUrl = typeof window !== 'undefined' ? window.location.pathname : '/superadmin/settings/general';

    form.put(updateUrl, {
        preserveScroll: true,
    });
};

const testingAiConnection = ref(false);
const aiConnectionTestState = ref<'idle' | 'success' | 'error'>('idle');
const aiConnectionTestMessage = ref('');

function extractFirstValidationError(errors: unknown): string | null {
    if (!errors || typeof errors !== 'object') {
        return null;
    }

    const values = Object.values(errors as Record<string, unknown>);
    for (const value of values) {
        if (Array.isArray(value) && typeof value[0] === 'string') {
            return value[0];
        }
    }

    return null;
}

async function testAiConnection() {
    if (testingAiConnection.value || typeof window === 'undefined') return;

    testingAiConnection.value = true;
    aiConnectionTestState.value = 'idle';
    aiConnectionTestMessage.value = '';

    const csrfToken = document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute('content');
    const xsrfToken = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    const basePath = window.location.pathname.replace(/\/$/, '');
    const testUrl = `${basePath}/test-ai-connection`;

    try {
        const response = await fetch(testUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                ...(xsrfToken ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfToken) } : {}),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                ai_provider: form.ai_provider,
            }),
        });

        const payload = await response.json().catch(() => null);
        if (!response.ok || !payload?.ok) {
            const firstValidationError = extractFirstValidationError(payload?.errors);
            aiConnectionTestState.value = 'error';
            aiConnectionTestMessage.value = firstValidationError ?? payload?.message ?? 'AI connection test failed.';
            return;
        }

        aiConnectionTestState.value = 'success';
        aiConnectionTestMessage.value = payload?.message ?? 'AI connection is valid.';
    } catch {
        aiConnectionTestState.value = 'error';
        aiConnectionTestMessage.value = 'Could not test AI connection. Please try again.';
    } finally {
        testingAiConnection.value = false;
    }
}
</script>

<template>
    <Head title="Landing Settings" />
    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">Landing Page Settings</h1>
                    <p class="text-sm text-muted-foreground">
                        Edit SaaS landing sections shown on the main domain.
                    </p>
                </div>
                <Button :disabled="form.processing" @click="submit">
                    {{ form.processing ? 'Saving...' : 'Save Changes' }}
                </Button>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <Card>
                    <CardHeader>
                        <CardTitle>AI Automation</CardTitle>
                        <CardDescription>
                            Super Admin controls whether AI extraction is active for contract files.
                            When disabled, the system only stores uploaded files.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <label class="flex items-center gap-3">
                            <input v-model="form.ai.enabled" type="checkbox" class="h-4 w-4" />
                            <span class="text-sm font-medium">Enable AI globally</span>
                        </label>

                        <label class="flex items-center gap-3">
                            <input
                                v-model="form.ai.contracts_extraction_enabled"
                                type="checkbox"
                                class="h-4 w-4"
                                :disabled="!form.ai.enabled"
                            />
                            <span class="text-sm font-medium">Enable contract extraction AI</span>
                        </label>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>AI Provider Settings</CardTitle>
                        <CardDescription>
                            Configure provider credentials and extraction behavior.
                            Empty secret fields keep existing saved values.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-xs text-muted-foreground">
                                Test provider credentials before saving.
                            </p>
                            <Button type="button" variant="outline" :disabled="testingAiConnection" @click="testAiConnection">
                                {{ testingAiConnection ? 'Testing...' : 'Test AI Connection' }}
                            </Button>
                        </div>

                        <div
                            v-if="aiConnectionTestMessage"
                            class="rounded-md border p-3 text-sm"
                            :class="aiConnectionTestState === 'success'
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                : 'border-red-200 bg-red-50 text-red-700'"
                        >
                            {{ aiConnectionTestMessage }}
                        </div>

                        <div class="space-y-2">
                            <Label for="ai_provider">Provider</Label>
                            <select
                                id="ai_provider"
                                v-model="form.ai_provider.provider"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 dark:bg-input/30"
                            >
                                <option value="openai">OpenAI</option>
                                <option value="google_document_ai">Google Document AI</option>
                            </select>
                            <p v-if="form.errors['ai_provider.provider']" class="text-sm text-red-600">
                                {{ form.errors['ai_provider.provider'] }}
                            </p>
                        </div>

                        <div class="space-y-4 rounded-md border p-4">
                            <h3 class="text-sm font-semibold">OpenAI</h3>

                            <div class="space-y-2">
                                <Label for="openai_api_key">API Key</Label>
                                <Input id="openai_api_key" v-model="form.ai_provider.openai.api_key" type="password" placeholder="sk-..." />
                                <p v-if="props.aiProviderSettings.meta?.has_openai_api_key" class="text-xs text-muted-foreground">
                                    A key is already saved. Leave blank to keep it.
                                </p>
                                <p v-if="form.errors['ai_provider.openai.api_key']" class="text-sm text-red-600">
                                    {{ form.errors['ai_provider.openai.api_key'] }}
                                </p>
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="openai_organization">Organization (optional)</Label>
                                    <Input id="openai_organization" v-model="form.ai_provider.openai.organization" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="openai_project">Project (optional)</Label>
                                    <Input id="openai_project" v-model="form.ai_provider.openai.project" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="openai_model">Model</Label>
                                    <Input id="openai_model" v-model="form.ai_provider.openai.model" placeholder="gpt-4.1-mini" />
                                    <p v-if="form.errors['ai_provider.openai.model']" class="text-sm text-red-600">
                                        {{ form.errors['ai_provider.openai.model'] }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="openai_base_uri">Base URL (optional)</Label>
                                    <Input id="openai_base_uri" v-model="form.ai_provider.openai.base_uri" placeholder="https://api.openai.com/v1" />
                                    <p v-if="form.errors['ai_provider.openai.base_uri']" class="text-sm text-red-600">
                                        {{ form.errors['ai_provider.openai.base_uri'] }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="openai_temperature">Temperature (0-2)</Label>
                                    <Input id="openai_temperature" v-model="form.ai_provider.openai.temperature" type="number" min="0" max="2" step="0.1" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="openai_tokens">Max Output Tokens</Label>
                                    <Input id="openai_tokens" v-model="form.ai_provider.openai.max_output_tokens" type="number" min="1" max="16384" />
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="openai_system_prompt">System Prompt (optional)</Label>
                                <Textarea
                                    id="openai_system_prompt"
                                    v-model="form.ai_provider.openai.system_prompt"
                                    rows="4"
                                    placeholder="Extract key fields from Arabic and English rental contract files as JSON."
                                />
                            </div>
                        </div>

                        <div class="space-y-4 rounded-md border p-4">
                            <h3 class="text-sm font-semibold">Google Document AI</h3>

                            <label class="flex items-center gap-3">
                                <input v-model="form.ai_provider.google_document_ai.enabled" type="checkbox" class="h-4 w-4" />
                                <span class="text-sm font-medium">Enable Google Document AI OCR</span>
                            </label>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div class="space-y-2">
                                    <Label for="gdoc_project_id">Project ID</Label>
                                    <Input id="gdoc_project_id" v-model="form.ai_provider.google_document_ai.project_id" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="gdoc_location">Location</Label>
                                    <Input id="gdoc_location" v-model="form.ai_provider.google_document_ai.location" placeholder="us" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="gdoc_processor_id">Processor ID</Label>
                                    <Input id="gdoc_processor_id" v-model="form.ai_provider.google_document_ai.processor_id" />
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="gdoc_credentials_json">Service Account JSON</Label>
                                <Textarea
                                    id="gdoc_credentials_json"
                                    v-model="form.ai_provider.google_document_ai.service_account_json"
                                    rows="5"
                                    placeholder='{"type":"service_account","project_id":"..."}'
                                />
                                <p v-if="props.aiProviderSettings.meta?.has_google_credentials" class="text-xs text-muted-foreground">
                                    Credentials are already saved. Leave blank to keep them.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Hero Section</CardTitle>
                        <CardDescription>Title, description, hero features, and image URL.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="hero_title">Title</Label>
                            <Input id="hero_title" v-model="form.settings.hero.title" />
                            <p v-if="form.errors['settings.hero.title']" class="text-sm text-red-600">
                                {{ form.errors['settings.hero.title'] }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="hero_description">Description</Label>
                            <Textarea id="hero_description" v-model="form.settings.hero.description" rows="3" />
                            <p v-if="form.errors['settings.hero.description']" class="text-sm text-red-600">
                                {{ form.errors['settings.hero.description'] }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="hero_image_url">Image URL</Label>
                            <Input id="hero_image_url" v-model="form.settings.hero.image_url" placeholder="https://..." />
                            <p v-if="form.errors['settings.hero.image_url']" class="text-sm text-red-600">
                                {{ form.errors['settings.hero.image_url'] }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <Label>Hero Features</Label>
                                <Button type="button" variant="outline" size="sm" @click="addHeroFeature">Add Feature</Button>
                            </div>
                            <div v-for="(_item, index) in form.settings.hero.features" :key="`hero-feature-${index}`" class="flex items-center gap-2">
                                <Input v-model="form.settings.hero.features[index]" placeholder="Feature text" />
                                <Button type="button" variant="destructive" size="sm" @click="removeHeroFeature(index)">Remove</Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Features Section</CardTitle>
                        <CardDescription>Section title/description and feature cards.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="features_title">Title</Label>
                            <Input id="features_title" v-model="form.settings.features_section.title" />
                        </div>

                        <div class="space-y-2">
                            <Label for="features_description">Description</Label>
                            <Textarea id="features_description" v-model="form.settings.features_section.description" rows="3" />
                        </div>

                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <Label>Feature Cards</Label>
                                <Button type="button" variant="outline" size="sm" @click="addFeatureCard">Add Card</Button>
                            </div>

                            <div
                                v-for="(card, index) in form.settings.features_section.cards"
                                :key="`feature-card-${index}`"
                                class="space-y-2 rounded-md border p-3"
                            >
                                <Input v-model="card.title" placeholder="Card title" />
                                <Input v-model="card.image_url" placeholder="Image URL" />
                                <Textarea v-model="card.content" rows="2" placeholder="Card content" />
                                <Button type="button" variant="destructive" size="sm" @click="removeFeatureCard(index)">Remove Card</Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Start in Minutes Section</CardTitle>
                        <CardDescription>Section title/description and quick start features.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="start_title">Title</Label>
                            <Input id="start_title" v-model="form.settings.getting_started.title" />
                        </div>

                        <div class="space-y-2">
                            <Label for="start_description">Description</Label>
                            <Textarea id="start_description" v-model="form.settings.getting_started.description" rows="3" />
                        </div>

                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <Label>Items</Label>
                                <Button type="button" variant="outline" size="sm" @click="addStepItem">Add Item</Button>
                            </div>

                            <div
                                v-for="(item, index) in form.settings.getting_started.items"
                                :key="`step-item-${index}`"
                                class="space-y-2 rounded-md border p-3"
                            >
                                <Input v-model="item.title" placeholder="Item title" />
                                <Textarea v-model="item.description" rows="2" placeholder="Item description" />
                                <Button type="button" variant="destructive" size="sm" @click="removeStepItem(index)">Remove Item</Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Plans Section</CardTitle>
                        <CardDescription>
                            Only heading and description are editable here. Plans are loaded from the plans table.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="plans_title">Title</Label>
                            <Input id="plans_title" v-model="form.settings.plans_section.title" />
                        </div>

                        <div class="space-y-2">
                            <Label for="plans_description">Description</Label>
                            <Textarea id="plans_description" v-model="form.settings.plans_section.description" rows="3" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>FAQ Section</CardTitle>
                        <CardDescription>Manage questions and answers.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="faq_title">Title</Label>
                            <Input id="faq_title" v-model="form.settings.faq_section.title" />
                        </div>

                        <div class="space-y-2">
                            <Label for="faq_description">Description</Label>
                            <Textarea id="faq_description" v-model="form.settings.faq_section.description" rows="3" />
                        </div>

                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <Label>FAQ Items</Label>
                                <Button type="button" variant="outline" size="sm" @click="addFaqItem">Add FAQ</Button>
                            </div>

                            <div
                                v-for="(item, index) in form.settings.faq_section.items"
                                :key="`faq-item-${index}`"
                                class="space-y-2 rounded-md border p-3"
                            >
                                <Input v-model="item.question" placeholder="Question" />
                                <Textarea v-model="item.answer" rows="3" placeholder="Answer" />
                                <Button type="button" variant="destructive" size="sm" @click="removeFaqItem(index)">Remove FAQ</Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Footer Section</CardTitle>
                        <CardDescription>Footer title and description text.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="footer_title">Title</Label>
                            <Input id="footer_title" v-model="form.settings.footer.title" />
                        </div>

                        <div class="space-y-2">
                            <Label for="footer_description">Description</Label>
                            <Textarea id="footer_description" v-model="form.settings.footer.description" rows="3" />
                        </div>
                    </CardContent>
                </Card>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : 'Save Changes' }}
                    </Button>
                </div>
            </form>
        </main>
    </SuperAdminLayout>
</template>
