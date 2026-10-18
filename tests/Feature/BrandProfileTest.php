<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function validBrandProfilePayload(array $overrides = []): array
{
    return array_merge([
        'brand_name' => 'Northstar Studio',
        'description' => 'A small design studio.',
        'industry' => 'Design',
        'target_audience' => 'Independent founders',
        'preferred_tone' => 'Warm and direct',
        'preferred_languages' => ['English', 'Persian'],
        'content_guidelines' => 'Use plain language.',
        'avoid_terms' => 'Guaranteed growth',
    ], $overrides);
}

test('authenticated users can edit only their own brand profile', function () {
    $owner = User::factory()->create();
    $owner->brandProfile()->create(validBrandProfilePayload());
    $anotherUser = User::factory()->create();

    $this->actingAs($anotherUser)
        ->get(route('brand.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Brand')
            ->where('brandProfile', null));
});

test('guests cannot view or update brand profiles', function () {
    $this->get(route('brand.edit'))->assertRedirect(route('login'));
    $this->put(route('brand.update'), validBrandProfilePayload())->assertRedirect(route('login'));
});

test('users can create and update one brand profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('brand.update'), validBrandProfilePayload())
        ->assertRedirect(route('brand.edit'));

    $this->assertDatabaseHas('brand_profiles', [
        'user_id' => $user->id,
        'brand_name' => 'Northstar Studio',
        'preferred_languages' => '["English","Persian"]',
    ]);

    $this->actingAs($user)
        ->put(route('brand.update'), validBrandProfilePayload(['brand_name' => 'Northstar Design']))
        ->assertRedirect(route('brand.edit'));

    $this->assertDatabaseCount('brand_profiles', 1);
    expect($user->brandProfile()->firstOrFail()->brand_name)->toBe('Northstar Design');
});

test('brand profile fields are validated before saving', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('brand.edit'))
        ->put(route('brand.update'), validBrandProfilePayload([
            'brand_name' => '',
            'preferred_languages' => ['Klingon'],
        ]))
        ->assertRedirect(route('brand.edit'))
        ->assertSessionHasErrors(['brand_name', 'preferred_languages.0']);

    $this->assertDatabaseCount('brand_profiles', 0);
});
