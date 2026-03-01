<script setup lang="ts">
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

defineProps<{
    title: string;
}>();
</script>

<template>
    <Head :title="title" />
    <SuperAdminLayout>
        <main class="flex-1 p-8">
            <Card class="max-w-2xl">
                <CardHeader>
                    <CardTitle>{{ title }}</CardTitle>
                    <CardDescription>This section is under development.</CardDescription>
                </CardHeader>
                <CardContent>
                    <p class="text-muted-foreground">Content will be added here.</p>
                </CardContent>
            </Card>
        </main>
    </SuperAdminLayout>
</template>
