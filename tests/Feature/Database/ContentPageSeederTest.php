<?php

use App\Enums\Role;
use App\Models\ContentPage;
use Database\Seeders\ContentPageSeeder;

it('refreshes pages no administrator has edited and keeps edited ones', function () {
    $untouched = ContentPage::factory()->create(['slug' => 'eligibility', 'title_ar' => 'الفئات المؤهلة']);
    $edited = ContentPage::factory()->create(['slug' => 'documents', 'title_ar' => 'نص المدير']);
    $edited->forceFill(['updated_by' => staffWith(Role::Administrator)->id])->save();

    $this->seed(ContentPageSeeder::class);

    expect($untouched->fresh()->title_ar)->toBe('شروط التقديم')
        ->and($edited->fresh()->title_ar)->toBe('نص المدير')
        ->and(ContentPage::query()->where('slug', 'instructions')->exists())->toBeTrue();
});
