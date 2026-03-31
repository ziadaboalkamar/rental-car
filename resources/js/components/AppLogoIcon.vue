<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();

const page = usePage<any>();
const logoUrl = computed(() => page.props.app_branding?.logo_url || '/logo/logo.png');
</script>

<template>
   <img :src="logoUrl" alt="" class="h-5 object-contain" />
</template>
