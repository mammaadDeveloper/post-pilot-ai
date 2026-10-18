# AI content assistant

The post composer uses the official Laravel AI SDK. Provider calls run on the
server; the selected provider credential is never sent to Inertia or the
browser.

## Setup

The SDK is installed with Composer and its configuration is published at
`config/ai.php`. The application brand profile uses the
`create_brand_profiles_table` migration. Apply the application's migrations:

```sh
php artisan migrate
```

The SDK conversation migration is intentionally not published or required:
generation requests are stateless and do not store conversation history.
OpenAI, Azure OpenAI, and OpenAI-compatible provider configuration also
disables response storage where the SDK exposes that option. Other providers
may have separate request-retention policies; review the selected provider's
terms before sending user content.

## Provider configuration

Set the selected provider, a text model supported by that provider, and its
server-side credential in the deployment environment. For OpenAI:

```dotenv
AI_PROVIDER=openai
AI_MODEL=gpt-4o-mini
OPENAI_API_KEY=your-server-side-key
AI_TIMEOUT=45
```

`AI_PROVIDER` defaults to `openai`; `AI_MODEL` and the provider credential are
required to enable generation. `AI_TIMEOUT` defaults to 45 seconds and must be
between 1 and 120 seconds. Keep credentials in deployment secrets or an
untracked `.env` file, never in source control, frontend variables, or browser
storage. For another provider, set `AI_PROVIDER` to a provider configured in
`config/ai.php` and provide the corresponding SDK environment variables listed
there. OpenAI-compatible endpoints also require `OPENAI_COMPATIBLE_URL`.

Input length, generated output length, number of variants, and per-user request
rate can be adjusted with `AI_MAX_INPUT_CHARS`, `AI_MAX_OUTPUT_CHARS`,
`AI_MAX_VARIANTS` (maximum 3), and `AI_REQUESTS_PER_MINUTE`. Structured output
must be supported by the chosen provider and model; consult the
[Laravel AI SDK provider support documentation](https://laravel.com/docs/13.x/ai-sdk#provider-support)
before switching.

Laravel configuration caching is supported because environment variables are
read only from configuration files.

## Tests

Run the focused feature tests without provider credentials or external API
calls:

```sh
php artisan test --compact tests/Feature/PostContentTest.php tests/Feature/BrandProfileTest.php
```

Tests use the Laravel AI SDK agent fake.
