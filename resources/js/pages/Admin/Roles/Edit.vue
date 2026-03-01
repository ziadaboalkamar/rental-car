<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { index, store, update } from '@/routes/admin/roles';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useTrans } from '@/composables/useTrans';

const props = defineProps<{
    role: any | null;
    permissions: Array<{ id: number; name: string; display_name: string; description: string }>;
}>();

const { t } = useTrans();
const page = usePage<any>();
const subdomain = computed(() => page.props.current_tenant?.slug);

const isEdit = computed(() => !!props.role);

// Initialize form with default values
const form = useForm({
    display_name: props.role?.display_name ?? '',
    description: props.role?.description ?? '',
    permission_ids: props.role?.permission_ids ?? [],
});

function togglePermission(id: number) {
    const index = form.permission_ids.indexOf(id);
    if (index > -1) {
        form.permission_ids.splice(index, 1);
    } else {
        form.permission_ids.push(id);
    }
}

function submit() {
    if (!subdomain.value) return;

    if (isEdit.value) {
        form.put(update([subdomain.value, props.role.id]).url);
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

    <Head :title="isEdit ? t('dashboard.admin.roles.edit_role') : t('dashboard.admin.roles.new_role')" />
    <AdminLayout>
        <!-- Main -->
        <main class="flex-1 space-y-6 p-8 text-indigo-900">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">
                    {{ isEdit ? t('dashboard.admin.roles.edit_role') : t('dashboard.admin.roles.new_role') }}
                </h1>
                <Link v-if="subdomain" :href="index(subdomain).url">
                    <Button variant="outline">{{ t('dashboard.admin.common.back') }}</Button>
                </Link>
            </div>

            <div class="max-w-4xl">
                <form class="space-y-8" @submit.prevent="submit">
                    <div class="grid grid-cols-1 gap-6 bg-white p-6 rounded-lg border shadow-sm">
                        <!-- Display Name -->
                        <div>
                            <Label for="display_name">{{ t('dashboard.admin.roles.form.display_name') }}</Label>
                            <Input id="display_name" v-model="form.display_name" required class="mt-1" />
                            <InputError :message="form.errors.display_name" class="mt-1" />
                        </div>

                        <!-- Description -->
                        <div>
                            <Label for="description">{{ t('dashboard.admin.roles.form.description') }}</Label>
                            <Input id="description" v-model="form.description" class="mt-1" />
                            <InputError :message="form.errors.description" class="mt-1" />
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-lg border shadow-sm space-y-4">
                        <div class="flex flex-col space-y-1">
                            <h3 class="text-lg font-medium">{{ t('dashboard.admin.roles.form.permissions') }}</h3>
                            <p class="text-sm text-gray-500">{{ t('dashboard.admin.roles.form.permissions_help') }}</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 border-t pt-4">
                            <div v-for="permission in props.permissions" :key="permission.id"
                                class="flex items-start space-x-3 p-3 rounded-md hover:bg-indigo-50 transition-colors border border-transparent hover:border-indigo-100">
                                <input :id="`perm-${permission.id}`" type="checkbox"
                                    :checked="form.permission_ids.includes(permission.id)"
                                    @change="togglePermission(permission.id)"
                                    class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                <div class="space-y-1">
                                    <label :for="`perm-${permission.id}`"
                                        class="text-sm font-semibold leading-none cursor-pointer">
                                        {{ permission.display_name }}
                                    </label>
                                    <p class="text-xs text-gray-500 italic">{{ permission.description }}</p>
                                </div>
                            </div>
                        </div>
                        <InputError :message="form.errors.permission_ids" class="mt-1" />
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <Button type="submit" :disabled="form.processing" size="lg">
                            {{
                                form.processing
                                    ? isEdit
                                        ? t('dashboard.admin.common.saving')
                                        : t('dashboard.admin.common.creating')
                                    : isEdit
                                        ? t('dashboard.admin.common.save_changes')
                                        : t('dashboard.admin.roles.new_role')
                            }}
                        </Button>
                        <Link v-if="subdomain" :href="index(subdomain).url">
                            <Button type="button" variant="outline" size="lg">{{ t('dashboard.admin.common.cancel')
                                }}</Button>
                        </Link>
                    </div>
                </form>
            </div>
        </main>
    </AdminLayout>
</template>
