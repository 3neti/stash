export interface Campaign {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    state: 'draft' | 'active' | 'paused' | 'archived';
    type: 'template' | 'custom' | 'meta';
    pipeline_config: Record<string, unknown> | null;
    settings: Record<string, unknown> | null;
    documents_count?: number;
    created_at: string;
    updated_at: string;
}

export interface CampaignFilters {
    state?: string;
    search?: string;
}
