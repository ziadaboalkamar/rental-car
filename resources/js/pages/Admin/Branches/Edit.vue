<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { index, store, update } from '@/routes/admin/branches';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useTrans } from '@/composables/useTrans';

const props = defineProps<{
    branch: any | null;
}>();

const { t } = useTrans();
const page = usePage<any>();
const subdomain = computed(() => page.props.current_tenant?.slug);

const isEdit = computed(() => !!props.branch);

// Initialize form with default values
const form = useForm({
    name: props.branch?.name ?? '',
    address: props.branch?.address ?? '',
    phone: props.branch?.phone ?? '',
    email: props.branch?.email ?? '',
});

function submit() {
    if (!subdomain.value) return;
    
    if (isEdit.value) {
        form.put(update([subdomain.value, props.branch.id]).url);
    } else {
        form.post(store(subdomain.value).url, {
            onSuccess: () => {
                form.reset();
            },
        });
    }
}
</script>

<template>
    <Head :title="isEdit ? t('dashboard.admin.branches.edit_branch') : t('dashboard.admin.branches.new_branch')" />
    <AdminLayout>
        <!-- Main -->
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">
                    {{ isEdit ? t('dashboard.admin.branches.edit_branch') : t('dashboard.admin.branches.new_branch') }}
                </h1>
                <Link v-if="subdomain" :href="index(subdomain).url">
                    <Button variant="outline">{{ t('dashboard.admin.common.back') }}</Button>
                </Link>
            </div>

            <div class="max-w-2xl">
                <form class="space-y-6" @submit.prevent="submit">
                    <div class="space-y-4">
                        <!-- Name -->
                        <div>
                            <Label for="name">{{ t('dashboard.admin.branches.table.name') }}</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                :placeholder="t('dashboard.admin.branches.table.name')"
                                required
                            />
                            <InputError
                                :message="form.errors.name"
                                class="mt-1"
                            />
                        </div>

                        <!-- Address -->
                        <div>
                            <Label for="address">{{ t('dashboard.admin.branches.table.address') }}</Label>
                            <Input
                                id="address"
                                v-model="form.address"
                                :placeholder="t('dashboard.admin.branches.table.address')"
                                required
                            />
                            <InputError
                                :message="form.errors.address"
                                class="mt-1"
                            />
                        </div>

                        <!-- Phone -->
                        <div>
                            <Label for="phone">{{ t('dashboard.admin.branches.table.phone') }}</Label>
                            <Input
                                id="phone"
                                v-model="form.phone"
                                :placeholder="t('dashboard.admin.branches.table.phone')"
                                required
                            />
                            <InputError
                                :message="form.errors.phone"
                                class="mt-1"
                            />
                        </div>

                        <!-- Email -->
                        <div>
                            <Label for="email">{{ t('dashboard.admin.branches.table.email') }}</Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                :placeholder="t('dashboard.admin.branches.table.email')"
                                required
                            />
                            <InputError
                                :message="form.errors.email"
                                class="mt-1"
                            />
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <Button type="submit" :disabled="form.processing">
                            {{
                                form.processing
                                    ? isEdit
                                        ? t('dashboard.admin.common.saving')
                                        : t('dashboard.admin.common.creating')
                                    : isEdit
                                      ? t('dashboard.admin.common.save_changes')
                                      : t('dashboard.admin.branches.new_branch')
                            }}
                        </Button>
                        <Link v-if="subdomain" :href="index(subdomain).url">
                            <Button type="button" variant="outline">{{ t('dashboard.admin.common.cancel') }}</Button>
                        </Link>
                    </div>
                </form>
            </div>
        </main>
    </AdminLayout>
</template>
