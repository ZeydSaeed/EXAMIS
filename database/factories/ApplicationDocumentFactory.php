<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApplicationDocument>
 */
class ApplicationDocumentFactory extends Factory
{
    /**
     * Writes a tiny valid PDF to the documents disk so downloads work.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $contents = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";
        $path = 'factory/'.Str::random(40);

        Storage::disk((string) config('examis.documents.disk'))->put($path, $contents);

        return [
            'application_id' => Application::factory(),
            'document_type_id' => DocumentType::factory(),
            'uploaded_by' => fn (array $attributes) => Application::query()->whereKey($attributes['application_id'])->value('user_id'),
            'disk' => config('examis.documents.disk'),
            'path' => $path,
            'original_name' => 'document.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'status' => DocumentStatus::Pending,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Verified,
            'scanned_at' => now(),
        ]);
    }
}
