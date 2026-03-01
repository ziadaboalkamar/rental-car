<script setup lang="ts">
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

const props = defineProps<{
    user: {
        id: number;
        name: string;
        email: string;
        roles: Array<{ id: number; name: string; display_name: string | null }>;
    };
    roles: Array<{ id: number; name: string; display_name: string | null; description: string | null }>;
}>();

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    password_confirmation: '',
    role_ids: props.user.roles.map((r) => r.id),
});

const submit = () => {
    console.log('Submitting with role_ids:', form.role_ids);
    form.role_ids = [...new Set(form.role_ids)].map((id) => Number(id));
    form.put(`/superadmin/users/${props.user.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Edit User: ${props.user.name}`" />
    <SuperAdminLayout>
        <main class="flex-1 p-8 space-y-6">
            <div class="flex items-center gap-4">
                <Link href="/superadmin/users">
                    <Button variant="outline">← Back</Button>
                </Link>
                <h1 class="text-2xl font-semibold">Edit user information and roles</h1>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <Card class="max-w-2xl">
                    <CardHeader>
                        <CardTitle>User Information</CardTitle>
                        <CardDescription>Edit the user's basic information.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">Name</Label>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                placeholder="Enter user name"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary"
                            />
                            <span v-if="form.errors.name" class="text-xs text-red-600">{{ form.errors.name }}</span>
                        </div>

                        <div class="space-y-2">
                            <Label for="email">Email</Label>
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                placeholder="Enter user email"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary"
                            />
                            <span v-if="form.errors.email" class="text-xs text-red-600">{{ form.errors.email }}</span>
                        </div>

                        <div class="space-y-2">
                            <Label for="password">Password (leave empty to keep current)</Label>
                            <input
                                id="password"
                                v-model="form.password"
                                type="password"
                                placeholder="Enter new password"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary"
                            />
                            <span v-if="form.errors.password" class="text-xs text-red-600">{{ form.errors.password }}</span>
                        </div>

                        <div class="space-y-2">
                            <Label for="password_confirmation">Confirm Password</Label>
                            <input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                placeholder="Confirm new password"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary"
                            />
                            <span v-if="form.errors.password_confirmation" class="text-xs text-red-600">{{ form.errors.password_confirmation }}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card class="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Roles</CardTitle>
                        <CardDescription>Assign one or more roles. The user will have all permissions attached to these roles (manage roles under User Management → Roles).</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div
                            v-for="role in props.roles"
                            :key="role.id"
                            class="flex items-center space-x-2"
                        >
                            <input
                                type="checkbox"
                                :id="`role-${role.id}`"
                                :value="role.id"
                                v-model="form.role_ids"
                                class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                            />
                            <label :for="`role-${role.id}`" class="font-normal cursor-pointer text-sm">
                                {{ role.display_name || role.name }}
                                <span v-if="role.description" class="text-gray-500"> — {{ role.description }}</span>
                            </label>
                        </div>
                        <p v-if="props.roles.length === 0" class="text-sm text-gray-500">
                            No roles defined. Create roles under User Management → Roles first.
                        </p>
                        
                        <!-- Debug display -->
                        <div class="mt-4 p-3 bg-gray-100 rounded text-xs font-mono">
                            <strong>Selected Role IDs:</strong> {{ form.role_ids.join(', ') || 'None' }}
                        </div>
                    </CardContent>
                </Card>

                <div class="flex gap-2">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : 'Save' }}
                    </Button>
                    <Link href="/superadmin/users">
                        <Button type="button" variant="outline">Cancel</Button>
                    </Link>
                </div>
            </form>
        </main>
    </SuperAdminLayout>
</template>