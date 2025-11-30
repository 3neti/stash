<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { LucideIcon } from 'lucide-vue-next';

interface Props {
    title: string;
    value: number;
    icon?: LucideIcon;
    description?: string;
}

defineProps<Props>();
</script>

<template>
    <Card>
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">{{ title }}</CardTitle>
            <component :is="icon" v-if="icon" class="h-4 w-4 text-muted-foreground" />
        </CardHeader>
        <CardContent>
            <div class="text-2xl font-bold">{{ value.toLocaleString() }}</div>
            <p v-if="description" class="text-xs text-muted-foreground">
                {{ description }}
            </p>
        </CardContent>
    </Card>
</template>
