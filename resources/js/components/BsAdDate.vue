<script setup lang="ts">
import { useCalendar } from '@/composables/useCalendar';

defineProps<{ date: string | null | undefined }>();
const { format } = useCalendar();
</script>

<template>
    <time :datetime="date ?? ''">{{ format(date) }}</time>
</template>
