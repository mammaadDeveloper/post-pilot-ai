<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    /**
     * Show the create post studio.
     */
    public function create(): Response
    {
        return Inertia::render('posts/Create');
    }

    /**
     * Show the saved drafts list.
     */
    public function drafts(): Response
    {
        return Inertia::render('posts/Drafts');
    }
}
