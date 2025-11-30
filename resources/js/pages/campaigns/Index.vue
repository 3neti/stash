<script setup lang="ts">
import CampaignCard from '@/components/CampaignCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Campaign, CampaignFilters } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Props {
    campaigns: {
        data: Campaign[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: CampaignFilters;
}

const props = defineProps<Props>();

const search = ref(props.filters.search || '');

watch(search, (value) => {
    router.get(
        '/campaigns',
        { search: value },
        { preserveState: true, replace: true }
    );
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Campaigns', href: '/campaigns' },
];
</script>

<template>
    <Head title="Campaigns" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Campaigns</h1>
                <Button as-child>
                    <Link href="/campaigns/create">
                        <Plus class="mr-2 h-4 w-4" />
                        New Campaign
                    </Link>
                </Button>
            </div>

            <div class="flex items-center gap-4">
                <Input
                    v-model="search"
                    placeholder="Search campaigns..."
                    class="max-w-sm"
                />
            </div>

            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <CampaignCard
                    v-for="campaign in campaigns.data"
                    :key="campaign.id"
                    :campaign="campaign"
                />
            </div>

            <div v-if="campaigns.data.length === 0" class="text-center py-12">
                <p class="text-muted-foreground">No campaigns found</p>
            </div>
        </div>
    </AppLayout>
</template>
