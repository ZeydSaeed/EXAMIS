<?php

namespace Database\Factories;

use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentType>
 */
class DocumentTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'doc_'.fake()->unique()->lexify('??????'),
            'name_ar' => 'مستند',
            'name_en' => 'Document',
            'allowed_mime_types' => ['image/jpeg', 'image/png', 'application/pdf'],
            'max_size_kb' => 2048,
            'is_photo' => false,
        ];
    }

    public function photo(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'personal_photo_'.fake()->unique()->lexify('????'),
            'name_ar' => 'الصورة الشخصية',
            'name_en' => 'Personal photo',
            'allowed_mime_types' => ['image/jpeg', 'image/png'],
            'is_photo' => true,
        ]);
    }
}
