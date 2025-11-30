<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { computed } from 'vue';

interface Props {
    status: 'pending' | 'processing' | 'completed' | 'failed';
}

const props = defineProps<Props>();

const statusConfig = computed(() => {
    switch (props.status) {
        case 'completed':
            return {
                label: 'Completed',
                variant: 'default' as const,
            };
        case 'processing':
            return {
                label: 'Processing',
                variant: 'secondary' as const,
            };
        case 'pending':
            return {
                label: 'Pending',
                variant: 'outline' as const,
            };
        case 'failed':
            return {
                label: 'Failed',
                variant: 'destructive' as const,
            };
        default:
            return {
                label: 'Unknown',
                variant: 'outline' as const,
            };
    }
});
</script>

<template>
    <Badge data-testid="processing-status" :variant="statusConfig.variant">
        {{ statusConfig.label }}
    </Badge>
</template>
