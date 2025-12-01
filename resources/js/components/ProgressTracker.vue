<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';

interface Props {
    documentUuid: string;
}

interface ProgressData {
    status: string;
    percentage_complete: number;
    stage_count: number;
    completed_stages: number;
    current_stage: string | null;
    updated_at?: string;
}

const props = defineProps<Props>();

const progress = ref<ProgressData>({
    status: 'pending',
    percentage_complete: 0,
    stage_count: 0,
    completed_stages: 0,
    current_stage: null,
});

const isLoading = ref(true);
const error = ref<string | null>(null);
let pollInterval: NodeJS.Timeout | null = null;

const fetchProgress = async () => {
    try {
        const response = await fetch(`/api/documents/${props.documentUuid}/progress`);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        progress.value = await response.json();
        error.value = null;

        // Stop polling if completed or failed
        if (progress.value.status === 'completed' || progress.value.status === 'failed') {
            if (pollInterval) {
                clearInterval(pollInterval);
                pollInterval = null;
            }
        }
    } catch (err) {
        error.value = err instanceof Error ? err.message : 'Failed to fetch progress';
    } finally {
        isLoading.value = false;
    }
};

const getStatusColor = (): string => {
    switch (progress.value.status) {
        case 'completed':
            return 'bg-green-500';
        case 'failed':
            return 'bg-red-500';
        case 'processing':
            return 'bg-blue-500';
        default:
            return 'bg-gray-300';
    }
};

const getStatusText = (): string => {
    switch (progress.value.status) {
        case 'completed':
            return 'Completed';
        case 'failed':
            return 'Failed';
        case 'processing':
            return 'Processing';
        case 'pending':
            return 'Pending';
        default:
            return 'Unknown';
    }
};

const startPolling = () => {
    if (pollInterval) {
        clearInterval(pollInterval);
    }
    // Poll every 2 seconds
    pollInterval = setInterval(fetchProgress, 2000);
};

const stopPolling = () => {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
};

watch(
    () => props.documentUuid,
    () => {
        fetchProgress();
        startPolling();
    },
);

onMounted(() => {
    fetchProgress();
    startPolling();
});

onMounted(() => {
    return () => {
        stopPolling();
    };
});
</script>

<template>
    <div class="w-full space-y-3 rounded-lg border border-gray-200 bg-white p-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div
                    class="h-3 w-3 rounded-full"
                    :class="getStatusColor()"
                />
                <h3 class="font-semibold text-gray-900">Processing Progress</h3>
            </div>
            <span class="text-sm font-medium text-gray-600">{{ getStatusText() }}</span>
        </div>

        <!-- Progress Bar -->
        <div class="space-y-1">
            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-600">Progress</span>
                <span class="font-semibold text-gray-900">{{ progress.percentage_complete }}%</span>
            </div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100">
                <div
                    class="h-full bg-blue-500 transition-all duration-300"
                    :style="{ width: `${progress.percentage_complete}%` }"
                />
            </div>
        </div>

        <!-- Stage Information -->
        <div class="grid grid-cols-2 gap-4 pt-2 text-sm">
            <div>
                <span class="text-gray-600">Stages Completed</span>
                <p class="font-semibold text-gray-900">
                    {{ progress.completed_stages }} / {{ progress.stage_count }}
                </p>
            </div>
            <div>
                <span class="text-gray-600">Current Stage</span>
                <p class="truncate font-semibold text-gray-900">
                    {{ progress.current_stage || 'N/A' }}
                </p>
            </div>
        </div>

        <!-- Error Message -->
        <div
            v-if="error"
            class="rounded-md bg-red-50 p-3 text-sm text-red-700"
        >
            {{ error }}
        </div>

        <!-- Loading State -->
        <div
            v-if="isLoading"
            class="text-center text-sm text-gray-500"
        >
            Loading progress...
        </div>
    </div>
</template>
