<script setup lang="ts">
import authHero from '@/assets/auth-hero.jpg';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { landing as mainAuthLanding } from '@/routes/auth';
import { store as mainRegisterStore } from '@/routes/register';
import { landing as tenantAuthLanding } from '@/routes/tenant/auth';
import { store as tenantRegisterStore } from '@/routes/tenant/register';
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage<any>();
const currentTenant = computed(() => page.props.current_tenant);

type RegisterPrefill = {
    name?: string | null;
    email?: string | null;
    custom_domain?: string | null;
    phone?: string | null;
};

const props = withDefaults(defineProps<{ prefill?: RegisterPrefill }>(), {
    prefill: () => ({}),
});

const registerAction = computed(() => {
    const slug = currentTenant.value?.slug;
    return (slug ? tenantRegisterStore(slug) : mainRegisterStore()) as any;
});

const loginUrl = computed(() => {
    const slug = currentTenant.value?.slug;
    return slug
        ? `http://${slug}.${page.props.app_url_base}/tenant/login`
        : `http://${page.props.app_url_base}/tenant/login`;
});

const landingUrl = computed(() => {
    const slug = currentTenant.value?.slug;
    return slug ? tenantAuthLanding(slug).url : mainAuthLanding().url;
});

const initial = computed(() => ({
    name: props.prefill?.name ?? '',
    email: props.prefill?.email ?? '',
    custom_domain: props.prefill?.custom_domain ?? '',
    phone: props.prefill?.phone ?? '',
}));
</script>

<template>
    <Head title="Sign Up" />

    <div class="flex min-h-screen bg-white">
        <div class="relative hidden overflow-hidden lg:flex lg:w-1/2">
            <div
                class="absolute inset-0 z-10 bg-gradient-to-br from-blue-700/80 to-blue-500/70"
            />
            <img
                :src="authHero"
                alt="Professional workspace"
                class="absolute inset-0 h-full w-full object-cover"
            />
        </div>

        <div
            class="flex w-full items-center justify-center p-6 sm:p-8 lg:w-1/2"
        >
            <div class="w-full max-w-md space-y-6">
                <div class="space-y-2">
                    <h1 class="text-3xl font-bold text-gray-900">Sign Up</h1>
                    <p class="text-gray-500">
                        Create your account to get started.
                    </p>
                </div>

                <Form
                    v-bind="registerAction"
                    :reset-on-success="['password', 'password_confirmation']"
                    v-slot="{ errors, processing }"
                    class="space-y-4"
                >
                    <div class="space-y-2">
                        <Label
                            for="name"
                            class="text-sm font-semibold text-gray-800"
                            >Company Name</Label
                        >
                        <Input
                            id="name"
                            name="name"
                            type="text"
                            placeholder="Your company..."
                            :default-value="initial.name"
                            required
                            autofocus
                            autocomplete="name"
                            class="h-11 border-gray-300"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="space-y-2">
                        <Label
                            for="email"
                            class="text-sm font-semibold text-gray-800"
                            >Email</Label
                        >
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            placeholder="Email address..."
                            :default-value="initial.email"
                            required
                            autocomplete="email"
                            class="h-11 border-gray-300"
                        />
                        <InputError :message="errors.email" />
                    </div>

                    <div class="space-y-2">
                        <Label
                            for="custom_domain"
                            class="text-sm font-semibold text-gray-800"
                        >
                            Custom Domain
                            <span class="font-normal text-gray-500"
                                >(optional)</span
                            >
                        </Label>
                        <Input
                            id="custom_domain"
                            name="custom_domain"
                            type="text"
                            placeholder="yourdomain.com"
                            :default-value="initial.custom_domain"
                            class="h-11 border-gray-300"
                        />
                        <InputError :message="errors.custom_domain" />
                    </div>

                    <div class="space-y-2">
                        <Label
                            for="phone"
                            class="text-sm font-semibold text-gray-800"
                            >Phone Number</Label
                        >
                        <Input
                            id="phone"
                            name="phone"
                            type="tel"
                            placeholder="+1 (555) 000-0000"
                            :default-value="initial.phone"
                            class="h-11 border-gray-300"
                        />
                        <InputError :message="errors.phone" />
                    </div>

                    <div class="space-y-2">
                        <Label
                            for="password"
                            class="text-sm font-semibold text-gray-800"
                            >Password</Label
                        >
                        <Input
                            id="password"
                            name="password"
                            type="password"
                            placeholder="**********"
                            required
                            autocomplete="new-password"
                            class="h-11 border-gray-300"
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <div class="space-y-2">
                        <Label
                            for="password_confirmation"
                            class="text-sm font-semibold text-gray-800"
                        >
                            Repeat Password
                        </Label>
                        <Input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            placeholder="**********"
                            required
                            autocomplete="new-password"
                            class="h-11 border-gray-300"
                        />
                        <InputError :message="errors.password_confirmation" />
                    </div>

                    <label
                        for="terms"
                        class="flex items-center gap-2 text-sm text-gray-600"
                    >
                        <input
                            id="terms"
                            type="checkbox"
                            required
                            class="h-4 w-4 rounded border-gray-300 text-blue-700 focus:ring-blue-600"
                        />
                        I agree to the
                        <a
                            href="#"
                            class="font-medium text-blue-700 hover:underline"
                            >Terms of Use</a
                        >
                    </label>

                    <button
                        type="submit"
                        class="h-12 w-full rounded-full bg-gradient-to-r from-blue-700 to-blue-500 font-semibold text-white shadow-sm transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="processing"
                    >
                        {{ processing ? 'Creating Account...' : 'Sign Up' }}
                    </button>
                </Form>

                <p class="text-center text-sm text-gray-600">
                    Already have an account?
                    <Link
                        :href="loginUrl"
                        class="ml-1 font-semibold text-blue-700 hover:underline"
                    >
                        Sign In ->
                    </Link>
                </p>

            
            </div>
        </div>
    </div>
</template>
