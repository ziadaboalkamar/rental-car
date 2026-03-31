<script setup lang="ts">
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { computed, ref } from 'vue';
import { ExternalLink, RefreshCw } from 'lucide-vue-next';

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

const props = defineProps<{
    settings: LandingSettings;
    previewUrl: string;
}>();

const previewNonce = ref(Date.now());

const form = useForm<{
    settings: LandingSettings;
}>({
    settings: JSON.parse(JSON.stringify(props.settings)),
});

const previewSrc = computed(() => {
    const separator = props.previewUrl.includes('?') ? '&' : '?';
    return `${props.previewUrl}${separator}preview=${previewNonce.value}`;
});

const refreshPreview = () => {
    previewNonce.value = Date.now();
};

const submit = () => {
    form.put('/superadmin/settings/design', {
        preserveScroll: true,
        onSuccess: () => {
            refreshPreview();
        },
    });
};

const openPreview = () => {
    window.open(previewSrc.value, '_blank', 'noopener,noreferrer');
};

const addHeroFeature = () => form.settings.hero.features.push('');
const removeHeroFeature = (index: number) => form.settings.hero.features.splice(index, 1);

const addFeatureCard = () => {
    form.settings.features_section.cards.push({
        title: '',
        image_url: '',
        content: '',
    });
};
const removeFeatureCard = (index: number) => form.settings.features_section.cards.splice(index, 1);

const addStepItem = () => {
    form.settings.getting_started.items.push({
        title: '',
        description: '',
    });
};
const removeStepItem = (index: number) => form.settings.getting_started.items.splice(index, 1);

const addFaqItem = () => {
    form.settings.faq_section.items.push({
        question: '',
        answer: '',
    });
};
const removeFaqItem = (index: number) => form.settings.faq_section.items.splice(index, 1);
</script>

<template>
    <Head title="Design Settings" />
    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">Design Settings</h1>
                    <p class="text-sm text-muted-foreground">
                        Edit the public landing page and preview the design from inside super admin.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button type="button" variant="outline" @click="refreshPreview">
                        <RefreshCw class="mr-2 h-4 w-4" />
                        Refresh Preview
                    </Button>
                    <Button type="button" variant="outline" @click="openPreview">
                        <ExternalLink class="mr-2 h-4 w-4" />
                        Open Full Preview
                    </Button>
                    <Button :disabled="form.processing" @click="submit">
                        {{ form.processing ? 'Saving...' : 'Save Design' }}
                    </Button>
                </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.05fr)_minmax(420px,0.95fr)]">
                <form class="space-y-6" @submit.prevent="submit">
                    <Card>
                        <CardHeader>
                            <CardTitle>Hero</CardTitle>
                            <CardDescription>Main title, description, image, and quick highlights.</CardDescription>
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
                                <Textarea id="hero_description" v-model="form.settings.hero.description" rows="4" />
                                <p v-if="form.errors['settings.hero.description']" class="text-sm text-red-600">
                                    {{ form.errors['settings.hero.description'] }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="hero_image_url">Hero Image URL</Label>
                                <Input id="hero_image_url" v-model="form.settings.hero.image_url" placeholder="https://..." />
                            </div>

                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <Label>Hero Features</Label>
                                    <Button type="button" variant="outline" size="sm" @click="addHeroFeature">Add Feature</Button>
                                </div>
                                <div
                                    v-for="(_item, index) in form.settings.hero.features"
                                    :key="`hero-feature-${index}`"
                                    class="flex items-center gap-2"
                                >
                                    <Input v-model="form.settings.hero.features[index]" placeholder="Feature text" />
                                    <Button type="button" variant="destructive" size="sm" @click="removeHeroFeature(index)">Remove</Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Features Section</CardTitle>
                            <CardDescription>Section intro and the feature cards shown on the landing page.</CardDescription>
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
                                    class="space-y-3 rounded-lg border p-4"
                                >
                                    <div class="space-y-2">
                                        <Label>Card Title</Label>
                                        <Input v-model="card.title" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>Image URL</Label>
                                        <Input v-model="card.image_url" placeholder="https://..." />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>Content</Label>
                                        <Textarea v-model="card.content" rows="3" />
                                    </div>
                                    <div class="flex justify-end">
                                        <Button type="button" variant="destructive" size="sm" @click="removeFeatureCard(index)">Remove Card</Button>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Getting Started</CardTitle>
                            <CardDescription>Control the section that explains the setup steps.</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2">
                                <Label>Section Title</Label>
                                <Input v-model="form.settings.getting_started.title" />
                            </div>
                            <div class="space-y-2">
                                <Label>Section Description</Label>
                                <Textarea v-model="form.settings.getting_started.description" rows="3" />
                            </div>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <Label>Steps</Label>
                                    <Button type="button" variant="outline" size="sm" @click="addStepItem">Add Step</Button>
                                </div>
                                <div
                                    v-for="(item, index) in form.settings.getting_started.items"
                                    :key="`step-${index}`"
                                    class="space-y-3 rounded-lg border p-4"
                                >
                                    <div class="space-y-2">
                                        <Label>Step Title</Label>
                                        <Input v-model="item.title" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>Step Description</Label>
                                        <Textarea v-model="item.description" rows="2" />
                                    </div>
                                    <div class="flex justify-end">
                                        <Button type="button" variant="destructive" size="sm" @click="removeStepItem(index)">Remove Step</Button>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Plans & FAQ</CardTitle>
                            <CardDescription>Pricing section heading plus FAQ content.</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label>Plans Title</Label>
                                    <Input v-model="form.settings.plans_section.title" />
                                </div>
                                <div class="space-y-2">
                                    <Label>Plans Description</Label>
                                    <Textarea v-model="form.settings.plans_section.description" rows="2" />
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label>FAQ Title</Label>
                                <Input v-model="form.settings.faq_section.title" />
                            </div>
                            <div class="space-y-2">
                                <Label>FAQ Description</Label>
                                <Textarea v-model="form.settings.faq_section.description" rows="2" />
                            </div>

                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <Label>FAQ Items</Label>
                                    <Button type="button" variant="outline" size="sm" @click="addFaqItem">Add FAQ</Button>
                                </div>
                                <div
                                    v-for="(faq, index) in form.settings.faq_section.items"
                                    :key="`faq-${index}`"
                                    class="space-y-3 rounded-lg border p-4"
                                >
                                    <div class="space-y-2">
                                        <Label>Question</Label>
                                        <Input v-model="faq.question" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>Answer</Label>
                                        <Textarea v-model="faq.answer" rows="3" />
                                    </div>
                                    <div class="flex justify-end">
                                        <Button type="button" variant="destructive" size="sm" @click="removeFaqItem(index)">Remove FAQ</Button>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Footer</CardTitle>
                            <CardDescription>Final call to action shown at the bottom of the landing page.</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2">
                                <Label>Footer Title</Label>
                                <Input v-model="form.settings.footer.title" />
                            </div>
                            <div class="space-y-2">
                                <Label>Footer Description</Label>
                                <Textarea v-model="form.settings.footer.description" rows="3" />
                            </div>
                        </CardContent>
                    </Card>
                </form>

                <div class="space-y-4">
                    <Card class="sticky top-6 overflow-hidden">
                        <CardHeader class="border-b">
                            <CardTitle>Live Preview</CardTitle>
                            <CardDescription>
                                The preview renders the public landing page on the main domain. Save changes to refresh it.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="p-0">
                            <iframe
                                :key="previewSrc"
                                :src="previewSrc"
                                class="h-[calc(100vh-16rem)] min-h-[720px] w-full bg-white"
                                title="Landing page preview"
                            />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </main>
    </SuperAdminLayout>
</template>
