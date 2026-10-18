<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBrandProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BrandProfileController extends Controller
{
    /**
     * Show the authenticated user's brand profile settings.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Brand', [
            'brandProfile' => $request->user()->brandProfile?->only([
                'brand_name',
                'description',
                'industry',
                'target_audience',
                'preferred_tone',
                'preferred_languages',
                'content_guidelines',
                'avoid_terms',
            ]),
        ]);
    }

    /**
     * Create or update the authenticated user's single brand profile.
     */
    public function update(UpdateBrandProfileRequest $request): RedirectResponse
    {
        $request->user()->brandProfile()->updateOrCreate([], $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Brand profile saved.')]);

        return to_route('brand.edit');
    }
}
