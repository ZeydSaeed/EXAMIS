<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The standalone entry page shown when the portal is opened: ministry,
 * application and vocational-education logos, the service title and the
 * ways in. The detailed portal home lives at /portal.
 */
class StartController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('start', [
            'canRegister' => Route::has('register'),
        ]);
    }
}
