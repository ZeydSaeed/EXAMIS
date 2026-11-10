<?php

use App\Enums\Portal;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Two portals, two hosts (ADR-0011)
|--------------------------------------------------------------------------
|
| A route answers on its own portal's host and is a 404 on the other. Only
| the health checks answer everywhere (bootstrap/app.php, outside the web
| group).
|
*/

Route::domain(Portal::Students->routeDomain())->group(base_path('routes/students.php'));

Route::domain(Portal::Staff->routeDomain())->group(base_path('routes/staff.php'));
