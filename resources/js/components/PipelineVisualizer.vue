<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChevronRight } from 'lucide-vue-next';

interface Props {
    pipelineConfig?: Record<string, unknown> | null;
}

defineProps<Props>();

const defaultSteps = ['OCR', 'Classification', 'Extraction', 'Validation'];
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Processing Pipeline</CardTitle>
            <CardDescription>Document processing workflow</CardDescription>
        </CardHeader>
        <CardContent>
            <div class="flex items-center gap-2 overflow-x-auto">
                <div
                    v-for="(step, index) in defaultSteps"
                    :key="step"
                    class="flex items-center gap-2"
                >
                    <div
                        class="flex items-center justify-center rounded-lg border bg-card p-4 min-w-[100px]"
                    >
                        <span class="text-sm font-medium">{{ step }}</span>
                    </div>
                    <ChevronRight
                        v-if="index < defaultSteps.length - 1"
                        class="h-5 w-5 text-muted-foreground"
                    />
                </div>
            </div>
        </CardContent>
    </Card>
</template>
