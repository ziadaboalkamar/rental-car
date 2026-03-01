<script setup lang="ts">
import authHero from '@/assets/auth-hero.jpg';
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { register as mainRegister } from '@/routes';
import { landing as mainAuthLanding } from '@/routes/auth';
import { request as mainPasswordRequest } from '@/routes/password';
import { store as mainTenantLoginStore } from '@/routes/tenant-login';
import { landing as tenantAuthLanding } from '@/routes/tenant/auth';
import { request as tenantPasswordRequest } from '@/routes/tenant/password';
import { store as tenantTenantLoginStore } from '@/routes/tenant/tenant-login';
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const page = usePage<any>();
const currentTenant = computed(() => page.props.current_tenant);

const loginAction = computed(() => {
    const slug = currentTenant.value?.slug;
    return (
        slug ? tenantTenantLoginStore(slug) : mainTenantLoginStore()
    ) as any;
});

const registerUrl = computed(() => {
    return mainRegister().url;
});

const forgotPasswordUrl = computed(() => {
    const slug = currentTenant.value?.slug;
    return slug ? tenantPasswordRequest(slug).url : mainPasswordRequest().url;
});

const landingUrl = computed(() => {
    const slug = currentTenant.value?.slug;
    return slug ? tenantAuthLanding(slug).url : mainAuthLanding().url;
});
</script>

<template>
    <Head title="Tenant Login" />

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
                    <h1 class="text-3xl font-bold text-gray-900">
                        Tenant Sign In
                    </h1>
                    <p class="text-gray-500">
                        Welcome back! Please enter your details.
                    </p>
                </div>

                <div
                    v-if="status"
                    class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700"
                >
                    {{ status }}
                </div>

                <Form
                    v-bind="loginAction"
                    :reset-on-success="['password']"
                    v-slot="{ errors, processing }"
                    class="space-y-5"
                >
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
                            required
                            autofocus
                            autocomplete="email"
                            class="h-11 border-gray-300"
                        />
                        <InputError :message="errors.email" />
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <Label
                                for="password"
                                class="text-sm font-semibold text-gray-800"
                                >Password</Label
                            >
                            <Link
                                v-if="canResetPassword"
                                :href="forgotPasswordUrl"
                                class="text-sm font-medium text-blue-700 hover:underline"
                            >
                                Forgot password?
                            </Link>
                        </div>

                        <Input
                            id="password"
                            name="password"
                            type="password"
                            placeholder="**********"
                            required
                            autocomplete="current-password"
                            class="h-11 border-gray-300"
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <div class="flex items-center gap-2">
                        <Checkbox id="remember" name="remember" />
                        <label for="remember" class="text-sm text-gray-600"
                            >Remember me</label
                        >
                    </div>

                    <button
                        type="submit"
                        class="h-12 w-full rounded-full bg-gradient-to-r from-blue-700 to-blue-500 font-semibold text-white shadow-sm transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="processing"
                    >
                        {{ processing ? 'Signing In...' : 'Sign In' }}
                    </button>
                </Form>

                <p class="text-center text-sm text-gray-600">
                    Don't have an account?
                    <Link
                        :href="registerUrl"
                        class="ml-1 font-semibold text-blue-700 hover:underline"
                    >
                        Sign Up ->
                    </Link>
                </p>

                <p class="text-center text-xs text-gray-500">
                    <Link
                        :href="landingUrl"
                        class="font-medium text-gray-600 hover:underline"
                    >
                        Back to auth landing
                    </Link>
                </p>
            </div>
        </div>
    </div>
</template>
