<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Dashboard\GetDashboardStats;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display dashboard.
     */
    public function __invoke(): Response
    {
        $stats = GetDashboardStats::run();

        return Inertia::render('Dashboard', [
            'stats' => $stats,
        ]);
    }
}
