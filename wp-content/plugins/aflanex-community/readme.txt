=== Aflanex Community ===
Contributors: aflanex
Tags: community, projects, portfolio, fluentcommunity, sso
Requires at least: 6.5
Tested up to: 7.1.2
Requires PHP: 8.0
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Projects, member portfolios, people discovery, onboarding and Erudify sign-in for Aflanex Community, built on FluentCommunity.

== Description ==

Aflanex Community is where people who are learning, building and doing what's next meet, share their work and collaborate. This plugin adds the parts of that experience that FluentCommunity doesn't provide, using only FluentCommunity's supported hooks.

**What it adds**

* **Projects:** members start projects, set a status (Idea, In progress, Seeking collaborators, Ready for feedback, Completed), list skills and links, invite collaborators and post progress updates. Every project gets a discussion thread in the Projects space, so comments, notifications and moderation are handled by FluentCommunity.
* **Portfolios:** a lightweight talent profile with headline, skills, interests, what someone is learning, how they can help and what they're open to, plus their projects and contributions. No follower counts.
* **People:** discover members by skills, focus areas and what they're open to.
* **Onboarding:** a short "Your next steps" checklist based on what each member has actually done.
* **Erudify sign-in:** optional OpenID Connect sign-in so learners use one Aflanex account for Erudify and the Community. Inactive until configured.
* **Privacy:** members-only directories and profiles, personal data export and erasure, and suggested privacy-policy text.

Presentation (design tokens, brand mark, templates styling) lives in the companion Aflanex Community child theme.

== Installation ==

1. Make sure FluentCommunity is installed and active (it's a required plugin).
2. Upload the `aflanex-community` folder to `/wp-content/plugins/`, or install the release zip from Plugins → Add New → Upload Plugin.
3. Activate the plugin. It creates the community pages (Projects, People, Portfolio, Community guidelines), the Projects space and a starting set of focus areas. Existing content is never overwritten.
4. Visit Settings → Aflanex Community to check status and adjust settings.
5. Optional: to enable Erudify sign-in, follow `docs/ERUDIFY-SSO-SETUP.md` in the repository and add the `AFLANEX_SSO_*` constants to `wp-config.php`.

== Frequently Asked Questions ==

= Does this turn the community into a learning platform? =

No. Erudify is the learning platform. This plugin deliberately has no courses, lessons or progress tracking. It can display learning information from Erudify in the future, through a controlled API.

= Where do I manage spaces, members and moderation? =

In FluentCommunity. This plugin doesn't duplicate those screens.

= How do I replace the temporary logo? =

Add `logo.svg` (and optionally `logo-dark.svg`) to the child theme's `assets/brand/` folder. The entry page, portal header and footer switch automatically.

= What happens to data if I delete the plugin? =

Nothing, unless you turn on "Delete all Aflanex Community data when the plugin is deleted" under Settings → Aflanex Community. FluentCommunity posts and spaces are never removed.

= How are updates delivered? =

From releases on the GitHub repository. WordPress shows update notices and supports auto-updates like any other plugin.

== Changelog ==

= 1.1.0 =
* New: Settings → Aflanex Community with status checks, settings and a "Repair setup" tool.
* New: Settings, Docs and Report an issue links on the Plugins screen.
* New: updates and auto-updates from GitHub releases, with a "View details" modal.
* New: personal data exporter and eraser, plus suggested privacy-policy text.
* New: safe uninstall. Data is removed only when you opt in.
* New: translation-ready (`languages/aflanex-community.pot`).
* Improved: the daily project limit and public stats threshold are now settings.
* Declares FluentCommunity as a required plugin.

= 1.0.0 =
* First release: projects, portfolios, people discovery, onboarding checklist, FluentCommunity integration, privacy hardening and Erudify sign-in (OpenID Connect).

== Upgrade Notice ==

= 1.1.0 =
Adds a settings page, update support and privacy tools. No data changes.
