# Aflanex Community: Technical Architecture

_Last updated: 2026-09-26 · Site: community.afriflare.com (domain-agnostic; nothing hard-codes it)_

Aflanex Community is the **community layer** of the Aflanex ecosystem: people, conversation, projects, collaboration and evidence of capability. **Erudify is the LMS** and owns courses, lessons, progress, assessments and certificates. Nothing in this codebase duplicates LMS functionality.

---

## 1. Platform (verified 2026-09-26)

| Component | Version / state |
|---|---|
| WordPress | 7.1.2 · PHP 8.1.34 · MariaDB 10.11 · single site |
| Parent theme | Hello Elementor 3.5.1 (unchanged) |
| **Child theme** | `aflanex-community` (this repo): presentation |
| **Plugin** | `aflanex-community` (this repo): logic |
| FluentCommunity | 2.11.0 **free** core (no Pro installed) |
| Also active | FluentCRM, Fluent Forms, FluentSMTP (**not configured**), Fluent Support, FluentCart, FluentNotify, Elementor, Imunify |
| Access for automation | Novamira MCP (PHP / WP-CLI / files) as user 1. Never modify or deactivate Novamira. |

No FluentCommunity, WordPress core or third-party files were edited. All integration uses documented hooks/filters.

## 2. Code layout

```
wp-content/
├─ themes/aflanex-community/          PRESENTATION
│  ├─ assets/css/tokens.css           ← single source of design tokens (colours, type, spacing…)
│  ├─ assets/css/components.css       buttons, cards, forms, chips, empty states, screens
│  ├─ assets/css/portal.css           maps FluentCommunity CSS vars → tokens; frame normalisation
│  ├─ assets/css/landing.css          public entry page
│  ├─ assets/brand/                   ← drop final logo here (see §8)
│  ├─ template-parts/brand-mark.php   ← THE logo component
│  ├─ template-parts/single.php        renders Aflanex screens inside FluentCommunity's Frame
│  ├─ front-page.php                  public entry page (guests only)
│  └─ functions.php                   enqueues, portal <head> injection, header brand
└─ plugins/aflanex-community/         LOGIC
   ├─ src/Plugin.php                  composition root
   ├─ src/Brand/Brand.php             brand data + logo-file detection
   ├─ src/Pages/Pages.php             routes, access gate, frame wiring, URL helpers
   ├─ src/Portal/Portal.php           menus, mobile nav, profile tab, header logo, next steps
   ├─ src/Portal/Copy.php             Aflanex voice for FluentCommunity strings (gettext)
   ├─ src/Projects/*                  post type, read model, forms, FluentCommunity bridge
   ├─ src/Profiles/MemberProfile.php  portfolio fields, directory, onboarding signals
   ├─ src/Integrations/*              external identity map + learning-data contract
   ├─ src/Identity/{Sso,OidcClient}   "Continue with Erudify" (OIDC), inert until configured
   ├─ src/Security/Hardening.php      member-privacy + wp-admin boundaries
   ├─ src/Setup/Installer.php         idempotent setup (pages, spaces, focus areas, banner)
   └─ templates/*                     screen templates (theme may override: {theme}/aflanex/*.php)
```

## 3. Information architecture & navigation

Primary (header + mobile bottom bar): **Home · Spaces · Projects · People**. Secondary (header right / avatar menu): Notifications · Profile · My portfolio · My projects · Settings.

| Route | Engine | Notes |
|---|---|---|
| `/` | Theme `front-page.php` | Guests only; members are redirected to the portal (admins can preview with `?preview_entry=1`) |
| `/portal/…` | FluentCommunity SPA | Home feed, spaces, profiles, notifications, search, settings |
| `/projects/`, `/projects/new/`, `/project/{slug}/` | Plugin in FC Frame | Directory, create/edit, project page |
| `/people/` | Plugin in FC Frame | Discovery by skills, focus areas, "open to" |
| `/portfolio/{username}/`, `/portfolio/edit/` | Plugin in FC Frame | Member portfolio (talent profile foundation) |
| `/community-guidelines/` | Plugin in FC Frame | Public, editable page |
| `/sso/start/`, `/sso/callback/` | Plugin | Only active when SSO is configured |

"Frame" = FluentCommunity's supported page template that wraps WordPress content in the portal header and sidebar, so members never feel they've left the community. Page IDs live in the `aflx_pages` option, so pages can be renamed or moved in wp-admin (they're labelled "Aflanex Community screen").

Future modules (Opportunities, Events, Mentorship, Talent) slot in the same way: a page key in `Pages`, a template, and one menu entry in `Portal::menu_groups()`. Nothing is exposed until it exists.

## 4. FluentCommunity mapping

| Need | Provided by |
|---|---|
| Feed, posts, comments, reactions, mentions, notifications, bookmarks, search | **FluentCommunity (native)** |
| Spaces & groups, membership, moderators | **Native**. Structure: Get Started (Start Here, Introductions) · Build (Projects & Collaboration, Ask the Community) · Grow (Career & Opportunities) |
| Welcome banner, profile completion prompt, privacy, access level | **Native settings** (configured, admin-editable) |
| Course module | **Disabled**. Erudify owns learning. |
| Projects, portfolio fields, people discovery, onboarding checklist | **Plugin** (not available in FC free) |
| Project discussion, feedback, updates | Plugin creates **FC posts** in the Projects space via `FeedsHelper::createFeed()`, so comments, notifications, moderation and search are all native |

Hooks used: `header_vars`, `menu_groups`, `mobile_menu`, `profile_view_data`, `before_header_logo`, `after_sidebar_wrap`, `before_auth_form_header`, `portal_head`, `headless/head`, `template_slug`, `gettext_fluent-community`.

## 5. Data model & ownership

**Community data (source of truth: this site)**

- `aflx_project` post: title, `post_excerpt` (summary), `post_content` (details), author = owner.
  Meta: `aflx_status` (idea · in_progress · seeking_collaborators · ready_for_feedback · completed), `aflx_problem`, `aflx_approach`, `aflx_looking_for`, `aflx_links[]`, `aflx_feedback_requested`, `aflx_collaborator` (one row per user), `aflx_feed_id`, `aflx_update_feed_ids[]`.
- Taxonomies: `aflx_area` (admin-managed focus areas, shared with member interests), `aflx_skill` (member-created, admin-curated).
- User meta: `aflx_headline`, `aflx_location`, `aflx_learning_now`, `aflx_can_help_with`, `aflx_open_to[]`, `aflx_links{}`, `aflx_directory_visible`, `aflx_skill`/`aflx_area` (one row per term).
- FluentCommunity keeps name, avatar, bio, username, posts, comments, spaces.

**Reserved for future synchronisation (empty until a real integration writes them)**

- Projects: `aflx_data_source` (`community` | `erudify`), `aflx_external_project_id`, `aflx_external_course_id`, `aflx_sync_status`, `aflx_last_synced_at`.
- Users: `aflx_ext_{source}` (external subject ID) + `aflx_ext_{source}_data` (issuer, linked_at, last_login_at, sync_status, last_synced_at). WordPress user IDs are never shared with other systems.

**Learning data** lives only in Erudify. Portfolios show a "Learning" section only if a connected `LearningSummaryProvider` returns data. None ships, so nothing is mocked.

## 6. Permissions & security

- Portal access: **logged-in only** (FC setting). Members directory, profiles and space lists: logged-in only.
- Plugin screens redirect guests to sign-in and send `noindex` + no-cache.
- Forms (`admin-post.php`): logged-in check → nonce → object permission → validation → sanitise; output escaped.
  - Edit project: owner, listed collaborators, or users with `edit_others_posts`. Only owner/moderators change collaborators.
  - Rate limit: 5 new projects per member per 24h. Cover uploads: JPG/PNG/WebP ≤5 MB, MIME + image checks.
- Guests get 401 from `/wp-json/wp/v2/users`; author archives, `?author=` enumeration and the users sitemap are closed.
- Members (no `edit_posts`) don't see the WP toolbar and are redirected away from wp-admin (`admin-post.php` stays reachable).
- Projects are `exclude_from_search` and `show_in_rest => false`.
- SSO: auth code + PKCE (S256), state bound to an HttpOnly cookie, one-time transient, nonce check, RS256/ES256 signature verification against JWKS, `iss`/`aud`/`azp`/`exp`/`iat` checks. Privileged accounts are never auto-linked.

QA verified: guest POSTs are redirected, forged nonces are rejected, and injected `<script>`/`onerror` markup is stripped.

## 7. Erudify integration & identity (SSO)

**Direction:** Erudify (Supabase Auth on Lovable) is the **identity provider**; the Community is an **OIDC client**. One account works in both places. On first sign-in the member's Community account is created automatically and linked by Erudify's `sub`.

**Status:** code shipped and **inactive**. It activates when these constants are added to `wp-config.php`:

```php
define( 'AFLANEX_SSO_ISSUER', 'https://<project-ref>.supabase.co/auth/v1' );
define( 'AFLANEX_SSO_CLIENT_ID', '…' );
define( 'AFLANEX_SSO_CLIENT_SECRET', '…' );
// optional
define( 'AFLANEX_SSO_SIGNUP_URL', 'https://<erudify>/signup' );   // where "Join" sends new people
define( 'AFLANEX_ERUDIFY_URL', 'https://<erudify>' );             // footer link
define( 'AFLANEX_SSO_PROVIDER_NAME', 'Erudify' );
```

When active: the entry page's Join goes to Erudify sign-up, Sign in goes straight to Erudify, and "Continue with Erudify" appears on the FluentCommunity and wp-login screens. Password login remains for admins. Full Erudify-side steps: **[ERUDIFY-SSO-SETUP.md](ERUDIFY-SSO-SETUP.md)**.

**Future learning-data sync (design only):**

| Item | Source → destination | Identifier | Direction | Frequency | Conflict rule |
|---|---|---|---|---|---|
| Identity | Erudify → Community | Erudify `sub` | one-way | on sign-in | Erudify wins for email/name; Community wins for portfolio fields |
| Programme membership | Erudify → Community | `external_programme_id` | one-way | webhook + nightly reconcile | Erudify authoritative; could auto-join matching space |
| Course project submission | Erudify → Community project | `external_project_id` (+ `external_course_id`) | one-way create, member-editable afterwards | webhook | Never overwrite member edits; update only `aflx_sync_status`/`last_synced_at` |
| Milestones, skills, certificates | Erudify → portfolio (display only) | Erudify IDs | one-way, read-only | on view (cached) or webhook | Erudify authoritative; never stored as Community records |

Implement as a `LearningSummaryProvider` plus a signed webhook endpoint (`aflanex/v1/sync`, HMAC-verified) in `src/Integrations/`. Don't scrape Erudify and don't duplicate LMS records.

## 8. Replacing the temporary logo

1. Add `wp-content/themes/aflanex-community/assets/brand/logo.svg` (light backgrounds) and optionally `logo-dark.svg`.
2. That's it: `Brand::logo()` detects the files. The brand-mark component (entry page, footer, portal header) and FluentCommunity's header/auth logo vars all switch automatically.

## 9. Updating brand colours / type

Edit only the **Brand palette** block (and its `html.dark` twin) in `assets/css/tokens.css`. Keep text pairs at ≥4.5:1 contrast (current muted text is #5f6672, ≥5:1 on every surface). The portal inherits the tokens through `portal.css`. To add a brand typeface, load it once in `functions.php` and prepend it to `--aflanex-font-sans` / `--aflanex-font-display`.

## 10. Onboarding, culture, gamification

- Native welcome banner: "Build what's next together." (admin-editable).
- "Your next steps" sidebar card with real signals only: photo/bio → headline & skills → join a space → introduce yourself → reply to someone → share a project. It shows one next action at a time and disappears when everything is done.
- No points, leaderboards or follower counts (FC leaderboard/badges disabled). Portfolios show **projects, posts and replies**: contribution, not popularity.

## 11. Analytics: events to track later

Emitted as WordPress actions today (ready for any analytics sink):
`aflanex/project/created`, `aflanex/project/saved`, `aflanex/project/announced`, `aflanex/project/updated`, `aflanex/profile/saved`, `aflanex/identity/linked`, `aflanex/sso/user_created`.
Plus native FC events (`fluent_community/feed/created`, `space/joined`, comment events).
Key metrics: weekly active members, members with ≥1 meaningful contribution (post, reply or project update), projects created/completed, projects with ≥1 collaborator, replies per question, 30-day retention, onboarding step completion.

## 12. Deployment

Source of truth is this repo. To deploy:

```bash
python dist/build.py   # builds dist/aflanex-community-{plugin,theme}.zip
```

Upload each zip to the server (e.g. `wp-content/aflx-deploy/`, **not** `wp-content/upgrade/`, which WordPress empties), then run:

```bash
wp plugin install <plugin.zip> --force && wp theme install <theme.zip> --force
```

Delete the uploaded zips afterwards. Setup is idempotent (`aflx_setup_version`). Take a DB backup first (`wp db export`); pre-project backup: `~/aflanex-backups/pre-aflanex-2026-09-26.sql`.

**Moving to community.aflanex.com:** change the site URL (standard WP search-replace), update FluentCommunity's portal settings if needed, and re-register the SSO redirect URI `https://<new-domain>/sso/callback/` in Erudify. No code changes.

## 13. Known limitations / next steps

- **Email is not configured (FluentSMTP)**, so password resets and notifications may not deliver. Configure a provider before inviting members.
- **Joining:** until SSO is configured, WordPress registration is closed and the entry page shows Sign in only (no dead Join link). Enable either SSO (recommended, per your one-account decision) or FC registration.
- FluentCommunity **free** has no custom profile fields. Portfolio fields live on the Portfolio screen (linked from the FC profile as a "Portfolio" tab and the avatar menu).
- Moderation is available natively (report/flag) but FC's advanced moderation (profanity filter, approval queues) is Pro.
- Collaborators added **after** a project is announced aren't @mentioned automatically.
- Member-level role QA (a non-admin account) still needs a real test member; no test accounts were created on production.
