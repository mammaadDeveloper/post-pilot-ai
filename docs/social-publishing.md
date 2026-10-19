# Scheduled social publishing

Scheduled posts are stored in the application database. Laravel's scheduler
finds due destinations and dispatches queue jobs; each job publishes to one
connected social account and records its result. Existing browser-stored drafts
are not migrated or removed.

## Provider setup

Create and configure an application with each provider you plan to use. The
provider must approve the required products and permissions for your account.
Register the exact callback URLs generated from the production `APP_URL`:

```text
{APP_URL}/social-accounts/linkedin/callback
{APP_URL}/social-accounts/instagram/callback
{APP_URL}/social-accounts/x/callback
```

Set provider credentials in the server environment or deployment secret store.
The matching settings are listed in `.env.example`:

- LinkedIn: `LINKEDIN_CLIENT_ID`, `LINKEDIN_CLIENT_SECRET`, and a currently
  supported `LINKEDIN_API_VERSION`. This release supports member accounts, not
  organization publishing.
- Instagram: `INSTAGRAM_APP_ID`, `INSTAGRAM_APP_SECRET`, and a currently
  supported `INSTAGRAM_GRAPH_API_VERSION`. The Instagram professional account
  and app must have Content Publishing access. Image publishing requires JPEG.
- X: `X_CLIENT_ID` and, for a confidential OAuth client, `X_CLIENT_SECRET`.
  Authorization uses OAuth 2.0 with PKCE.

Keep these credentials, social-account tokens, and `APP_KEY` out of source
control and browser code. Social access and refresh tokens are encrypted with
Laravel's application key; keep the same key available to web and queue
processes. LinkedIn refresh-token support is not assumed, so an expired
LinkedIn account may need to be connected again.

## Production runtime

Apply the application's migrations through the normal deployment process
before enabling the publishing interface:

```sh
php artisan migrate --force
```

Use a persistent asynchronous queue connection (`database` is the default;
Redis is also supported) and run a worker for the `default` queue:

```sh
php artisan queue:work --queue=default --tries=5 --timeout=90
```

Run Laravel's scheduler every minute:

```cron
* * * * * cd /path/to/application && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler invokes `publications:dispatch-due`. The worker timeout is 90
seconds; keep the queue connection's retry-after or visibility timeout above
that value. The database, Beanstalkd, and Redis retry-after defaults are 150
seconds. Configure SQS visibility timeout externally.

Set `APP_URL` to the public HTTPS origin. Instagram fetches image media from a
short-lived signed application URL, so that URL must be reachable over HTTPS
from Meta. The configured media disk must be accessible to both web and queue
processes; use shared storage when they run on separate hosts. Deploy the same
`APP_KEY` to both so signed URLs can be verified.

## Operational behavior

Each destination publishes independently and its status is shown in the
schedule. Transient failures are retried with a bounded attempt count;
authorization failures require reconnecting the account. If a provider's
response leaves the publication outcome uncertain, the destination is marked
`outcome_unknown` to avoid a blind duplicate. Confirm the post on the provider
before manually retrying it.

The feature tests use faked HTTP responses and do not require provider
credentials or make live publication requests:

```sh
php artisan test --compact tests/Feature/SocialPublishingWorkflowTest.php tests/Feature/SocialPublisherProviderTest.php
```
