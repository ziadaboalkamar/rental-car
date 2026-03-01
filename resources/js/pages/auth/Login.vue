<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/composables/useTrans';
import HomeLayout from '@/layouts/HomeLayout.vue';
import { register as mainRegister } from '@/routes';
import { store as mainLoginStore } from '@/routes/login';
import { request as mainPasswordRequest } from '@/routes/password';
import { register as tenantRegister } from '@/routes/tenant';
import { store as tenantLoginStore } from '@/routes/tenant/login';
import { request as tenantPasswordRequest } from '@/routes/tenant/password';
import { Form, Head, usePage } from '@inertiajs/vue3';
import {
    ChevronDown,
    ChevronUp,
    LoaderCircle,
    Shield,
    User,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const page = usePage<any>();
const { t } = useTrans();
const currentTenant = computed(() => page.props.current_tenant);

const loginAction = computed(() => {
    const slug = currentTenant.value?.slug;
    return (slug ? tenantLoginStore(slug) : mainLoginStore()) as any;
});
const registerUrl = computed(() => {
    const slug = currentTenant.value?.slug;
    return slug ? tenantRegister(slug).url : mainRegister().url;
});
const forgotPasswordUrl = computed(() => {
    const slug = currentTenant.value?.slug;
    return slug ? tenantPasswordRequest(slug).url : mainPasswordRequest().url;
});

const isDemoOpen = ref(false);
</script>

<template>
    <HomeLayout>
        <Head :title="t('auth.login_title')" />

        <div
            class="flex min-h-[90vh] items-center justify-center px-4 sm:px-6 lg:px-8"
        >
            <div class="w-full max-w-md space-y-8">
                <!-- Header -->
                <div class="text-center">
                    <h1 class="mb-2 text-3xl font-bold text-gray-900">
                        {{ t('auth.welcome_back') }}
                    </h1>
                    <p class="text-gray-600">
                        {{ t('auth.sign_in_continue') }}
                    </p>
                </div>

                <!-- Demo Credentials (Collapsible) -->
                <div
                    class="overflow-hidden rounded-xl border border-blue-200 bg-gradient-to-r from-blue-50 to-indigo-50 shadow-sm"
                >
                    <!-- Toggle Button -->
                    <button
                        @click="isDemoOpen = !isDemoOpen"
                        type="button"
                        class="flex w-full items-center justify-between px-6 py-4 transition-colors hover:bg-blue-100/50"
                    >
                        <div class="flex items-center space-x-3">
                            <div class="rounded-full bg-blue-100 p-2">
                                <svg
                                    class="h-4 w-4 text-blue-600"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                            </div>
                            <span class="text-sm font-semibold text-gray-900">
                                {{ t('auth.demo_credentials') }}
                            </span>
                        </div>
                        <ChevronDown
                            v-if="!isDemoOpen"
                            class="h-5 w-5 text-gray-600 transition-transform"
                        />
                        <ChevronUp
                            v-else
                            class="h-5 w-5 text-gray-600 transition-transform"
                        />
                    </button>

                    <!-- Collapsible Content -->
                    <div v-show="isDemoOpen" class="space-y-3 px-6 pb-6">
                        <!-- Client Demo -->
                        <div class="mt-2 rounded-lg bg-white p-3 shadow-sm">
                            <div class="mb-2 flex items-center space-x-2">
                                <User class="h-4 w-4 text-gray-600" />
                                <span
                                    class="text-xs font-semibold tracking-wide text-gray-700 uppercase"
                                >
                                    {{ t('auth.client_access') }}
                                </span>
                            </div>
                            <div class="space-y-1 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600"
                                        >{{ t('auth.demo_email') }}:</span
                                    >
                                    <code
                                        class="rounded bg-gray-100 px-2 py-0.5 font-mono text-xs text-gray-800"
                                    >
                                        client@example.com
                                    </code>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600"
                                        >{{ t('auth.demo_password') }}:</span
                                    >
                                    <code
                                        class="rounded bg-gray-100 px-2 py-0.5 font-mono text-xs text-gray-800"
                                    >
                                        00000000
                                    </code>
                                </div>
                            </div>
                        </div>

                        <!-- Admin Demo -->
                        <div class="rounded-lg bg-white p-3 shadow-sm">
                            <div class="mb-2 flex items-center space-x-2">
                                <Shield class="h-4 w-4 text-orange-600" />
                                <span
                                    class="text-xs font-semibold tracking-wide text-orange-700 uppercase"
                                >
                                    {{ t('auth.admin_access') }}
                                </span>
                            </div>
                            <div class="space-y-1 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600"
                                        >{{ t('auth.demo_email') }}:</span
                                    >
                                    <code
                                        class="rounded bg-gray-100 px-2 py-0.5 font-mono text-xs text-gray-800"
                                    >
                                        admin@example.com
                                    </code>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600"
                                        >{{ t('auth.demo_password') }}:</span
                                    >
                                    <code
                                        class="rounded bg-gray-100 px-2 py-0.5 font-mono text-xs text-gray-800"
                                    >
                                        00000000
                                    </code>
                                </div>
                                <div class="mt-2 border-t border-gray-200 pt-2">
                                    <a
                                        href="/admin-secret-url"
                                        class="text-xs font-medium text-orange-600 hover:text-orange-700 hover:underline"
                                    >
                                        {{ t('auth.go_admin_panel') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status Message -->
                <div
                    v-if="status"
                    class="rounded-lg border border-green-200 bg-green-50 p-4 text-center"
                >
                    <p class="text-sm font-medium text-green-800">
                        {{ status }}
                    </p>
                </div>

                <!-- Login Form -->
                <div
                    class="rounded-xl border border-gray-200 bg-white p-8 shadow-sm"
                >
                    <Form
                        v-bind="loginAction"
                        :reset-on-success="['password']"
                        v-slot="{ errors, processing }"
                        class="space-y-6"
                    >
                        <!-- Email Field -->
                        <div>
                            <Label
                                for="email"
                                class="mb-2 block text-sm font-semibold text-gray-900"
                            >
                                {{ t('auth.email') }}
                            </Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autofocus
                                :tabindex="1"
                                autocomplete="email"
                                :placeholder="t('auth.placeholder_email')"
                                class="w-full rounded-lg border border-gray-300 px-4 py-3 transition-colors focus:border-orange-500 focus:ring-2 focus:ring-orange-500"
                            />
                            <InputError :message="errors.email" class="mt-1" />
                        </div>

                        <!-- Password Field -->
                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <Label
                                    for="password"
                                    class="block text-sm font-semibold text-gray-900"
                                >
                                    {{ t('auth.password') }}
                                </Label>
                                <TextLink
                                    v-if="canResetPassword"
                                    :href="forgotPasswordUrl"
                                    class="text-sm font-medium text-orange-600 hover:text-orange-700"
                                    :tabindex="5"
                                >
                                    {{ t('auth.forgot_password') }}
                                </TextLink>
                            </div>
                            <Input
                                id="password"
                                type="password"
                                name="password"
                                required
                                :tabindex="2"
                                autocomplete="current-password"
                                :placeholder="t('auth.placeholder_password')"
                                class="w-full rounded-lg border border-gray-300 px-4 py-3 transition-colors focus:border-orange-500 focus:ring-2 focus:ring-orange-500"
                            />
                            <InputError
                                :message="errors.password"
                                class="mt-1"
                            />
                        </div>

                        <!-- Remember Me -->
                        <div class="flex items-center">
                            <Label
                                for="remember"
                                class="flex cursor-pointer items-center space-x-3"
                            >
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    :tabindex="3"
                                    class="rounded border-gray-300 text-orange-600 focus:ring-orange-500"
                                />
                                <span class="text-sm text-gray-700">{{
                                    t('auth.remember_me')
                                }}</span>
                            </Label>
                        </div>

                        <!-- Submit Button -->
                        <Button
                            type="submit"
                            class="flex w-full items-center justify-center rounded-lg bg-orange-600 px-4 py-3 font-semibold text-white transition-colors duration-200 hover:bg-orange-700"
                            :tabindex="4"
                            :disabled="processing"
                            data-test="login-button"
                        >
                            <LoaderCircle
                                v-if="processing"
                                class="mr-2 h-5 w-5 animate-spin"
                            />
                            {{
                                processing
                                    ? t('auth.signing_in')
                                    : t('auth.sign_in')
                            }}
                        </Button>

                        <!-- Sign Up Link -->
                        <div class="border-t border-gray-200 pt-4 text-center">
                            <p class="text-sm text-gray-600">
                                {{ t('auth.dont_have_account') }}
                                <TextLink
                                    :href="registerUrl"
                                    :tabindex="5"
                                    class="ml-1 font-semibold text-orange-600 hover:text-orange-700"
                                >
                                    {{ t('auth.create_one') }}
                                </TextLink>
                            </p>
                        </div>
                    </Form>
                </div>

                <!-- Additional Info -->
                <div class="text-center">
                    <p class="text-xs text-gray-500">
                        {{ t('auth.terms_notice') }}
                    </p>
                </div>
            </div>
        </div>
    </HomeLayout>
</template>
