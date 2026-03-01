<script setup lang="ts">
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

const props = defineProps<{
    tenant: {
        id: number;
        name: string;
        slug: string;
        domain: string | null;
        email: string | null;
        phone: string | null;
        plan_id: number | null;
        subscription_plan?: { id: number; name: string } | null;
        is_active: boolean;
    };
    plans: Array<{ id: number; name: string }>;
    admin_user: { id: number; name: string; email: string } | null;
}>();

const form = useForm({
    name: props.tenant.name,
    slug: props.tenant.slug,
    domain: props.tenant.domain ?? '',
    email: props.tenant.email ?? '',
    phone: props.tenant.phone ?? '',
    plan_id: props.tenant.plan_id ? String(props.tenant.plan_id) : '',
    is_active: props.tenant.is_active,
    admin_password: '',
    admin_password_confirmation: '',
});

const submit = () => {
    form.put(`/superadmin/tenants/${props.tenant.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`Edit: ${props.tenant.name}`" />
    <SuperAdminLayout>
        <main class="flex-1 p-8 space-y-6">
            <div class="flex items-center gap-4">
                <Link :href="`/superadmin/tenants/${props.tenant.id}`">
                    <Button variant="outline">← Back</Button>
                </Link>
                <h1 class="text-2xl font-semibold">Edit Tenant</h1>
            </div>

            <Card class="max-w-2xl">
                <CardHeader>
                    <CardTitle>Tenant Information</CardTitle>
                    <CardDescription>Update company and contact details</CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">Company Name *</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                type="text"
                                placeholder="ABC Rent a Car"
                                required
                            />
                            <div v-if="form.errors.name" class="text-sm text-red-600">
                                {{ form.errors.name }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="slug">Subdomain (Slug) *</Label>
                            <div class="flex items-center gap-1">
                                <Input
                                    id="slug"
                                    v-model="form.slug"
                                    type="text"
                                    placeholder="company-slug"
                                    required
                                />
                                <span class="text-sm text-muted-foreground whitespace-nowrap">.{{ $page.props.app_url_base || 'localhost' }}</span>
                            </div>
                            <p class="text-xs text-muted-foreground">Tenant unique URL: company.domain.com</p>
                            <div v-if="form.errors.slug" class="text-sm text-red-600">
                                {{ form.errors.slug }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="email">Contact Email *</Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                placeholder="contact@company.com"
                                required
                            />
                            <div v-if="form.errors.email" class="text-sm text-red-600">
                                {{ form.errors.email }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="domain">Custom Domain (optional)</Label>
                            <Input
                                id="domain"
                                v-model="form.domain"
                                type="text"
                                placeholder="companycars.com"
                            />
                            <p class="text-xs text-muted-foreground">Optional. Enter a domain only (no http:// or https://).</p>
                            <div v-if="form.errors.domain" class="text-sm text-red-600">
                                {{ form.errors.domain }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="phone">Phone Number</Label>
                            <Input
                                id="phone"
                                v-model="form.phone"
                                type="tel"
                                placeholder="+1 (555) 123-4567"
                            />
                            <div v-if="form.errors.phone" class="text-sm text-red-600">
                                {{ form.errors.phone }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="plan_id">Subscription Plan *</Label>
                            <Select v-model="form.plan_id" required>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select a plan" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="planOption in props.plans"
                                        :key="planOption.id"
                                        :value="String(planOption.id)"
                                    >
                                        {{ planOption.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="props.plans.length === 0" class="text-xs text-amber-600">
                                No plans found. Create plans first in Product Management.
                            </p>
                            <div v-if="form.errors.plan_id" class="text-sm text-red-600">
                                {{ form.errors.plan_id }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="is_active">Status</Label>
                            <Select 
                                :model-value="form.is_active ? '1' : '0'"
                                @update:model-value="(val: any) => form.is_active = val === '1'"
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="1">Active</SelectItem>
                                    <SelectItem value="0">Inactive</SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-sm text-muted-foreground">Inactive tenants cannot be used by their users.</p>
                            <div v-if="form.errors.is_active" class="text-sm text-red-600">
                                {{ form.errors.is_active }}
                            </div>
                        </div>

                        <div v-if="admin_user" class="border-t pt-6 mt-6 space-y-4">
                            <h3 class="text-lg font-medium">Change admin login password</h3>
                            <p class="text-sm text-muted-foreground">
                                Optional. Leave blank to keep current password. Admin: {{ admin_user.email }}
                            </p>
                            <div class="space-y-2">
                                <Label for="admin_password">New password</Label>
                                <Input
                                    id="admin_password"
                                    v-model="form.admin_password"
                                    type="password"
                                    placeholder="••••••••"
                                    autocomplete="new-password"
                                />
                                <div v-if="form.errors.admin_password" class="text-sm text-red-600">
                                    {{ form.errors.admin_password }}
                                </div>
                            </div>
                            <div class="space-y-2">
                                <Label for="admin_password_confirmation">Confirm new password</Label>
                                <Input
                                    id="admin_password_confirmation"
                                    v-model="form.admin_password_confirmation"
                                    type="password"
                                    placeholder="••••••••"
                                    autocomplete="new-password"
                                />
                            </div>
                        </div>

                        <div class="flex gap-2 pt-4">
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? 'Saving...' : 'Save Changes' }}
                            </Button>
                            <Link :href="`/superadmin/tenants/${props.tenant.id}`">
                                <Button type="button" variant="outline">Cancel</Button>
                            </Link>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </main>
    </SuperAdminLayout>
</template>
