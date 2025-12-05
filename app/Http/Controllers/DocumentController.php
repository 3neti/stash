<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Documents\Web\ListDocuments;
use App\Actions\Documents\Web\ShowDocument;
use App\Models\KycTransaction;
use App\Models\ProcessorExecution;
use App\Services\Tenancy\TenancyService;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    /**
     * Download signed document from ProcessorExecution media.
     * 
     * Public endpoint - no authentication required.
     * Security relies on processor execution UUID being hard to guess.
     */
    public function downloadSigned(string $tenant, string $execution): BinaryFileResponse
    {
        // Find tenant
        $tenantModel = \App\Models\Tenant::on('central')->find($tenant);
        
        if (!$tenantModel) {
            abort(404, 'Tenant not found');
        }

        // Initialize tenant context
        app(TenancyService::class)->initializeTenant($tenantModel);

        // Find execution in tenant database
        $exec = ProcessorExecution::find($execution);

        if (!$exec) {
            abort(404, 'Signed document not found');
        }

        // Get signed document from media
        $signedDoc = $exec->getFirstMedia('signed_documents');

        if (!$signedDoc) {
            abort(404, 'Signed document file not found');
        }

        // Stream the file
        return response()->download(
            $signedDoc->getPath(),
            $signedDoc->file_name,
            [
                'Content-Type' => $signedDoc->mime_type,
                'Content-Disposition' => 'attachment; filename="' . $signedDoc->file_name . '"',
            ]
        );
    }
}
