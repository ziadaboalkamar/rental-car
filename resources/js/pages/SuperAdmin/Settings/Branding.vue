<script setup lang="ts">
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    settings: {
        app_name: string;
        logo_url: string | null;
    };
    logoFiles: Array<{ id: number; url: string }>;
    actions: {
        update: string;
    };
}>();

const form = useForm({
    app_name: props.settings.app_name ?? '',
    logo_url: props.settings.logo_url ?? '',
    logo_temp_folders: [] as string[],
    logo_removed_files: [] as number[],
});

const fileUploadRef = ref<InstanceType<typeof FileUpload> | null>(null);
const logoTempFolders = ref<string[]>([]);
const logoRemovedFileIds = ref<number[]>([]);

watch(
    logoTempFolders,
    (value) => {
        form.logo_temp_folders = [...value];
    },
    { deep: true },
);

const previewName = computed(() => form.app_name || 'Real Rent Car');
const uploadedLogoUrl = computed(() => props.logoFiles?.[0]?.url || null);
const previewLogo = computed(() => uploadedLogoUrl.value || form.logo_url || '/logo/logo.png');

const handleLogoFileRemoved = (data: { type: string; fileId?: number }) => {
    if (data.type === 'existing' && data.fileId) {
        logoRemovedFileIds.value.push(data.fileId);
        form.logo_removed_files = [...new Set(logoRemovedFileIds.value)];
    }
};

const submit = () => {
    form
        .transform((data) => ({
            ...data,
            _method: 'put',
        }))
        .post(props.actions.update, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                logoTempFolders.value = [];
                form.logo_temp_folders = [];
                form.logo_removed_files = [];
                logoRemovedFileIds.value = [];
                fileUploadRef.value?.resetFiles();
            },
        });
};
</script>

<template>
    <Head title="Branding Settings" />

    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">Branding Settings</h1>
                    <p class="text-sm text-muted-foreground">
                        Update the global SaaS application name and default logo used across super admin and auth pages.
                    </p>
                </div>
                <Button :disabled="form.processing" @click="submit">
                    {{ form.processing ? 'Saving...' : 'Save Changes' }}
                </Button>
            </div>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
                <Card>
                    <CardHeader>
                        <CardTitle>Global Application Identity</CardTitle>
                        <CardDescription>
                            These values are used for the main SaaS branding, email display name, and shared layouts.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="app_name">SaaS Application Name</Label>
                            <Input id="app_name" v-model="form.app_name" placeholder="Real Rent Car" />
                            <p v-if="form.errors.app_name" class="text-sm text-red-600">
                                {{ form.errors.app_name }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label>Logo Upload</Label>
                            <FileUpload
                                ref="fileUploadRef"
                                v-model="logoTempFolders"
                                :initial-files="logoFiles || []"
                                :allow-multiple="false"
                                :max-files="1"
                                collection="logo"
                                theme="light"
                                width="100%"
                                @file-removed="handleLogoFileRemoved"
                            />
                            <p class="text-xs text-muted-foreground">
                                Upload the SaaS logo here. If no uploaded file exists, the fallback below or <code>/public/logo/logo.png</code> will be used.
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="logo_url">Fallback Logo URL</Label>
                            <Input id="logo_url" v-model="form.logo_url" placeholder="https://example.com/logo.png" />
                            <p class="text-xs text-muted-foreground">
                                Optional fallback used only when there is no uploaded logo file.
                            </p>
                            <p v-if="form.errors.logo_url" class="text-sm text-red-600">
                                {{ form.errors.logo_url }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Preview</CardTitle>
                        <CardDescription>How the brand appears in the shared application header.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="rounded-xl border bg-background p-4">
                            <div class="flex items-center gap-3">
                                <img :src="previewLogo" alt="Brand logo preview" class="h-10 w-10 rounded-md object-contain" />
                                <div class="min-w-0">
                                    <p class="truncate text-sm text-muted-foreground">Application name</p>
                                    <p class="truncate text-lg font-semibold">{{ previewName }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl border bg-muted/30 p-4">
                            <p class="text-sm font-medium">Notes</p>
                            <ul class="mt-2 space-y-2 text-sm text-muted-foreground">
                                <li>The name is used in shared layouts and browser titles.</li>
                                <li>The logo is used globally when a tenant-specific logo is not available.</li>
                                <li>Tenant websites can still override their own branding separately.</li>
                            </ul>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </main>
    </SuperAdminLayout>
</template>
