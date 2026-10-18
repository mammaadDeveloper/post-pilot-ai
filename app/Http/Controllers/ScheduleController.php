<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    /**
     * Show the schedule overview.
     */
    public function index(): Response
    {
        return Inertia::render('schedule/Index');
    }
}
