<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import HomeLayout from '@/layouts/HomeLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { fleet as mainFleet, about as mainAbout } from '@/routes';
import { about as tenantAbout, fleet as tenantFleet } from '@/routes/tenant';
import { guestContact } from '@/routes/tenant/contact';
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage<any>();
const { t } = useTrans();
const currentTenant = computed(() => page.props.current_tenant);
const tenantSlug = computed(() => currentTenant.value?.slug ?? null);
const fleetUrl = computed(() =>
    tenantSlug.value ? tenantFleet(tenantSlug.value).url : mainFleet().url
);
const aboutUrl = computed(() =>
    tenantSlug.value ? tenantAbout(tenantSlug.value).url : mainAbout().url
);
const canSubmitTenantTicket = computed(() => !!tenantSlug.value);

const form = useForm({
    name: '',
    email: '',
    subject: '',
    message: '',
});

const showNotification = ref(false);
const notificationMessage = ref('');

const sendTicket = () => {
    if (!canSubmitTenantTicket.value) {
        showNotification.value = true;
        notificationMessage.value = t('contact.ticket_tenant_only');
        setTimeout(() => {
            showNotification.value = false;
        }, 2000);
        return;
    }

    form.post(guestContact(tenantSlug.value as string).url, {
        onSuccess() {
            form.reset();
            showNotification.value = true;
            notificationMessage.value = t('contact.ticket_success');
            setTimeout(() => {
                showNotification.value = false;
            }, 2000);
        },
        onError() {
            showNotification.value = true;
            notificationMessage.value = t('contact.ticket_error');
            setTimeout(() => {
                showNotification.value = false;
            }, 2000);
        }
    });
}
</script>
<template>
    <HomeLayout>
        <div class="min-h-screen bg-white py-16 ">
            <!-- notification -->
            <div>
                <p class="fixed top-24 right-4 bg-slate-700 text-white p-3 rounded-xl" v-if="showNotification">{{ notificationMessage }}</p>
            </div>
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Header Section -->
                <div class="mb-16 text-center">
                    <h1 class="mb-4 text-4xl font-bold text-gray-900">
                        {{ t('contact.title') }}
                    </h1>
                    <p class="mx-auto max-w-2xl text-xl text-gray-600">
                        {{ t('contact.subtitle') }}
                    </p>
                </div>

                <div class="grid gap-12 lg:grid-cols-3">
                    <!-- Contact Form -->
                    <div class="lg:col-span-2">
                        <div
                            class="rounded-lg border border-gray-200 bg-white p-8 shadow-sm"
                        >
                            <h2 class="mb-6 text-2xl font-bold text-gray-900">
                                {{ t('contact.send_message') }}
                            </h2>

                            <form class="space-y-6"
                            
                            @submit.prevent="sendTicket">
                                <!-- Name Field -->
                                <div>
                                    <label
                                        for="name"
                                        class="mb-2 block text-sm font-semibold text-gray-700"
                                    >
                                        {{ t('contact.full_name') }}
                                    </label>
                                    <input
                                        type="text"
                                        id="name"
                                        name="name"
                                        class="w-full rounded-lg border border-gray-300 px-4 py-3 transition-colors focus:border-orange-500 focus:ring-2 focus:ring-orange-500"
                                        :placeholder="t('contact.placeholder_name')"
                                        v-model="form.name"
                                    />
                                    <span class="text-red-500" v-if="form.errors.name">{{ form.errors.name }}</span>
                                </div>

                                <!-- Email Field -->
                                <div>
                                    <label
                                        for="email"
                                        class="mb-2 block text-sm font-semibold text-gray-700"
                                    >
                                        {{ t('contact.email') }}
                                    </label>
                                    <input
                                        type="email"
                                        id="email"
                                        name="email"
                                        class="w-full rounded-lg border border-gray-300 px-4 py-3 transition-colors focus:border-orange-500 focus:ring-2 focus:ring-orange-500"
                                        :placeholder="t('contact.placeholder_email')"
                                        v-model="form.email"
                                    />
                                    <span class="text-red-500" v-if="form.errors.email">{{ form.errors.email }}</span>
                                </div>

                                <!-- Subject Field -->
                                <div>
                                    <label
                                        for="subject"
                                        class="mb-2 block text-sm font-semibold text-gray-700"
                                    >
                                        {{ t('contact.subject') }}
                                    </label>
                                    <input
                                        type="text"
                                        id="subject"
                                        name="subject"
                                        class="w-full rounded-lg border border-gray-300 px-4 py-3 transition-colors focus:border-orange-500 focus:ring-2 focus:ring-orange-500"
                                        :placeholder="t('contact.placeholder_subject')"
                                        v-model="form.subject"
                                    />
                                    <span class="text-red-500" v-if="form.errors.subject">{{ form.errors.subject }}</span>
                                </div>

                                <!-- Message Field -->
                                <div>
                                    <label
                                        for="message"
                                        class="mb-2 block text-sm font-semibold text-gray-700"
                                    >
                                        {{ t('contact.message') }}
                                    </label>
                                    <textarea
                                        id="message"
                                        name="message"
                                        rows="6"
                                        class="resize-vertical w-full rounded-lg border border-gray-300 px-4 py-3 transition-colors focus:border-orange-500 focus:ring-2 focus:ring-orange-500"
                                        :placeholder="t('contact.placeholder_message')"
                                        v-model="form.message"
                                    ></textarea>
                                    <span class="text-red-500" v-if="form.errors.message">{{ form.errors.message }}</span>
                                </div>

                                <!-- Submit Button -->
                                <div>
                                    <button
                                        type="submit"
                                        class="w-full cursor-pointer rounded-lg bg-orange-500 px-6 py-3 font-semibold text-white transition-colors duration-200 hover:bg-orange-600"
                                    >
                                        {{ t('contact.submit') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Contact Information Sidebar -->
                    <div class="lg:col-span-1">
                        <div
                            class="rounded-lg border border-gray-200 bg-gray-50 p-8"
                        >
                            <h3 class="mb-6 text-xl font-bold text-gray-900">
                                {{ t('contact.get_in_touch') }}
                            </h3>

                            <div class="space-y-6">
                                <!-- Phone -->
                                <div>
                                    <h4
                                        class="mb-2 font-semibold text-gray-900"
                                    >
                                        {{ t('contact.phone') }}
                                    </h4>
                                    <p class="text-gray-600">
                                        +1 (555) 123-4567
                                    </p>
                                </div>

                                <!-- Email -->
                                <div>
                                    <h4
                                        class="mb-2 font-semibold text-gray-900"
                                    >
                                        {{ t('contact.email') }}
                                    </h4>
                                    <p class="text-gray-600">
                                        info@realrentcar.com
                                    </p>
                                </div>

                                <!-- Address -->
                                <div>
                                    <h4
                                        class="mb-2 font-semibold text-gray-900"
                                    >
                                        {{ t('contact.address') }}
                                    </h4>
                                    <p class="text-gray-600">
                                        123 Main Street<br />
                                        Downtown District<br />
                                        City, State 12345
                                    </p>
                                </div>

                                <!-- Business Hours -->
                                <div>
                                    <h4
                                        class="mb-2 font-semibold text-gray-900"
                                    >
                                        {{ t('contact.business_hours') }}
                                    </h4>
                                    <div class="space-y-1 text-gray-600">
                                        <p>
                                            Monday - Friday: 8:00 AM - 8:00 PM
                                        </p>
                                        <p>Saturday: 9:00 AM - 6:00 PM</p>
                                        <p>Sunday: 10:00 AM - 4:00 PM</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Links -->
                        <div
                            class="mt-8 rounded-lg border border-gray-200 bg-white p-6"
                        >
                            <h3 class="mb-4 text-lg font-bold text-gray-900">
                                {{ t('contact.quick_links') }}
                            </h3>
                            <div class="space-y-3">
                                <a
                                    :href="fleetUrl"
                                    class="block font-medium text-orange-500 transition-colors hover:text-orange-600"
                                >
                                    {{ t('contact.browse_fleet') }}
                                </a>
                                <a
                                    :href="aboutUrl"
                                    class="block font-medium text-orange-500 transition-colors hover:text-orange-600"
                                >
                                    {{ t('contact.about_us') }}
                                </a>
                                
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </HomeLayout>
</template>
