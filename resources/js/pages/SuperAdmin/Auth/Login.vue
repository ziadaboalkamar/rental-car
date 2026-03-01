<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ShieldCheck } from 'lucide-vue-next';

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/superadmin/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-gray-900 px-4 py-12 sm:px-6 lg:px-8">
        <Head title="Super Admin Login" />

        <div class="w-full max-w-md space-y-8">
            <div class="flex flex-col items-center">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-indigo-500 text-white">
                    <ShieldCheck class="h-8 w-8" />
                </div>
                <h2 class="mt-6 text-center text-3xl font-bold tracking-tight text-white">
                    Super Admin Access
                </h2>
                <p class="mt-2 text-center text-sm text-gray-400">
                    Secure login for platform administrators only
                </p>
            </div>

            <form class="mt-8 space-y-6" @submit.prevent="submit">
                <div class="-space-y-px rounded-md shadow-sm">
                    <div class="space-y-2">
                        <Label for="email" class="text-white">Email address</Label>
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            placeholder="admin@platform.com"
                            class="bg-gray-800 text-white border-gray-700 focus:border-indigo-500"
                        />
                        <div v-if="form.errors.email" class="text-sm text-red-500 mt-1">
                            {{ form.errors.email }}
                        </div>
                    </div>
                    
                    <div class="space-y-2 pt-4">
                        <Label for="password" class="text-white">Password</Label>
                        <Input
                            id="password"
                            v-model="form.password"
                            type="password"
                            required
                            placeholder="••••••••"
                            class="bg-gray-800 text-white border-gray-700 focus:border-indigo-500"
                        />
                        <div v-if="form.errors.password" class="text-sm text-red-500 mt-1">
                            {{ form.errors.password }}
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input
                            id="remember-me"
                            v-model="form.remember"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-700 bg-gray-800 text-indigo-600 focus:ring-indigo-500"
                        />
                        <label for="remember-me" class="ml-2 block text-sm text-gray-400">
                            Remember me
                        </label>
                    </div>
                </div>

                <div>
                    <Button
                        type="submit"
                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded"
                        :disabled="form.processing"
                    >
                        <span v-if="form.processing">Authenticating...</span>
                        <span v-else>Sign in to Dashboard</span>
                    </Button>
                </div>
                
                <div class="text-center pt-4">
                    <Link href="/" class="text-sm text-gray-500 hover:text-gray-300 transition-colors">
                        ← Return to main site
                    </Link>
                </div>
            </form>
        </div>
    </div>
</template>
