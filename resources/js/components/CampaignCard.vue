<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { Campaign } from '@/types';
import { Link } from '@inertiajs/vue3';
import { FileText, FolderOpen } from 'lucide-vue-next';

interface Props {
    campaign: Campaign;
}

defineProps<Props>();
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-2">
                    <FolderOpen class="h-5 w-5 text-muted-foreground" />
                    <CardTitle>{{ campaign.name }}</CardTitle>
                </div>
                <Badge :variant="campaign.status === 'active' ? 'default' : 'secondary'">
                    {{ campaign.status }}
                </Badge>
            </div>
            <CardDescription v-if="campaign.description">
                {{ campaign.description }}
            </CardDescription>
        </CardHeader>
        <CardContent>
            <div class="flex items-center gap-2 text-sm text-muted-foreground">
                <FileText class="h-4 w-4" />
                <span>{{ campaign.documents_count || 0 }} documents</span>
            </div>
        </CardContent>
        <CardFooter>
            <Button as-child variant="outline" size="sm">
                <Link :href="`/campaigns/${campaign.id}`">View Details</Link>
            </Button>
        </CardFooter>
    </Card>
</template>
