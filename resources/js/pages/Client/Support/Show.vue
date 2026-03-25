<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import { Button } from '@/components/ui/button';
import ClientLayout from '@/layouts/ClientLayout.vue';
import { index, reply } from '@/routes/client/support';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

interface Message {
    id: number;
    message: string;
    is_admin: boolean;
    created_at: string;
}

interface Ticket {
    id: number;
    subject: string;
    status: TicketStatusType;
    created_at: string;
    messages: Message[];
    user?: {
        name: string;
    };
}

const TicketStatus = {
    OPEN: 'open',
    IN_PROGRESS: 'in_progress',
    CLOSED: 'closed',
} as const;

type TicketStatusType = (typeof TicketStatus)[keyof typeof TicketStatus];

const props = defineProps<{
    ticket: Ticket;
}>();

const { t, locale } = useTrans();

const form = useForm<{
    message: string;
}>({
    message: '',
});

const statusColors: Record<TicketStatusType, string> = {
    [TicketStatus.OPEN]: 'bg-blue-100 text-blue-800',
    [TicketStatus.IN_PROGRESS]: 'bg-yellow-100 text-yellow-800',
    [TicketStatus.CLOSED]: 'bg-gray-100 text-gray-800',
} as const;

const statusLabels: Record<TicketStatusType, string> = {
    [TicketStatus.OPEN]: t('client_pages.support.show.statuses.open'),
    [TicketStatus.IN_PROGRESS]: t(
        'client_pages.support.show.statuses.in_progress',
    ),
    [TicketStatus.CLOSED]: t('client_pages.support.show.statuses.closed'),
} as const;

const pageTitle = computed(() =>
    t('client_pages.support.show.head_title', { id: props.ticket.id }),
);

const canSend = computed(
    () => form.message.trim().length > 0 && !form.processing,
);

const messagesEndRef = ref<HTMLElement | null>(null);

const scrollToBottom = async () => {
    await nextTick();
    messagesEndRef.value?.scrollIntoView({ behavior: 'smooth', block: 'end' });
};

const submitReply = async () => {
    if (!form.message || form.message.trim().length === 0) return;

    try {
        await form.post(reply(props.ticket.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('message');
                router.reload({ only: ['ticket'] });
                scrollToBottom();
            },
        });
    } catch (error) {
        console.error('An error occurred while sending the message:', error);
    }
};

const formatDate = (dateString: string): string =>
    new Date(dateString).toLocaleString(locale.value);

onMounted(() => {
    scrollToBottom();
});

watch(
    () => props.ticket.messages?.length,
    () => scrollToBottom(),
);
</script>

<template>
    <Head :title="pageTitle" />
    <ClientLayout>
        <div class="p-4">
            <div class="mb-6 w-full rounded-lg bg-white p-6 shadow">
                <div class="mb-4 flex items-start justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">
                            {{ ticket.subject }}
                        </h1>
                        <div class="mt-2 flex items-center">
                            <span
                                class="rounded-full px-3 py-1 text-xs font-medium"
                                :class="
                                    statusColors[
                                        ticket.status as TicketStatusType
                                    ] || 'bg-gray-100 text-gray-800'
                                "
                            >
                                {{
                                    statusLabels[
                                        ticket.status as TicketStatusType
                                    ] || ticket.status
                                }}
                            </span>
                            <span class="ml-2 text-sm text-gray-500">
                                #{{ ticket.id }} -
                                {{ formatDate(ticket.created_at) }}
                            </span>
                        </div>
                    </div>
                    <div class="mt-2">
                        <Link :href="index().url">
                            <Button variant="outline">{{
                                t('client_pages.support.show.back')
                            }}</Button>
                        </Link>
                    </div>
                </div>
            </div>

            <div
                class="h-2/3 space-y-4 overflow-scroll rounded-md border-2 p-2"
            >
                <div class="min-h-[200px] space-y-4">
                    <div
                        v-if="!ticket.messages || ticket.messages.length === 0"
                        class="rounded-md border border-dashed p-6 text-center text-sm text-gray-500"
                    >
                        {{ t('client_pages.support.show.no_messages') }}
                    </div>
                    <div
                        v-for="message in ticket.messages"
                        :key="message.id"
                        :class="[
                            'flex',
                            message.is_admin ? 'justify-start' : 'justify-end',
                        ]"
                    >
                        <div
                            :class="[
                                'max-w-3xl rounded-lg px-4 py-2',
                                message.is_admin
                                    ? 'rounded-tl-none bg-gray-200 text-gray-800'
                                    : 'rounded-tr-none bg-blue-500 text-white',
                            ]"
                        >
                            <p class="whitespace-pre-line">
                                {{ message.message }}
                            </p>
                            <p class="mt-1 text-right text-xs opacity-75">
                                {{ formatDate(message.created_at) }}
                                <span v-if="message.is_admin" class="ml-1">
                                    -
                                    {{
                                        t(
                                            'client_pages.support.show.support_team',
                                        )
                                    }}
                                </span>
                                <span v-else class="ml-1">
                                    - {{ t('client_pages.support.show.you') }}
                                </span>
                            </p>
                        </div>
                    </div>
                    <div ref="messagesEndRef"></div>
                </div>
            </div>

            <form
                v-if="ticket.status !== 'closed'"
                class="mt-6"
                @submit.prevent="submitReply"
            >
                <div class="flex space-x-2">
                    <div class="flex-1">
                        <label for="message" class="sr-only">{{
                            t('client_pages.support.show.reply_label')
                        }}</label>
                        <textarea
                            id="message"
                            v-model="form.message"
                            rows="3"
                            class="w-full rounded-lg border-1 border-gray-300 p-2"
                            :placeholder="
                                t(
                                    'client_pages.support.show.reply_placeholder',
                                )
                            "
                            required
                            :aria-label="
                                t('client_pages.support.show.reply_label')
                            "
                            @keydown.ctrl.enter.prevent="submitReply"
                        ></textarea>
                        <p
                            v-if="form.errors.message"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors.message }}
                        </p>
                    </div>
                    <button
                        type="submit"
                        class="mb-2 w-20 cursor-pointer self-end rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!canSend"
                    >
                        <span v-if="form.processing">{{
                            t('client_pages.support.show.sending')
                        }}</span>
                        <span v-else>{{
                            t('client_pages.support.show.send')
                        }}</span>
                    </button>
                </div>
            </form>
        </div>
    </ClientLayout>
</template>
