# One Aflanex account: Erudify ↔ Community sign-in setup

**Goal:** a learner who has an Erudify account can sign in to Aflanex Community with that same account. Their Community profile is created automatically on first sign-in. There's no second password.

**How it works:** Erudify runs on Lovable, which uses Supabase Auth. Supabase Auth can act as an **OAuth 2.1 / OpenID Connect identity provider** (currently in beta, free on all plans). Erudify becomes the provider; the Community (already built) is the client.

```
Member clicks "Sign in" on the Community
   → community /sso/start/
   → Erudify (Supabase) /auth/v1/oauth/authorize
   → Erudify /oauth/consent page (member signs in to Erudify if needed; approves once, or auto-approved)
   → community /sso/callback/  (code exchange + ID-token verification)
   → member is signed in; account created/linked by Erudify user ID
```

The Community side is **done and inactive**. Everything below happens in Erudify and in `wp-config.php`.

---

## Step 0: Check which Supabase backend Erudify uses

In the Lovable project for Erudify, check whether it uses **Lovable Cloud** (built-in backend) or a **connected Supabase project** (you can open it at supabase.com/dashboard).

- **Connected Supabase project:** follow steps 1–5 as written.
- **Lovable Cloud:** check whether its backend settings expose *Authentication → OAuth Server* and *JWT signing keys*. If they don't, see "If Lovable Cloud doesn't expose these settings" at the end.

## Step 1: Switch Supabase to asymmetric JWT signing keys

Supabase dashboard → **Project Settings → JWT Keys** → migrate to asymmetric keys (ES256 or RS256) and rotate them in.
OpenID Connect ID tokens **require** asymmetric signing; the Community verifies them against Supabase's public JWKS and refuses HS256.

> Existing Erudify sessions keep working through the standard rotation flow. Do it at a quiet time and follow Supabase's rotation guidance.

## Step 2: Enable the OAuth 2.1 server

Dashboard → **Authentication → OAuth Server** → Enable.
Set the **authorization path** to `/oauth/consent` (the Erudify page you'll add in Step 4).

## Step 3: Register Aflanex Community as a client

Dashboard → **Authentication → OAuth Apps → Add a new client**

| Field | Value |
|---|---|
| Name | Aflanex Community |
| Type | **Confidential** (server-side app) |
| Redirect URI | `https://community.afriflare.com/sso/callback/` (exact match, including the trailing slash) |

Copy the **Client ID** and **Client Secret**. Keep the secret out of chat, email and Lovable prompts.

## Step 4: Add the consent page to Erudify

Paste this prompt into the Erudify Lovable project:

> Add a route `/oauth/consent` for Supabase's OAuth 2.1 server.
> 1. Read `authorization_id` from the query string.
> 2. If the user is not signed in, send them to our existing sign-in page and return them to this exact URL (including `authorization_id`) afterwards. Sign-up should also return here.
> 3. Call `supabase.auth.oauth.getAuthorizationDetails(authorization_id)` to get the client name and requested scopes.
> 4. If the client is our own first-party app **"Aflanex Community"** (client ID stored in the env var `VITE_AFLANEX_COMMUNITY_CLIENT_ID`), approve immediately with `supabase.auth.oauth.approveAuthorization(authorization_id)` and follow the returned redirect. No screen is needed for our own app.
> 5. For any other client, show a simple, branded consent screen ("*[client]* wants to use your Erudify account") with Approve / Cancel buttons, calling `approveAuthorization` or `denyAuthorization` and following the returned redirect.
> 6. Show a friendly error if the authorization is missing or expired.
> Don't change any existing auth flows.

Then set `VITE_AFLANEX_COMMUNITY_CLIENT_ID` to the Client ID from Step 3.

## Step 5: Turn it on in the Community

Add to `wp-config.php` above `/* That's all, stop editing! */` (via your host's file manager):

```php
define( 'AFLANEX_SSO_ISSUER', 'https://<project-ref>.supabase.co/auth/v1' );
define( 'AFLANEX_SSO_CLIENT_ID', '<client id>' );
define( 'AFLANEX_SSO_CLIENT_SECRET', '<client secret>' );
define( 'AFLANEX_SSO_SIGNUP_URL', 'https://<erudify-domain>/<signup-path>' ); // optional: where "Join" goes
define( 'AFLANEX_ERUDIFY_URL', 'https://<erudify-domain>' );                  // optional: footer link
```

The issuer must match Supabase's discovery document:
`https://<project-ref>.supabase.co/auth/v1/.well-known/openid-configuration` → `"issuer"`.

## Step 6: Test

1. In a private window, open the Community entry page. Join/Sign in should now point to Erudify.
2. Sign in with an Erudify learner account. You should land in the Community feed, with a new member created (WordPress role *Subscriber*).
3. Sign out and sign in again. The same account should be reused (matched by Erudify user ID).
4. If something fails, the sign-in page shows a plain-language message. Admins can enable `WP_DEBUG_LOG` to see `[aflanex-sso]` entries.

**Account-matching rules:**
- An existing Erudify link is matched by the Erudify user ID (`sub`).
- An existing Community account with the **same verified email** is linked automatically, except admin/editor accounts, which are never auto-linked for safety.
- Otherwise a new Community account is created. Erudify must share the email address.

---

## If Lovable Cloud doesn't expose these settings

Pick one:

1. **Move Erudify's auth to a Supabase project you control** (Lovable supports connecting your own Supabase). This is the cleanest long-term base for an "Aflanex ID" across Erudify, the Community and future products.
2. **Signed hand-off bridge:** an Erudify Edge Function mints a 60-second signed token for the signed-in user, and the Community verifies it. This needs a small additional Community-side driver (not built yet, since the standards-based route is preferred). Ask for it if needed.

## Moving domains later

If the Community moves (e.g. to `community.aflanex.com`), add the new redirect URI `https://community.aflanex.com/sso/callback/` to the OAuth client **before** switching. No code changes are needed.
