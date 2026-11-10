<?php

use App\Enums\ReferenceType;
use App\Services\ReferenceData;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    seedReferenceData();
});

it('reads each list from the cache once per request, however many codes it names', function () {
    $data = app(ReferenceData::class);
    $code = $data->codes(ReferenceType::Governorate)[0];

    Cache::spy();

    foreach (range(1, 50) as $ignored) {
        app(ReferenceData::class)->name(ReferenceType::Governorate, $code);
    }

    Cache::shouldNotHaveReceived('remember');
});

it('reloads a list after it is forgotten', function () {
    $data = app(ReferenceData::class);
    $data->items(ReferenceType::Governorate);

    Cache::spy();
    $data->forget(ReferenceType::Governorate);
    $data->items(ReferenceType::Governorate);

    Cache::shouldHaveReceived('remember')->once();
});
