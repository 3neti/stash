<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';

interface ProcessorMetric {
    processor_id: string;
    processor: {
        name: string;
        category: string;
    };
    duration_ms: number | null;
    status: string;
    completed_at?: string;
}

interface Props {
    documentId: string;
}

const props = defineProps<Props>();

const metrics = ref<ProcessorMetric[]>([]);
const isLoading = ref(true);
const error = ref<string | null>(null);
let pollInterval: NodeJS.Timeout | null = null;

const fetchMetrics = async () => {
    try {
        const response = await fetch(`/api/documents/${props.documentId}/metrics`);
        if (!response.ok) {
            if (response.status === 404) {
                // Not found is OK, means no metrics yet
                metrics.value = [];
            } else {
                throw new Error(`HTTP ${response.status}`);
            }
        } else {
            metrics.value = await response.json();
        }
        error.value = null;
    } catch (err) {
        error.value = err instanceof Error ? err.message : 'Failed to fetch metrics';
    } finally {
        isLoading.value = false;
    }
};

const formatDuration = (ms: number | null | undefined): string => {
    if (!ms || ms === 0) return 'N/A';
    if (ms < 1000) return `${ms}ms`;
    return `${(ms / 1000).toFixed(2)}s`;
};

const totalDuration = computed((): number => {
    return metrics.value.reduce((sum, m) => sum + (m.duration_ms || 0), 0);
});

const completedCount = computed((): number => {
    return metrics.value.filter((m) => m.status === 'completed').length;
});

const getStatusBadgeClass = (status: string): string => {
    switch (status) {
        case 'completed':
            return 'bg-green-100 text-green-800';
        case 'running':
            return 'bg-blue-100 text-blue-800';
        case 'failed':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const startPolling = () => {
    if (pollInterval) {
        clearInterval(pollInterval);
    }
    // Poll every 3 seconds
    pollInterval = setInterval(fetchMetrics, 3000);
};

const stopPolling = () => {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
};

watch(
    () => props.documentId,
    () => {
        fetchMetrics();
        startPolling();
    },
);

onMounted(() => {
    fetchMetrics();
    startPolling();
});

onMounted(() => {
    return () => {
        stopPolling();
    };
});
</script>

<template>
    <div class="w-full space-y-4">
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <h3 class="mb-4 font-semibold text-gray-900">Processing Metrics</h3>

            <!-- Summary Stats -->
            <div class="mb-4 grid grid-cols-3 gap-4">
                <div class="rounded-md bg-gray-50 p-3">
                    <p class="text-sm text-gray-600">Total Duration</p>
                    <p class="text-lg font-semibold text-gray-900">
                        {{ formatDuration(totalDuration) }}
                    </p>
                </div>
                <div class="rounded-md bg-gray-50 p-3">
                    <p class="text-sm text-gray-600">Completed</p>
                    <p class="text-lg font-semibold text-gray-900">
                        {{ completedCount }} / {{ metrics.length }}
                    </p>
                </div>
                <div class="rounded-md bg-gray-50 p-3">
                    <p class="text-sm text-gray-600">Average Time</p>
                    <p class="text-lg font-semibold text-gray-900">
                        {{
                            completedCount > 0
                                ? formatDuration(totalDuration / completedCount)
                                : 'N/A'
                        }}
                    </p>
                </div>
            </div>

            <!-- Metrics Table -->
            <div v-if="metrics.length > 0" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 border-collapse">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-sm font-medium text-gray-700">
                                Processor
                            </th>
                            <th class="px-3 py-2 text-left text-sm font-medium text-gray-700">
                                Status
                            </th>
                            <th class="px-3 py-2 text-right text-sm font-medium text-gray-700">
                                Duration
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="metric in metrics" :key="metric.processor_id" class="hover:bg-gray-50">
                            <td class="px-3 py-2 text-sm text-gray-900">
                                <div class="font-medium">{{ metric.processor.name }}</div>
                                <div class="text-xs text-gray-500">
                                    {{ metric.processor.category }}
                                </div>
                            </td>
                            <td class="px-3 py-2 text-sm">
                                <span
                                    class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="getStatusBadgeClass(metric.status)"
                                >
                                    {{ metric.status }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right text-sm font-mono text-gray-900">
                                {{ formatDuration(metric.duration_ms) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty State -->
            <div v-else-if="!isLoading" class="rounded-md bg-gray-50 p-4 text-center text-sm text-gray-600">
                No processing metrics available yet
            </div>

            <!-- Loading State -->
            <div v-else class="text-center text-sm text-gray-500">
                Loading metrics...
            </div>

            <!-- Error Message -->
            <div
                v-if="error"
                class="mt-4 rounded-md bg-red-50 p-3 text-sm text-red-700"
            >
                {{ error }}
            </div>
        </div>
    </div>
</template>
