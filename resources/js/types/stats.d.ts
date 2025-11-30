export interface DashboardStats {
    campaigns: {
        total: number;
        active: number;
        draft: number;
        paused: number;
        archived: number;
    };
    documents: {
        total: number;
        pending: number;
        processing: number;
        completed: number;
        failed: number;
    };
}
