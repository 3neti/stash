<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Documents\Web\ListDocuments;
use App\Actions\Documents\Web\ShowDocument;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    /**
     * Display documents list.
     */
    public function index(): Response
    {
        $documents = ListDocuments::run(
            campaign: null,
            status: request('status'),
            search: request('search'),
            perPage: (int) request('per_page', 15)
        );

        return Inertia::render('documents/Index', [
            'documents' => $documents,
            'filters' => [
                'status' => request('status'),
                'search' => request('search'),
            ],
        ]);
    }

    /**
     * Display single document.
     */
    public function show(string $uuid): Response
    {
        $document = ShowDocument::run($uuid);

        return Inertia::render('documents/Show', [
            'document' => $document,
        ]);
    }
}
