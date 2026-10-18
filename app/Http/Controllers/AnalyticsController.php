<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    /**
     * Show the analytics overview.
     */
    public function index(): Response
    {
        return Inertia::render('analytics/Index');
    }
}
