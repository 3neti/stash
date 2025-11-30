<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Link } from '@inertiajs/vue3';
import { FolderPlus, Upload } from 'lucide-vue-next';

const actions = [
    {
        title: 'New Campaign',
        description: 'Create a new document processing campaign',
        icon: FolderPlus,
        href: '/campaigns/create',
    },
    {
        title: 'Upload Documents',
        description: 'Upload documents to existing campaigns',
        icon: Upload,
        href: '/documents',
    },
];
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Quick Actions</CardTitle>
        </CardHeader>
        <CardContent class="grid gap-4">
            <Link
                v-for="action in actions"
                :key="action.title"
                :href="action.href"
                class="flex items-start gap-4 rounded-lg border p-4 transition-colors hover:bg-accent"
            >
                <component :is="action.icon" class="h-5 w-5 text-muted-foreground" />
                <div class="flex-1">
                    <p class="font-medium">{{ action.title }}</p>
                    <p class="text-sm text-muted-foreground">
                        {{ action.description }}
                    </p>
                </div>
            </Link>
        </CardContent>
    </Card>
</template>
