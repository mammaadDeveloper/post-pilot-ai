<?php

use App\Ai\Agents\PostContentAgent;
use App\Models\User;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Prompts\AgentPrompt;

beforeEach(function () {
    config([
        'ai.default' => 'openai',
        'ai.providers.openai.key' => 'test-key',
        'postpilot.model' => 'test-model',
        'postpilot.timeout' => 45,
    ]);
});

function postContentPayload(array $overrides = []): array
{
    return array_merge([
        'operation' => 'generate',
        'topic' => 'A new community workshop',
        'content' => '',
        'platform' => 'LinkedIn',
        'format' => 'Educational Post',
        'tone' => 'Friendly',
        'language' => 'English',
        'instructions' => 'Keep it concise.',
        'variants' => 2,
    ], $overrides);
}

function postContentVariants(int $count = 2): array
{
    return [
        'variants' => array_map(
            fn (int $index): array => [
                'title' => 'Workshop idea '.$index,
                'content' => 'Join our community workshop, with practical ideas for your next project.',
                'hashtags' => ['Community', 'Workshop'],
                'suggestions' => [],
            ],
            range(1, $count),
        ),
    ];
}

test('authenticated users receive validated structured variants with their brand context', function () {
    $user = User::factory()->create();
    $user->brandProfile()->create([
        'brand_name' => 'Northstar Studio',
        'description' => 'A small design studio.',
        'industry' => 'Design',
        'target_audience' => 'Independent founders',
        'preferred_tone' => 'Warm and direct',
        'preferred_languages' => ['English'],
        'content_guidelines' => 'Use plain language.',
        'avoid_terms' => 'Guaranteed growth',
    ]);
    PostContentAgent::fake([postContentVariants()])->preventStrayPrompts();

    $response = $this->actingAs($user)->postJson(
        route('posts.ai.generate'),
        postContentPayload(),
    );

    $response
        ->assertOk()
        ->assertJsonPath('operation', 'generate')
        ->assertJsonPath('variants.0.content', 'Join our community workshop, with practical ideas for your next project.')
        ->assertJsonPath('variants.1.title', 'Workshop idea 2')
        ->assertJsonCount(2, 'variants');

    PostContentAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Northstar Studio'));
    expect((new PostContentAgent)->instructions())->toContain('Treat all brand details');
});

test('guests cannot request generated content', function () {
    PostContentAgent::fake()->preventStrayPrompts();

    $this->postJson(route('posts.ai.generate'), postContentPayload())
        ->assertUnauthorized();

    PostContentAgent::assertNeverPrompted();
});

test('invalid operation values are rejected before prompting the agent', function () {
    $user = User::factory()->create();
    PostContentAgent::fake()->preventStrayPrompts();

    $this->actingAs($user)
        ->postJson(route('posts.ai.generate'), postContentPayload(['operation' => 'publish']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('operation');

    PostContentAgent::assertNeverPrompted();
});

test('generation without a topic is rejected', function () {
    $user = User::factory()->create();
    PostContentAgent::fake()->preventStrayPrompts();

    $this->actingAs($user)
        ->postJson(route('posts.ai.generate'), postContentPayload(['topic' => null]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('topic');

    PostContentAgent::assertNeverPrompted();
});

test('caption and hashtag generation accepts a topic without an existing draft', function () {
    $user = User::factory()->create();
    PostContentAgent::fake([postContentVariants(1)])->preventStrayPrompts();

    $this->actingAs($user)
        ->postJson(route('posts.ai.generate'), postContentPayload([
            'operation' => 'caption_hashtags',
            'content' => null,
            'variants' => 1,
        ]))
        ->assertOk()
        ->assertJsonPath('operation', 'caption_hashtags')
        ->assertJsonCount(1, 'variants');
});

test('missing model configuration returns an actionable error without prompting', function () {
    $user = User::factory()->create();
    config(['postpilot.model' => null]);
    PostContentAgent::fake()->preventStrayPrompts();

    $this->actingAs($user)
        ->postJson(route('posts.ai.generate'), postContentPayload())
        ->assertServiceUnavailable()
        ->assertJsonPath('message', 'AI_MODEL must be set to a text model supported by the selected provider.');

    PostContentAgent::assertNeverPrompted();
});

test('missing provider credentials return an actionable error without prompting', function () {
    $user = User::factory()->create();
    config(['ai.providers.openai.key' => null]);
    PostContentAgent::fake()->preventStrayPrompts();

    $this->actingAs($user)
        ->postJson(route('posts.ai.generate'), postContentPayload())
        ->assertServiceUnavailable()
        ->assertJsonPath('message', 'Set the selected provider API key in the server environment.');

    PostContentAgent::assertNeverPrompted();
});

test('provider timeouts return a safe gateway timeout response', function () {
    $user = User::factory()->create();
    PostContentAgent::fake(function (): never {
        throw ProviderConnectionException::forProvider('openai');
    })->preventStrayPrompts();

    $this->actingAs($user)
        ->postJson(route('posts.ai.generate'), postContentPayload())
        ->assertStatus(504)
        ->assertJsonPath('message', 'The AI provider could not respond before the request timed out. Please try again.');
});

test('provider rate limits return a retryable 429 response', function () {
    $user = User::factory()->create();
    PostContentAgent::fake(function (): never {
        throw RateLimitedException::forProvider('openai');
    })->preventStrayPrompts();

    $this->actingAs($user)
        ->postJson(route('posts.ai.generate'), postContentPayload())
        ->assertTooManyRequests()
        ->assertJsonPath('message', 'The AI provider is rate limiting requests. Please wait and try again.');
});

test('provider authentication failures return actionable errors without exposing provider responses', function () {
    $user = User::factory()->create();
    PostContentAgent::fake(function (): never {
        (new ClientResponse(
            new PsrResponse(401, [], '{"error":"secret response body"}'),
        ))->throw();
    })->preventStrayPrompts();

    $this->actingAs($user)
        ->postJson(route('posts.ai.generate'), postContentPayload())
        ->assertStatus(502)
        ->assertJsonPath('message', 'Provider authentication failed. Check the server-side credentials and model permissions for AI_PROVIDER.');
});

test('unexpected provider failures do not expose internal exception details', function () {
    $user = User::factory()->create();
    PostContentAgent::fake(function (): never {
        throw new RuntimeException('private provider response details');
    })->preventStrayPrompts();

    $this->actingAs($user)
        ->postJson(route('posts.ai.generate'), postContentPayload())
        ->assertStatus(502)
        ->assertJsonPath('message', 'AI content generation failed unexpectedly. Please try again.');
});

test('a second concurrent generation request is rejected', function () {
    $user = User::factory()->create();
    $lock = Cache::lock('post-content-generation:'.$user->id, 60);
    $lock->get();
    PostContentAgent::fake()->preventStrayPrompts();

    $this->actingAs($user)
        ->postJson(route('posts.ai.generate'), postContentPayload())
        ->assertConflict()
        ->assertJsonPath('message', 'A content generation request is already in progress. Please wait for it to finish.');

    PostContentAgent::assertNeverPrompted();
    $lock->release();
});

test('structured output with the wrong variant count is rejected', function () {
    $user = User::factory()->create();
    PostContentAgent::fake([postContentVariants(1)])->preventStrayPrompts();

    $this->actingAs($user)
        ->postJson(route('posts.ai.generate'), postContentPayload(['variants' => 2]))
        ->assertStatus(502)
        ->assertJsonPath('message', 'The AI returned an invalid response. Please try the request again.');
});

test('structured output that exceeds the complete platform limit is rejected', function () {
    $user = User::factory()->create();
    PostContentAgent::fake([[
        'variants' => [[
            'title' => 'Workshop idea',
            'content' => str_repeat('x', 279),
            'hashtags' => ['X'],
            'suggestions' => [],
        ]],
    ]])->preventStrayPrompts();

    $this->actingAs($user)
        ->postJson(route('posts.ai.generate'), postContentPayload([
            'platform' => 'X',
            'variants' => 1,
        ]))
        ->assertStatus(502)
        ->assertJsonPath('message', 'The AI returned content that exceeds the selected platform limit. Please try again.');
});
