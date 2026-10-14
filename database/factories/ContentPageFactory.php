<?php

namespace Database\Factories;

use App\Models\ContentPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentPage>
 */
class ContentPageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'title_ar' => 'صفحة تعريفية',
            'title_en' => 'Information page',
            'body_ar' => 'نص الصفحة.',
            'body_en' => 'Page text.',
            'is_published' => true,
        ];
    }
}
