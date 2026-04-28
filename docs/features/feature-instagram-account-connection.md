# Feature
Instagram OAuth Connection (Instagram Business Login)

## Goal
Allow users to connect an Instagram account using the **Instagram Business Login flow** and import the account as a channel.

Facebook OAuth is already implemented.  
Instagram should follow the **same architecture and patterns** used in the Facebook implementation.

Reuse:

- SocialPlatformDriver interface
- SocialPlatformManager
- platform_oauth_connections table
- platform_oauth_connection_states table
- channels table
- same service / controller structure

Only the OAuth endpoints and API calls differ.

---

## Driver

Create / implement InstagramDriver
Implement the same driver methods already used for Facebook

---

## Config

Read credentials from config (same pattern as Facebook)
services.instagram.app_id
services.instagram.app_secret
services.instagram.redirect_uri

## Route

Route structure similar to facebook implementation.

## Callback

Steps

1. validate OAuth state
2. read authorization code
3. exchange code for access token
4. fetch Instagram user profile
5. store OAuth connection
6. return discovered channel

Follow the same structure used in the Facebook callback handler.

---

### Token Exchange

Endpoint POST https://api.instagram.com/oauth/access_token

Body

client_id
client_secret
grant_type=authorization_code
redirect_uri
code

Response

access_token
user_id

---

### Fetch Instagram Account

Use the token to fetch account info.

Endpoint GET https://graph.instagram.com/me

Fields

id
username
account_type

Example GET https://graph.instagram.com/me?fields=id,username,account_type&access_token=TOKEN

---

### Channel Creation

Return normalized channel object like Facebook driver.

Insert into channels table using the same logic used in the Facebook implementation.

---

# Notes

Follow the **same patterns already used for Facebook OAuth**:

- same service structure
- same state validation
- same OAuth connection storage
- same channel creation flow

Only the OAuth URL and API endpoints differ.