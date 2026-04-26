# Feature
Facebook Platform OAuth Connection

# Goal

Allow a workspace to connect a Facebook account and import Facebook Pages as publishing channels.

This feature should replicate the behavior seen in Buffer where:

- Facebook and Instagram connections are handled separately
- Facebook connection only handles Facebook Pages
- Instagram accounts will be handled via a separate OAuth flow later

Integration uses Facebook Graph API v25.0.

---

# Architecture

This project uses Laravel.

Platform integrations must follow a driver architecture so additional providers can be implemented easily.

Directory structure

app/Services/SocialPlatforms
    SocialPlatformDriver.php
    SocialPlatformManager.php
    Drivers
        FacebookDriver.php
        InstagramDriver.php (future)

Note: These classes already exists (rename MetaDriver to Facebook Driver), please move them to directory specified above.

---

# Driver Interface

SocialPlatformDriver

Required methods

connect
handleCallback
fetchChannels

---

# Driver Resolution

SocialPlatformManager should resolve drivers like

$driver = SocialPlatformManager::driver('facebook');

---

# Database Tables

## platform_oauth_connections

Represents an OAuth authorization between a workspace and a platform.

Columns

id
workspace_id
provider
provider_user_id
access_token
expires_at
created_at
updated_at

provider value

facebook

Notes:
- Tokens must be encrypted using Laravel encryption.
- Rename platform_auth_connections table

---

## channels

Existing table used for publishing destinations.

Fields used

id
workspace_id
platform
platform_channel_id
name
metadata
created_at
updated_at

Platform value for this feature

facebook

Example row

platform
facebook

platform_channel_id
PAGE_ID

---

## platform_oauth_connection_states

Temporary table used for OAuth CSRF protection.

Columns

id (uuid)
workspace_id
user_id
provider
expires_at
created_at

Notes:
- State should expire after approximately 10 minutes.
- Rename platform_oauth_connection_states table

---

# Configuration (implemented already)

Values must be loaded from config.

services.facebook.app_id
services.facebook.app_secret
services.facebook.redirect_uri
services.facebook.graph_version

Graph API version

v25.0

Base API URL

https://graph.facebook.com/v25.0

OAuth URL

https://www.facebook.com/v25.0/dialog/oauth

---

# API Endpoints

## Start Facebook Connection (implemented already, rename if needed)

GET /api/social/facebook/connect

Behavior

1 Generate OAuth state UUID
2 Store in platform_oauth_connection_states
3 Redirect to Facebook OAuth

OAuth parameters

client_id from config
redirect_uri from config
response_type code
scope pages_show_list pages_read_engagement pages_manage_posts pages_manage_metadata business_management
state generated state id

---

## OAuth Callback (implemented already, rename if needed)

GET /api/social/facebook/callback

Query parameters

code
state

Steps

1 Validate state exists and not expired
2 Retrieve workspace_id and user_id
3 Exchange code for user access token
4 Convert short lived token to long lived token
5 Fetch Facebook user id
6 Store connection in platform_oauth_connections
7 Fetch pages
8 Return discovered channels

---

# Token Exchange (implemented already)

Endpoint

GET https://graph.facebook.com/v25.0/oauth/access_token

Parameters

client_id
client_secret
redirect_uri
code

---

# Convert to Long Lived Token (implemented already)

Endpoint

GET https://graph.facebook.com/v25.0/oauth/access_token

Parameters

grant_type fb_exchange_token
client_id
client_secret
fb_exchange_token

---

# Fetch Facebook Pages

Pages must be discovered from two sources.

Step 1 Direct pages  (implemented already, adjust if needed)

GET /me/accounts

Example

https://graph.facebook.com/v25.0/me/accounts

Parameters

access_token user_token

Fields returned

id
name
access_token

---

Step 2 Business pages

GET /me/businesses

For each business

GET /{BUSINESS_ID}/owned_pages

GET /{BUSINESS_ID}/client_pages

Merge all page results and deduplicate by PAGE_ID.

---

# Channel Normalization

Driver must return unified structure.

Example

[
  {
    "platform": "facebook",
    "platform_channel_id": "PAGE_ID",
    "name": "My Cafe",
    "access_token": "PAGE_ACCESS_TOKEN"
  }
]

---

# Store Channels

POST /api/social/facebook/channels (implemented already, rename if needed)

Payload

{
  "channels": [
    {
      "platform_channel_id": "PAGE_ID"
    }
  ]
}

Insert rows into channels table.

Platform value must be facebook.

---

# Security Requirements

Validate OAuth state

Reject expired state

Encrypt tokens

Do not return tokens in API responses

---

# Implementation Order

Cursor should implement in this order

1 Database migration for platform_oauth_connection_states (implemented already, adjust if needed)
2 SocialPlatformDriver interface
3 SocialPlatformManager
4 FacebookDriver
5 OAuth connect endpoint
6 OAuth callback handler
7 Page discovery logic
8 Channel storage endpoint

---

# Out of Scope

Instagram OAuth
Channel syncing
Scheduler logic
Frontend implementation