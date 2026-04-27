# Feature Spec: Meta (Facebook + Instagram) Account Connection

## Feature Name

`meta-platform-connection`

## Objective

Allow a workspace member to connect a Meta account, discover Facebook Pages and linked Instagram Business accounts, and save selected accounts as Schedly channels.

This feature uses:

- Facebook Graph API `v25.0`
- Instagram Graph API (through Facebook Graph endpoints)

The implementation should stay extensible for future platforms (LinkedIn, X, TikTok, etc.).

---

## Codebase Context (Current State)

This section reflects what already exists in this repository and should guide implementation choices.

### Existing API conventions

- Versioned API routes live under `/api/v1`.
- Protected routes use `auth:sanctum`.
- Channel CRUD currently exists at `/api/v1/channels`.

### Existing channel data model

`channels` already exists and currently stores:

- `workspace_id`
- `platform_id` (FK to `platforms`)
- `platform_account_id`
- `handle`
- `access_token`
- `refresh_token` (nullable)
- `token_expires_at` (nullable)
- `created_by`

So Meta channel import must map discovered accounts to this shape instead of a new `platform`/`metadata` structure.

### Existing social spike (to be ignored - implemented to test the connection flow)

- `SocialController` and `resources/views/social.blade.php` are currently a local spike/prototype.
- They are not wired into `routes/api.php`.
- They currently reference `services.facebook.*` keys, but `config/services.php` does not yet define Facebook credentials.

---

## Scope

### In Scope

- OAuth start and callback flow for Meta
- Facebook Page discovery
- Linked Instagram Business account discovery
- Persisting selected channels into existing `channels` table

### Out of Scope

- Ongoing sync / background refresh
- Revoke detection / token health checks
- Scheduling or publishing logic
- Frontend implementation details

---

## Architecture

Use a driver-based integration layer so platform integrations are pluggable.

Suggested structure:

```text
app/
  Domain/
    Content/
      Services/
        SocialPlatforms/
          Contracts/
            SocialPlatformDriver.php
          SocialPlatformManager.php
          Drivers/
            MetaDriver.php
```

### Driver contract (minimum behavior)

- `buildAuthorizationUrl(...)`
- `handleCallback(...)`
- `discoverChannels(...)`

### Manager responsibility

Resolve a driver by platform slug.

Example:

```php
$driver = $socialPlatformManager->driver('meta');
$channels = $driver->discoverChannels($connection);
```

---

## Data Model Changes

### 1) `platform_auth_connections` (new)

Represents an OAuth connection per workspace + platform account.

Minimum fields:

- `id`
- `uuid`
- `workspace_id` (FK)
- `platform_id` (FK, points to meta platform row)
- `provider_user_id`
- `access_token` (encrypted)
- `expires_at` (nullable)
- `created_by` (FK users)
- `created_at`, `updated_at`

Notes:

- Store long-lived user access token here.
- Do not return raw token values in API responses.

### 2) `platform_auth_connection_states` (new)

Temporary OAuth CSRF state store.

Minimum fields:

- `id`
- `uuid`
- `workspace_id`
- `user_id`
- `platform_id`
- `expires_at`
- `created_at`

Notes:

- State TTL: ~10 minutes.
- Callback must reject expired or missing state.

### 3) `channels` (existing)

No breaking schema change required for this feature.

Persisted rows should map as:

- `platform_id` -> facebook/instagram platform row
- `platform_account_id` -> page ID or Instagram business ID
- `handle` -> page name or ig username/label
- `access_token` -> page token for Facebook channels (and whichever token strategy is chosen for IG)
- `created_by` -> authenticated user

---

## Configuration

All secrets must come from config/environment.

Add to `config/services.php`:

- `services.facebook.app_id`
- `services.facebook.app_secret`
- `services.facebook.redirect_uri`
- `services.facebook.graph_version` (default `v25.0`)

Base URLs:

- OAuth dialog: `https://www.facebook.com/{graph_version}/dialog/oauth`
- Graph API: `https://graph.facebook.com/{graph_version}`

---

## API Contract

Use `/api/v1` namespace to match existing project conventions.

### 1) Start OAuth

`GET /api/v1/social/meta/connect`

Behavior:

1. Validate current user/workspace context.
2. Create OAuth state record.
3. Build Meta OAuth URL.
4. Return redirect (or URL payload, based on API style decision).

Required OAuth query params:

- `client_id`
- `redirect_uri`
- `response_type=code`
- `scope=pages_show_list,pages_manage_posts,instagram_basic,instagram_content_publish`
- `state`

### 2) OAuth Callback

`GET /api/v1/social/meta/callback`

Query params:

- `code`
- `state`

Backend flow:

1. Validate `state` and expiry.
2. Exchange code for short-lived token.
3. Exchange short-lived token for long-lived token.
4. Persist/update `platform_auth_connections`.
5. Discover Facebook + Instagram channels.
6. Return normalized channel candidates (without sensitive tokens unless explicitly needed server-side only).

### 3) Persist Selected Channels

`POST /api/v1/social/meta/channels`

Payload example:

```json
{
  "channels": [
    {
      "platform_slug": "facebook",
      "platform_account_id": "1234567890",
      "handle": "My Cafe"
    },
    {
      "platform_slug": "instagram",
      "platform_account_id": "17841400000000000",
      "handle": "@mycafe"
    }
  ]
}
```

Behavior:

- Resolve `platform_id` from `platform_slug`.
- Persist via existing channel creation rules/service shape.
- Return created channels through existing resource format.

---

## Meta API Interactions

### Exchange code for token

`GET https://graph.facebook.com/v25.0/oauth/access_token`

Params:

- `client_id`
- `client_secret`
- `redirect_uri`
- `code`

### Exchange for long-lived token

`GET https://graph.facebook.com/v25.0/oauth/access_token`

Params:

- `grant_type=fb_exchange_token`
- `client_id`
- `client_secret`
- `fb_exchange_token`

### Fetch Facebook pages

`GET https://graph.facebook.com/v25.0/me/accounts?access_token={USER_ACCESS_TOKEN}`

Expected fields per page:

- `id`
- `name`
- `access_token`

### Discover Instagram business account per page

`GET https://graph.facebook.com/v25.0/{PAGE_ID}?fields=instagram_business_account&access_token={PAGE_ACCESS_TOKEN}`

Field:

- `instagram_business_account.id` (nullable)

---

## Normalized Discovery Response

Internal normalized shape (driver output):

```json
[
  {
    "platform_slug": "facebook",
    "platform_account_id": "PAGE_ID",
    "handle": "My Cafe",
    "access_token": "PAGE_ACCESS_TOKEN"
  },
  {
    "platform_slug": "instagram",
    "platform_account_id": "IG_BUSINESS_ID",
    "handle": "@mycafe",
    "access_token": null
  }
]
```

---

## Security Requirements

- Validate OAuth `state` against stored state record.
- Reject expired state records.
- Encrypt stored OAuth tokens.
- Never log or expose secrets/tokens in API responses.
- Enforce workspace ownership checks before channel creation.

---

## Implementation Order

1. Add config entries and env wiring for Facebook.
2. Add OAuth state + connection migrations/models.
3. Build social driver contract + manager.
4. Implement `MetaDriver` (OAuth + discovery).
5. Add API routes under `/api/v1/social/meta/*`.
6. Implement connect + callback controllers/services.
7. Implement selected channel persistence endpoint.
8. Add tests for auth, state validation, and channel persistence.