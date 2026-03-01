<script setup lang="ts">
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

const props = defineProps<{
    roles: Array<{ id: number; name: string; display_name: string | null; description: string | null }>;
}>();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role_ids: [] as number[],
});

const submit = () => {
    console.log('Submitting with role_ids:', form.role_ids);
    form.post('/superadmin/users', { preserveScroll: true });
};
</script>

<template>
    <Head title="Add Super Admin User" />
    <SuperAdminLayout>
        <main class="flex-1 p-8 space-y-6">
            <div class="flex items-center gap-4">
                <Link href="/superadmin/users">
                    <Button variant="outline">← Back</Button>
                </Link>
                <h1 class="text-2xl font-semibold">Add Super Admin User</h1>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <Card class="max-w-2xl">
                    <CardHeader>
                        <CardTitle>New user</CardTitle>
                        <CardDescription>This user will be able to log in at the Super Admin login and manage pages. Assign roles and permissions after creation.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">Name *</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                type="text"
                                placeholder="Jane Doe"
                                required
                            />
                            <div v-if="form.errors.name" class="text-sm text-red-600">{{ form.errors.name }}</div>
                        </div>
                        <div class="space-y-2">
                            <Label for="email">Email *</Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                placeholder="jane@example.com"
                                required
                            />
                            <div v-if="form.errors.email" class="text-sm text-red-600">{{ form.errors.email }}</div>
                        </div>
                        <div class="space-y-2">
                            <Label for="password">Password *</Label>
                            <Input
                                id="password"
                                v-model="form.password"
                                type="password"
                                placeholder="••••••••"
                                required
                                autocomplete="new-password"
                            />
                            <div v-if="form.errors.password" class="text-sm text-red-600">{{ form.errors.password }}</div>
                        </div>
                        <div class="space-y-2">
                            <Label for="password_confirmation">Confirm password *</Label>
                            <Input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                placeholder="••••••••"
                                required
                                autocomplete="new-password"
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card class="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Roles</CardTitle>
                        <CardDescription>Assign one or more roles. The user will have all permissions attached to these roles.</CardDescription>
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
                        {{ form.processing ? 'Creating...' : 'Create User' }}
                    </Button>
                    <Link href="/superadmin/users">
                        <Button type="button" variant="outline">Cancel</Button>
                    </Link>
                </div>
            </form>
        </main>
    </SuperAdminLayout>
</template>