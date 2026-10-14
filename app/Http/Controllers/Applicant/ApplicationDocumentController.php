<?php

namespace App\Http\Controllers\Applicant;

use App\Actions\Applications\StoreApplicationDocument;
use App\Http\Controllers\Controller;
use App\Http\Requests\Applications\UploadDocumentRequest;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationDocumentController extends Controller
{
    public function store(UploadDocumentRequest $request, Application $application, StoreApplicationDocument $storeDocument): RedirectResponse
    {
        $type = $request->documentType();
        abort_if($type === null, 422);

        $storeDocument->handle($application, $type, $request->file('file'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('documents.uploaded')]);

        return back();
    }

    /**
     * Streams a verified document to its owner or authorized staff. Served
     * with a locked-down CSP and `nosniff`; images and PDFs may be shown
     * inline for preview, everything else is forced to download.
     */
    public function show(Request $request, ApplicationDocument $document, AuditLogger $audit): StreamedResponse
    {
        $document->load('application');
        Gate::authorize('view', $document);

        if ($request->user()->isStaff()) {
            $audit->record('document.viewed', $document, ['application' => $document->application->uuid]);
        }

        $inline = $request->boolean('inline');
        $extension = match ($document->mime_type) {
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            default => 'jpg',
        };
        $filename = $document->documentType()->value('code').'-'.substr($document->uuid, -8).'.'.$extension;

        $response = Storage::disk($document->disk)->response($document->path, $filename, [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Content-Security-Policy' => $document->mime_type === 'application/pdf'
                ? "default-src 'none'; frame-ancestors 'self'"
                : "default-src 'none'; img-src 'self'; frame-ancestors 'self'; sandbox",
        ], $inline ? 'inline' : 'attachment');

        return $response;
    }
}
