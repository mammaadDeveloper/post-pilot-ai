<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the full profile page.
     */
    public function show(Request $request): Response
    {
        return Inertia::render('profile/Show', [
            'user' => $request->user(),
        ]);
    }
}
