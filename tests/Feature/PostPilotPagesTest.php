<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated users can visit each PostPilot workspace page', function () {
    $this->actingAs(User::factory()->create());

    $pages = [
        'posts.create' => 'posts/Create',
        'posts.drafts' => 'posts/Drafts',
        'schedule.index' => 'schedule/Index',
        'analytics.index' => 'analytics/Index',
        'profile.show' => 'profile/Show',
    ];

    foreach ($pages as $route => $component) {
        $this->get(route($route))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component));
    }
});

test('guests cannot visit PostPilot workspace pages', function () {
    foreach (['posts.create', 'posts.drafts', 'schedule.index', 'analytics.index', 'profile.show'] as $route) {
        $this->get(route($route))->assertRedirect(route('login'));
    }
});
