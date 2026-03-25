<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import { Button } from '@/components/ui/button';
import ClientLayout from '@/layouts/ClientLayout.vue';
import { index, store } from '@/routes/client/support';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const { t } = useTrans();

const form = useForm<{
    subject: string;
    message: string;
}>({
    subject: '',
    message: '',
});

const canSubmit = computed(
    () =>
        form.subject.trim().length > 0 &&
        form.message.trim().length > 0 &&
        !form.processing,
);

const submitTicket = async () => {
    if (!form.subject || form.subject.trim().length === 0) return;
    if (!form.message || form.message.trim().length === 0) return;

    try {
        await form.post(store().url);
    } catch (error) {
        console.error('An error occurred while creating the ticket:', error);
    }
};
</script>

<template>
    <Head :title="t('client_pages.support.create.head_title')" />
    <ClientLayout>
        <div class="p-4">
            <div class="mb-6 w-full rounded-lg bg-white p-6 shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">
                            {{ t('client_pages.support.create.title') }}
                        </h1>
                        <p class="mt-2 text-sm text-gray-600">
                            {{ t('client_pages.support.create.subtitle') }}
                        </p>
                    </div>
                    <div>
                        <Link :href="index().url">
                            <Button variant="outline">{{
                                t('client_pages.support.create.back_to_tickets')
                            }}</Button>
                        </Link>
                    </div>
                </div>
            </div>

            <div class="w-full rounded-lg bg-white p-6 shadow">
                <form class="space-y-6" @submit.prevent="submitTicket">
                    <div>
                        <label
                            for="subject"
                            class="block text-sm font-medium text-gray-700"
                        >
                            {{ t('client_pages.support.create.fields.subject') }}
                            <span class="text-red-500">*</span>
                        </label>
                        <input
                            id="subject"
                            v-model="form.subject"
                            type="text"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            :placeholder="
                                t(
                                    'client_pages.support.create.placeholders.subject',
                                )
                            "
                            required
                            maxlength="255"
                            :aria-label="
                                t('client_pages.support.create.aria.subject')
                            "
                        />
                        <p
                            v-if="form.errors.subject"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors.subject }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500">
                            {{
                                t('client_pages.support.create.subject_counter', {
                                    count: form.subject.length,
                                })
                            }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="message"
                            class="block text-sm font-medium text-gray-700"
                        >
                            {{ t('client_pages.support.create.fields.message') }}
                            <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            id="message"
                            v-model="form.message"
                            rows="8"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            :placeholder="
                                t(
                                    'client_pages.support.create.placeholders.message',
                                )
                            "
                            required
                            :aria-label="
                                t('client_pages.support.create.aria.message')
                            "
                        ></textarea>
                        <p
                            v-if="form.errors.message"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors.message }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ t('client_pages.support.create.detail_help') }}
                        </p>
                    </div>

                    <div class="flex items-center justify-end space-x-3">
                        <Link :href="index().url">
                            <Button
                                type="button"
                                variant="outline"
                                :disabled="form.processing"
                            >
                                {{ t('client_pages.support.create.cancel') }}
                            </Button>
                        </Link>
                        <button
                            type="submit"
                            class="cursor-pointer rounded-md bg-slate-600 px-6 py-2 text-white hover:bg-slate-700 focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!canSubmit"
                        >
                            <span v-if="form.processing">{{
                                t('client_pages.support.create.submitting')
                            }}</span>
                            <span v-else>{{
                                t('client_pages.support.create.submit')
                            }}</span>
                        </button>
                    </div>
                </form>
            </div>

            <div class="mt-6 rounded-lg border border-blue-200 bg-blue-50 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg
                            class="h-5 w-5 text-blue-400"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">
                            {{ t('client_pages.support.create.tips_title') }}
                        </h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <ul class="list-disc space-y-1 pl-5">
                                <li>
                                    {{ t('client_pages.support.create.tips.1') }}
                                </li>
                                <li>
                                    {{ t('client_pages.support.create.tips.2') }}
                                </li>
                                <li>
                                    {{ t('client_pages.support.create.tips.3') }}
                                </li>
                                <li>
                                    {{ t('client_pages.support.create.tips.4') }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </ClientLayout>
</template>
