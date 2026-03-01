<script setup lang="ts">
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

const props = defineProps<{
    role: {
        id: number;
        name: string;
        display_name: string | null;
        description: string | null;
        permissions?: Array<{ id: number }>;
    };
    permissions: Array<{ id: number; name: string; display_name: string | null; description: string | null }>;
}>();

const form = useForm({
    name: props.role.name,
    display_name: props.role.display_name ?? '',
    description: props.role.description ?? '',
    permission_ids: (props.role.permissions || []).map((p) => p.id),
});

const togglePermission = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const permissionId = parseInt(target.value);
    
    console.log('Checkbox changed:', permissionId, target.checked);
    
    if (target.checked) {
        if (!form.permission_ids.includes(permissionId)) {
            form.permission_ids.push(permissionId);
        }
    } else {
        const index = form.permission_ids.indexOf(permissionId);
        if (index > -1) {
            form.permission_ids.splice(index, 1);
        }
    }
    
    console.log('Updated permission_ids:', form.permission_ids);
};

const submit = () => {
    console.log('Submitting with permission_ids:', form.permission_ids);
    form.permission_ids = [...new Set(form.permission_ids)].map((id) => Number(id));
    form.put(`/superadmin/roles/${props.role.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Edit Role: ${props.role.display_name || props.role.name}`" />
    <SuperAdminLayout>
        <main class="flex-1 p-8 space-y-6">
            <div class="flex items-center gap-4">
                <Link href="/superadmin/roles">
                    <Button variant="outline">← Back</Button>
                </Link>
                <h1 class="text-2xl font-semibold">Edit Role</h1>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <Card class="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Role details</CardTitle>
                        <CardDescription>Name is used in code; display name is shown in the UI.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">Name (slug) *</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                type="text"
                                required
                            />
                            <div v-if="form.errors.name" class="text-sm text-red-600">{{ form.errors.name }}</div>
                        </div>
                        <div class="space-y-2">
                            <Label for="display_name">Display name</Label>
                            <Input
                                id="display_name"
                                v-model="form.display_name"
                                type="text"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="description">Description</Label>
                            <Input
                                id="description"
                                v-model="form.description"
                                type="text"
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card class="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Permissions</CardTitle>
                        <CardDescription>Select the permissions this role gives to users who have it.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div
                            v-for="perm in props.permissions"
                            :key="perm.id"
                            class="flex items-center space-x-2"
                        >
                            <input
                                type="checkbox"
                                :id="`perm-${perm.id}`"
                                :value="perm.id"
                                :checked="form.permission_ids.includes(perm.id)"
                                @change="togglePermission"
                                class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                            />
                            <label :for="`perm-${perm.id}`" class="font-normal cursor-pointer text-sm">
                                {{ perm.display_name || perm.name }}
                                <span v-if="perm.description" class="text-gray-500"> — {{ perm.description }}</span>
                            </label>
                        </div>
                        <p v-if="props.permissions.length === 0" class="text-sm text-gray-500">
                            No permissions defined.
                        </p>
                        
                        <!-- Debug display -->
                        <div class="mt-4 p-3 bg-gray-100 rounded text-xs font-mono">
                            <strong>Selected IDs:</strong> {{ form.permission_ids.join(', ') || 'None' }}
                        </div>
                    </CardContent>
                </Card>

                <div class="flex gap-2">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : 'Save' }}
                    </Button>
                    <Link href="/superadmin/roles">
                        <Button type="button" variant="outline">Cancel</Button>
                    </Link>
                </div>
            </form>
        </main>
    </SuperAdminLayout>
</template>