<?php

use App\Enums\ReferenceType;
use App\Models\ReferenceItem;
use App\Services\ReferenceData;
use Database\Seeders\ReferenceDataSeeder;

it('offers the published branches with their specializations and hides retired codes', function () {
    ReferenceItem::factory()->create(['type' => ReferenceType::Branch, 'code' => 'SCI', 'is_active' => true]);

    $this->seed(ReferenceDataSeeder::class);

    $referenceData = app(ReferenceData::class);
    $specializations = collect($referenceData->items(ReferenceType::Category));

    expect($referenceData->codes(ReferenceType::Branch))->toBe(['COM', 'APA', 'AGR', 'CIT', 'IND'])
        ->and($specializations->where('parent_code', 'IND')->pluck('code')->all())
        ->toBe(['IND_ELC', 'IND_ECT', 'IND_MEC', 'IND_CON', 'IND_PET', 'IND_HVAC'])
        ->and($specializations->whereNull('parent_code'))->toBeEmpty()
        ->and(ReferenceItem::query()->where('code', 'SCI')->sole()->is_active)->toBeFalse();
});
