<script setup lang="ts">
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface SocialLoginSettings {
    google: {
        enabled: boolean;
        client_id: string;
        client_secret: string;
    };
    apple: {
        enabled: boolean;
        client_id: string;
        client_secret: string;
    };
}

const props = defineProps<{
    socialLoginSettings: SocialLoginSettings;
}>();

const form = useForm<{
    social_login: SocialLoginSettings;
}>({
    social_login: JSON.parse(JSON.stringify(props.socialLoginSettings || {
        google: { enabled: false, client_id: '', client_secret: '' },
        apple: { enabled: false, client_id: '', client_secret: '' },
    })),
});

const submit = () => {
    form.put('/superadmin/settings/login', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Login Settings" />
    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">Login Settings</h1>
                    <p class="text-sm text-muted-foreground">
                        Configure authenticators including Google and Apple Socialite credentials.
                    </p>
                </div>
                <Button :disabled="form.processing" @click="submit">
                    {{ form.processing ? 'Saving...' : 'Save Changes' }}
                </Button>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <Card>
                    <CardHeader>
                        <CardTitle>Social Login Integrations</CardTitle>
                        <CardDescription>
                            Configure dynamic provider credentials for tenant clients.
                            Leave the Client Secret fields blank to retain the currently saved values.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="space-y-4 rounded-md border p-4">
                            <h3 class="text-sm font-semibold">Google Auth</h3>

                            <label class="flex items-center gap-3">
                                <input v-model="form.social_login.google.enabled" type="checkbox" class="h-4 w-4" />
                                <span class="text-sm font-medium">Enable Google Login</span>
                            </label>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="google_client_id">Client ID</Label>
                                    <Input id="google_client_id" v-model="form.social_login.google.client_id" />
                                    <p v-if="form.errors['social_login.google.client_id']" class="text-sm text-red-600">
                                        {{ form.errors['social_login.google.client_id'] }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="google_client_secret">Client Secret</Label>
                                    <Input id="google_client_secret" v-model="form.social_login.google.client_secret" type="password" />
                                    <p v-if="form.errors['social_login.google.client_secret']" class="text-sm text-red-600">
                                        {{ form.errors['social_login.google.client_secret'] }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4 rounded-md border p-4">
                            <h3 class="text-sm font-semibold">Apple Auth</h3>

                            <label class="flex items-center gap-3">
                                <input v-model="form.social_login.apple.enabled" type="checkbox" class="h-4 w-4" />
                                <span class="text-sm font-medium">Enable Apple Login</span>
                            </label>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="apple_client_id">Client ID</Label>
                                    <Input id="apple_client_id" v-model="form.social_login.apple.client_id" />
                                    <p v-if="form.errors['social_login.apple.client_id']" class="text-sm text-red-600">
                                        {{ form.errors['social_login.apple.client_id'] }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="apple_client_secret">Client Secret / Private Key</Label>
                                    <Input id="apple_client_secret" v-model="form.social_login.apple.client_secret" type="password" />
                                    <p v-if="form.errors['social_login.apple.client_secret']" class="text-sm text-red-600">
                                        {{ form.errors['social_login.apple.client_secret'] }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : 'Save Changes' }}
                    </Button>
                </div>
            </form>
        </main>
    </SuperAdminLayout>
</template>
