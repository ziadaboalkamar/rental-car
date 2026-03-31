<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useTrans } from '@/composables/useTrans';
import { register as mainRegister } from '@/routes';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Check, ChevronDown, Menu, X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import heroMockup from '@/assets/hero-mockup.png';
import { type Plan } from '@/types';

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
    landingSettings: LandingSettings;
    plans: Plan[];
    tenantLogos: Array<{
        id: number;
        name: string;
        slug: string;
        logo_url: string | null;
    }>;
}>();

const page = usePage<any>();
const { t } = useTrans();
const appName = computed(() => page.props.name || 'Real Rent Car');

const navLinks = [
    { label: 'Features', href: '#features' },
    { label: 'Start in Minutes', href: '#how-it-works' },
    { label: 'Plans', href: '#pricing' },
    { label: 'FAQ', href: '#faq' },
];

const mobileOpen = ref(false);
const scrolled = ref(false);
const yearly = ref(false);
const currentYear = new Date().getFullYear();
const registerUrl = mainRegister().url;

const heroImage = computed(() => props.landingSettings.hero.image_url || heroMockup);

const onScroll = () => {
    scrolled.value = window.scrollY > 10;
};

const toggleMenu = () => {
    mobileOpen.value = !mobileOpen.value;
};

const closeMenu = () => {
    mobileOpen.value = false;
};

const toggleYearly = () => {
    yearly.value = !yearly.value;
};

const planPrice = (plan: Plan) => {
    if (yearly.value) {
        if (plan.yearly_price !== null && plan.yearly_price !== undefined) {
            return Number(plan.yearly_price);
        }

        return Math.round(Number(plan.monthly_price) * 0.8);
    }

    return Number(plan.monthly_price);
};

const money = (value: number) => {
    return Number(value).toFixed(2);
};

const tenantInitial = (name: string) => {
    return name?.trim()?.charAt(0)?.toUpperCase() || 'T';
};

onMounted(() => {
    onScroll();
    window.addEventListener('scroll', onScroll);
});

onUnmounted(() => {
    window.removeEventListener('scroll', onScroll);
});
</script>

<template>
    <Head :title="landingSettings.hero.title" />

    <div class="min-h-screen bg-background">
        <nav
            class="fixed left-0 right-0 top-0 z-50 transition-all duration-300"
            :class="
                scrolled
                    ? 'border-b border-border bg-background/80 shadow-sm backdrop-blur-lg'
                    : 'bg-transparent'
            "
        >
            <div class="section-container flex h-16 items-center justify-between">
                <a href="#" class="text-xl font-bold tracking-tight text-foreground">{{ appName }}</a>

                <div class="hidden items-center gap-8 md:flex">
                    <a
                        v-for="link in navLinks"
                        :key="link.href"
                        :href="link.href"
                        class="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                    >
                        {{ link.label }}
                    </a>
                    <Button as-child class="gradient-button rounded-full px-5" size="sm">
                        <Link :href="registerUrl">{{ t('landing.start_free_trial') }}</Link>
                    </Button>
                </div>

                <button
                    class="text-foreground md:hidden"
                    :aria-label="t('landing.toggle_menu')"
                    type="button"
                    @click="toggleMenu"
                >
                    <X v-if="mobileOpen" :size="22" />
                    <Menu v-else :size="22" />
                </button>
            </div>

            <div
                v-if="mobileOpen"
                class="animate-fade-in border-b border-border bg-background px-4 pb-4 md:hidden"
            >
                <a
                    v-for="link in navLinks"
                    :key="`mobile-${link.href}`"
                    :href="link.href"
                    class="block py-2 text-sm font-medium text-muted-foreground hover:text-foreground"
                    @click="closeMenu"
                >
                    {{ link.label }}
                </a>
                <Button as-child class="gradient-button mt-2 w-full rounded-full" size="sm">
                    <Link :href="registerUrl">{{ t('landing.start_free_trial') }}</Link>
                </Button>
            </div>
        </nav>

        <main>
            <section class="relative overflow-hidden pb-20 pt-32 md:pb-28 md:pt-40" style="background: var(--gradient-hero)">
                <div class="section-container">
                    <div class="mx-auto max-w-3xl text-center animate-reveal-up">
                        <h1 class="text-4xl font-extrabold leading-[1.1] tracking-tight text-foreground sm:text-5xl lg:text-6xl">
                            {{ landingSettings.hero.title }}
                        </h1>
                        <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-muted-foreground sm:text-xl">
                            {{ landingSettings.hero.description }}
                        </p>
                        <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                            <Button as-child size="lg" class="gradient-button h-12 rounded-full px-8 text-base">
                                <Link :href="registerUrl">{{ t('landing.start_free_trial') }}</Link>
                            </Button>
                            <a
                                href="#pricing"
                                class="inline-flex h-12 items-center justify-center rounded-full border border-input px-8 text-base font-medium hover:bg-accent"
                            >
                                {{ t('landing.see_pricing') }}
                            </a>
                        </div>

                        <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                            <div
                                v-for="feature in landingSettings.hero.features"
                                :key="feature"
                                class="rounded-full border border-border bg-background/60 px-4 py-1.5 text-sm text-muted-foreground"
                            >
                                {{ feature }}
                            </div>
                        </div>
                    </div>

                    <div class="mx-auto mt-16 max-w-5xl animate-reveal-up-delay">
                        <div class="card-elevated overflow-hidden rounded-2xl p-1">
                            <img
                                :src="heroImage"
                                alt="Hero"
                                class="w-full rounded-xl"
                                loading="eager"
                            >
                        </div>
                    </div>
                </div>
            </section>

            <section class="section-padding border-b border-border">
                <div class="section-container">
                    <p class="mb-8 text-center text-sm font-medium text-muted-foreground">Our Clients</p>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div
                            v-for="tenant in tenantLogos"
                            :key="tenant.id"
                            class="card-elevated flex items-center gap-3 rounded-xl p-4"
                        >
                            <img
                                v-if="tenant.logo_url"
                                :src="tenant.logo_url"
                                :alt="tenant.name"
                                class="h-10 w-10 rounded-full object-cover"
                            >
                            <div
                                v-else
                                class="bg-primary/10 text-primary flex h-10 w-10 items-center justify-center rounded-full text-sm font-semibold"
                            >
                                {{ tenantInitial(tenant.name) }}
                            </div>
                            <div class="font-medium text-foreground">{{ tenant.name }}</div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="features" class="section-padding bg-secondary/30">
                <div class="section-container">
                    <div class="mx-auto mb-14 max-w-2xl text-center">
                        <h2 class="text-3xl font-bold text-foreground sm:text-4xl">{{ landingSettings.features_section.title }}</h2>
                        <p class="mt-4 text-lg text-muted-foreground">{{ landingSettings.features_section.description }}</p>
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="card in landingSettings.features_section.cards"
                            :key="`${card.title}-${card.content}`"
                            class="card-elevated rounded-xl p-6"
                        >
                            <img
                                v-if="card.image_url"
                                :src="card.image_url"
                                :alt="card.title"
                                class="mb-4 h-40 w-full rounded-lg object-cover"
                            >
                            <h3 class="mb-2 text-lg font-semibold text-foreground">{{ card.title }}</h3>
                            <p class="text-sm leading-relaxed text-muted-foreground">{{ card.content }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section id="how-it-works" class="section-padding">
                <div class="section-container">
                    <div class="mx-auto mb-14 max-w-2xl text-center">
                        <h2 class="text-3xl font-bold text-foreground sm:text-4xl">{{ landingSettings.getting_started.title }}</h2>
                        <p class="mt-4 text-lg text-muted-foreground">{{ landingSettings.getting_started.description }}</p>
                    </div>

                    <div class="mx-auto grid max-w-5xl gap-8 md:grid-cols-3">
                        <div v-for="(item, index) in landingSettings.getting_started.items" :key="`${item.title}-${index}`" class="text-center">
                            <div class="gradient-button mx-auto mb-5 flex h-12 w-12 items-center justify-center rounded-full text-lg font-bold">
                                {{ index + 1 }}
                            </div>
                            <h3 class="mb-2 text-lg font-semibold text-foreground">{{ item.title }}</h3>
                            <p class="text-sm leading-relaxed text-muted-foreground">{{ item.description }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section id="pricing" class="section-padding bg-secondary/30">
                <div class="section-container">
                    <div class="mx-auto mb-10 max-w-2xl text-center">
                        <h2 class="text-3xl font-bold text-foreground sm:text-4xl">{{ landingSettings.plans_section.title }}</h2>
                        <p class="mt-4 text-lg text-muted-foreground">{{ landingSettings.plans_section.description }}</p>
                    </div>

                    <div class="mb-12 flex items-center justify-center gap-3">
                        <span class="text-sm font-medium" :class="!yearly ? 'text-foreground' : 'text-muted-foreground'">{{ t('landing.monthly') }}</span>
                        <button
                            class="relative h-6 w-12 rounded-full transition-colors"
                            :class="yearly ? 'bg-primary' : 'bg-border'"
                            :aria-label="t('landing.toggle_yearly_pricing')"
                            type="button"
                            @click="toggleYearly"
                        >
                            <span
                                class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-primary-foreground transition-transform"
                                :class="yearly ? 'translate-x-6' : ''"
                            />
                        </button>
                        <span class="text-sm font-medium" :class="yearly ? 'text-foreground' : 'text-muted-foreground'">{{ t('landing.yearly') }}</span>
                    </div>

                    <div class="mx-auto grid max-w-6xl gap-6 md:grid-cols-3">
                        <div
                            v-for="plan in plans"
                            :key="plan.id"
                            class="card-elevated flex flex-col rounded-xl p-6"
                        >
                            <h3 class="text-lg font-semibold text-foreground">{{ plan.name }}</h3>
                            <p class="mb-4 text-sm text-muted-foreground">{{ plan.description || '' }}</p>

                            <div class="mb-6">
                                <span class="text-4xl font-extrabold text-foreground">${{ money(planPrice(plan)) }}</span>
                                <span class="text-sm text-muted-foreground">/{{ yearly ? t('landing.yearly') : t('landing.monthly') }}</span>
                            </div>

                            <ul class="mb-8 flex-1 space-y-3">
                                <li v-for="feature in (plan.features || [])" :key="feature" class="flex items-start gap-2 text-sm text-muted-foreground">
                                    <Check :size="16" class="mt-0.5 shrink-0 text-primary" />
                                    {{ feature }}
                                </li>
                            </ul>

                            <Button as-child class="gradient-button w-full rounded-full">
                                <Link :href="registerUrl">{{ t('landing.start_free_trial') }}</Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </section>

            <section id="faq" class="section-padding">
                <div class="section-container mx-auto max-w-3xl">
                    <div class="mb-12 text-center">
                        <h2 class="text-3xl font-bold text-foreground sm:text-4xl">{{ landingSettings.faq_section.title }}</h2>
                        <p class="mt-4 text-lg text-muted-foreground">{{ landingSettings.faq_section.description }}</p>
                    </div>

                    <div class="space-y-3">
                        <details
                            v-for="faq in landingSettings.faq_section.items"
                            :key="`${faq.question}-${faq.answer}`"
                            class="card-elevated faq-item rounded-lg border px-5"
                        >
                            <summary class="flex cursor-pointer list-none items-center justify-between py-4 font-medium text-foreground">
                                <span>{{ faq.question }}</span>
                                <ChevronDown :size="18" class="faq-chevron text-muted-foreground" />
                            </summary>
                            <p class="pb-4 text-muted-foreground">{{ faq.answer }}</p>
                        </details>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-border py-10">
            <div class="section-container text-center">
                <h3 class="text-2xl font-bold text-foreground">{{ landingSettings.footer.title }}</h3>
                <p class="mx-auto mt-3 max-w-2xl text-muted-foreground">{{ landingSettings.footer.description }}</p>
                <p class="mt-6 text-sm text-muted-foreground">&copy; {{ currentYear }} {{ appName }}. {{ t('landing.footer_rights') }}</p>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.faq-item .faq-chevron {
    transition: transform 0.2s ease;
}

.faq-item[open] .faq-chevron {
    transform: rotate(180deg);
}
</style>
