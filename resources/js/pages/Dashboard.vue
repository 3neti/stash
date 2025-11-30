<script setup lang="ts">
import QuickActions from '@/components/QuickActions.vue';
import StatsCard from '@/components/StatsCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem, type DashboardStats } from '@/types';
import { Head } from '@inertiajs/vue3';
import {
    FileText,
    FolderOpen,
    CheckCircle,
    Clock,
    AlertCircle,
} from 'lucide-vue-next';

interface Props {
    stats: DashboardStats;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div data-testid="stats-grid" class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <StatsCard
                    data-testid="total-campaigns-stat"
                    title="Total Campaigns"
                    :value="stats.campaigns.total"
                    :icon="FolderOpen"
                    :description="`${stats.campaigns.active} active, ${stats.campaigns.paused} paused`"
                />
                <StatsCard
                    data-testid="total-documents-stat"
                    title="Total Documents"
                    :value="stats.documents.total"
                    :icon="FileText"
                />
                <StatsCard
                    data-testid="processing-documents-stat"
                    title="Processing"
                    :value="stats.documents.processing"
                    :icon="Clock"
                    :description="`${stats.documents.pending} pending`"
                />
                <StatsCard
                    data-testid="completed-documents-stat"
                    title="Completed"
                    :value="stats.documents.completed"
                    :icon="CheckCircle"
                    :description="`${stats.documents.failed} failed`"
                />
            </div>
            <div data-testid="quick-actions" class="grid gap-4 md:grid-cols-2">
                <QuickActions />
            </div>
        </div>
    </AppLayout>
</template>
