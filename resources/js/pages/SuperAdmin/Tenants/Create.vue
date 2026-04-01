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
import { ref, watch } from 'vue';

const props = defineProps<{
    plans: Array<{ id: number; name: string }>;
}>();

const form = useForm({
    name: '',
    slug: '',
    domain: '',
    email: '',
    phone: '',
    plan_id: props.plans[0]?.id ? String(props.plans[0].id) : '',
    admin_name: '',
    admin_email: '',
    admin_password: '',
    admin_password_confirmation: '',
});

const slugManuallyEdited = ref(false);

const slugify = (value: string) =>
    value
        .toLowerCase()
        .trim()
        .replace(/\s+/g, '-')
        .replace(/[^\w-]+/g, '')
        .replace(/-{2,}/g, '-')
        .replace(/^-+|-+$/g, '');

watch(() => form.name, (newName, oldName) => {
    const previousAutoSlug = slugify(oldName ?? '');

    if (!slugManuallyEdited.value || !form.slug || form.slug === previousAutoSlug) {
        form.slug = slugify(newName);
    }
});

watch(() => form.slug, (newSlug) => {
    if (!newSlug) {
        slugManuallyEdited.value = false;
        return;
    }

    slugManuallyEdited.value = newSlug !== slugify(form.name);
});

const submit = () => {
    form.post('/superadmin/tenants', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Create Tenant" />
    <SuperAdminLayout>
        <main class="flex-1 p-8 space-y-6">
            <div class="flex items-center gap-4">
                <Link href="/superadmin/tenants">
                    <Button variant="outline">← Back</Button>
                </Link>
                <h1 class="text-2xl font-semibold">Create New Tenant</h1>
            </div>

            <Card class="max-w-2xl">
                <CardHeader>
                    <CardTitle>Tenant Information</CardTitle>
                    <CardDescription>Create a new rental company tenant</CardDescription>
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

                        <div class="border-t pt-6 mt-6 space-y-4">
                            <h3 class="text-lg font-medium">Tenant Admin Login</h3>
                            <p class="text-sm text-muted-foreground">
                                Create an admin user for this tenant. They will use this email and password to log in to the admin dashboard.
                            </p>
                            <div class="space-y-2">
                                <Label for="admin_name">Admin Name *</Label>
                                <Input
                                    id="admin_name"
                                    v-model="form.admin_name"
                                    type="text"
                                    placeholder="John Doe"
                                    required
                                />
                                <div v-if="form.errors.admin_name" class="text-sm text-red-600">
                                    {{ form.errors.admin_name }}
                                </div>
                            </div>
                            <div class="space-y-2">
                                <Label for="admin_email">Admin Email (login) *</Label>
                                <Input
                                    id="admin_email"
                                    v-model="form.admin_email"
                                    type="email"
                                    placeholder="admin@company.com"
                                    required
                                />
                                <div v-if="form.errors.admin_email" class="text-sm text-red-600">
                                    {{ form.errors.admin_email }}
                                </div>
                            </div>
                            <div class="space-y-2">
                                <Label for="admin_password">Admin Password *</Label>
                                <Input
                                    id="admin_password"
                                    v-model="form.admin_password"
                                    type="password"
                                    placeholder="••••••••"
                                    required
                                    autocomplete="new-password"
                                />
                                <div v-if="form.errors.admin_password" class="text-sm text-red-600">
                                    {{ form.errors.admin_password }}
                                </div>
                            </div>
                            <div class="space-y-2">
                                <Label for="admin_password_confirmation">Confirm Password *</Label>
                                <Input
                                    id="admin_password_confirmation"
                                    v-model="form.admin_password_confirmation"
                                    type="password"
                                    placeholder="••••••••"
                                    required
                                    autocomplete="new-password"
                                />
                            </div>
                        </div>

                        <div class="flex gap-2 pt-4">
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? 'Creating...' : 'Create Tenant' }}
                            </Button>
                            <Link href="/superadmin/tenants">
                                <Button type="button" variant="outline">Cancel</Button>
                            </Link>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </main>
    </SuperAdminLayout>
</template>
