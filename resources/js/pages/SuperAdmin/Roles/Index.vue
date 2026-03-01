<script setup lang="ts">
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Shield, KeyRound } from 'lucide-vue-next';
import { useTrans } from '@/composables/useTrans';

const props = defineProps<{
    roles: Array<{
        id: number;
        name: string;
        display_name: string | null;
        description: string | null;
        permissions?: Array<{ id: number; name: string; display_name: string | null }>;
    }>;
}>();
const { t } = useTrans();

function deleteRole(id: number, name: string) {
    if (confirm(`Delete role "${name}"? Users will lose this role.`)) {
        router.delete(`/superadmin/roles/${id}`);
    }
}
</script>

<template>
    <Head :title="t('dashboard.super_admin.roles.index.head_title')" />
    <SuperAdminLayout>
        <main class="flex-1 p-8 space-y-6">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">{{ t('dashboard.super_admin.roles.index.title') }}</h1>
                <Link href="/superadmin/roles/create">
                    <Button>+ {{ t('dashboard.super_admin.roles.index.create_role') }}</Button>
                </Link>
            </div>

            <p class="text-muted-foreground text-sm">
                {{ t('dashboard.super_admin.roles.index.subtitle') }}
            </p>

            <div class="overflow-x-auto rounded-md border">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.super_admin.roles.index.role') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('dashboard.super_admin.roles.index.permissions') }}</th>
                            <th class="px-4 py-3 text-right">{{ t('dashboard.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="role in props.roles" :key="role.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                                        <Shield class="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <div class="font-medium">{{ role.display_name || role.name }}</div>
                                        <div class="text-sm text-muted-foreground">{{ role.name }}</div>
                                        <div v-if="role.description" class="text-sm text-muted-foreground mt-0.5">{{ role.description }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1 max-w-xl">
                                    <span
                                        v-for="perm in (role.permissions || [])"
                                        :key="perm.id"
                                        class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700"
                                    >
                                        <KeyRound class="h-3 w-3 mr-1" />
                                        {{ perm.display_name || perm.name }}
                                    </span>
                                    <span v-if="!(role.permissions?.length)" class="text-sm text-muted-foreground">{{ t('dashboard.super_admin.roles.index.no_permissions') }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <Link :href="`/superadmin/roles/${role.id}/edit`">
                                    <Button variant="outline" size="sm">{{ t('dashboard.common.edit') }}</Button>
                                </Link>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="text-red-600 hover:text-red-700"
                                    @click="deleteRole(role.id, role.display_name || role.name)"
                                >
                                    {{ t('dashboard.common.delete') }}
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="props.roles.length === 0">
                            <td colspan="3" class="px-4 py-6 text-center text-gray-500">
                                {{ t('dashboard.super_admin.roles.index.empty') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </SuperAdminLayout>
</template>

