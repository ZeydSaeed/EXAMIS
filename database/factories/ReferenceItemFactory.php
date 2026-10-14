<?php

namespace Database\Factories;

use App\Enums\ReferenceType;
use App\Models\ReferenceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferenceItem>
 */
class ReferenceItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ReferenceType::Governorate,
            'code' => fake()->unique()->lexify('???'),
            'name_ar' => 'عنصر مرجعي',
            'name_en' => 'Reference item',
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
