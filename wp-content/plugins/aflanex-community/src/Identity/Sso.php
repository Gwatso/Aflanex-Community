<?php

namespace Aflanex\Community\Identity;

use Aflanex\Community\Integrations\ExternalIdentity;

defined( 'ABSPATH' ) || exit;

/**
 * "Continue with Erudify": one Aflanex account across Erudify and the
 * Community.
 *
 * Erudify (Supabase Auth, OAuth 2.1 / OIDC server) is the identity
 * provider. This site is a confidential OIDC client. On first sign-in a
 * Community account is provisioned and linked by the Erudify subject
 * (`sub`), never by WordPress user ID.
 *
 * INERT UNTIL CONFIGURED. Nothing is shown or routed unless wp-config.php
 * defines:
 *   AFLANEX_SSO_ISSUER         https://<project-ref>.supabase.co/auth/v1
 *   AFLANEX_SSO_CLIENT_ID
 *   AFLANEX_SSO_CLIENT_SECRET
 * Optional:
 *   AFLANEX_SSO_PROVIDER_NAME  Button label name (default "Erudify")
 *   AFLANEX_SSO_SIGNUP_URL     Where "Join" sends new people (Erudify sign-up)
 *   AFLANEX_ERUDIFY_URL        Public Erudify link for the footer
 *
 * See docs/ERUDIFY-SSO-SETUP.md.
 */
final class Sso {

	private const COOKIE = 'aflx_sso_state';
	private const TTL    = 600;

	public static function register(): void {
		add_action( 'init', [ self::class, 'rewrites' ] );
		add_filter( 'query_vars', static fn( $vars ) => array_merge( $vars, [ 'aflx_sso' ] ) );
		add_action( 'parse_request', [ self::class, 'dispatch' ] );

		if ( ! self::is_enabled() ) {
			return;
		}

		add_action( 'fluent_community/before_auth_form_header', [ self::class, 'render_auth_button' ] );
		add_filter( 'login_message', [ self::class, 'wp_login_message' ] );
	}

	public static function is_enabled(): bool {
		return defined( 'AFLANEX_SSO_ISSUER' ) && defined( 'AFLANEX_SSO_CLIENT_ID' ) && defined( 'AFLANEX_SSO_CLIENT_SECRET' )
			&& AFLANEX_SSO_ISSUER && AFLANEX_SSO_CLIENT_ID && AFLANEX_SSO_CLIENT_SECRET;
	}

	public static function provider_name(): string {
		return defined( 'AFLANEX_SSO_PROVIDER_NAME' ) && AFLANEX_SSO_PROVIDER_NAME ? (string) AFLANEX_SSO_PROVIDER_NAME : 'Erudify';
	}

	public static function signup_url(): string {
		if ( ! self::is_enabled() ) {
			return '';
		}
		return defined( 'AFLANEX_SSO_SIGNUP_URL' ) && AFLANEX_SSO_SIGNUP_URL ? (string) AFLANEX_SSO_SIGNUP_URL : self::start_url();
	}

	public static function redirect_uri(): string {
		return home_url( '/sso/callback/' );
	}

	public static function start_url( string $redirect_to = '' ): string {
		$url = home_url( '/sso/start/' );
		return $redirect_to ? add_query_arg( 'redirect_to', rawurlencode( $redirect_to ), $url ) : $url;
	}

	private static function client(): OidcClient {
		return new OidcClient( (string) AFLANEX_SSO_ISSUER, (string) AFLANEX_SSO_CLIENT_ID, (string) AFLANEX_SSO_CLIENT_SECRET );
	}

	public static function rewrites(): void {
		add_rewrite_rule( '^sso/(start|callback)/?$', 'index.php?aflx_sso=$matches[1]', 'top' );
	}

	public static function dispatch( \WP $wp ): void {
		$action = $wp->query_vars['aflx_sso'] ?? '';
		if ( ! $action ) {
			return;
		}
		if ( ! self::is_enabled() ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}
		nocache_headers();
		'callback' === $action ? self::callback() : self::start();
	}

	private static function portal_url(): string {
		return class_exists( '\FluentCommunity\App\Services\Helper' ) ? \FluentCommunity\App\Services\Helper::baseUrl( '' ) : home_url( '/' );
	}

	/* ------------------------------------------------------------------ */
	/* Start                                                               */
	/* ------------------------------------------------------------------ */

	private static function start(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- state param protects the flow.
		$redirect_to = isset( $_GET['redirect_to'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ), self::portal_url() ) : self::portal_url();

		if ( is_user_logged_in() ) {
			wp_safe_redirect( $redirect_to );
			exit;
		}

		$state    = bin2hex( random_bytes( 16 ) );
		$nonce    = bin2hex( random_bytes( 16 ) );
		$verifier = OidcClient::b64url_encode( random_bytes( 32 ) );

		set_transient(
			'aflx_sso_' . hash( 'sha256', $state ),
			[ 'nonce' => $nonce, 'verifier' => $verifier, 'redirect_to' => $redirect_to ],
			self::TTL
		);

		setcookie(
			self::COOKIE,
			$state,
			[
				'expires'  => time() + self::TTL,
				'path'     => COOKIEPATH ?: '/',
				'domain'   => COOKIE_DOMAIN ?: '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			]
		);

		$url = self::client()->authorization_url(
			self::redirect_uri(),
			$state,
			$nonce,
			OidcClient::b64url_encode( hash( 'sha256', $verifier, true ) )
		);

		if ( is_wp_error( $url ) ) {
			self::fail( 'unavailable', $url );
		}

		// External redirect to the configured identity provider only.
		wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Callback                                                            */
	/* ------------------------------------------------------------------ */

	private static function callback(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- OAuth state is the CSRF token.
		if ( isset( $_GET['error'] ) ) {
			self::fail( 'access_denied' === sanitize_key( wp_unslash( $_GET['error'] ) ) ? 'denied' : 'provider_error' );
		}

		$state  = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$code   = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		$cookie = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		// phpcs:enable

		// The state must match the one issued to *this* browser (prevents login CSRF).
		if ( '' === $state || '' === $cookie || ! hash_equals( $cookie, $state ) ) {
			self::fail( 'expired' );
		}

		$key     = 'aflx_sso_' . hash( 'sha256', $state );
		$pending = get_transient( $key );
		delete_transient( $key ); // One-time use.
		self::clear_cookie();

		if ( ! is_array( $pending ) || '' === $code ) {
			self::fail( 'expired' );
		}

		$client = self::client();
		$tokens = $client->exchange_code( $code, self::redirect_uri(), $pending['verifier'] );
		if ( is_wp_error( $tokens ) ) {
			self::fail( 'provider_error', $tokens );
		}

		$claims = $client->verify_id_token( (string) $tokens['id_token'], (string) $pending['nonce'] );
		if ( is_wp_error( $claims ) ) {
			self::fail( 'invalid_token', $claims );
		}

		// Profile enrichment is optional; verified ID token claims win on conflict.
		$claims = array_merge( $client->userinfo( (string) ( $tokens['access_token'] ?? '' ) ), $claims );

		$user = self::resolve_user( $claims );
		if ( is_wp_error( $user ) ) {
			self::fail( $user->get_error_code(), $user );
		}

		ExternalIdentity::link(
			$user->ID,
			ExternalIdentity::SOURCE_ERUDIFY,
			(string) $claims['sub'],
			[ 'issuer' => (string) AFLANEX_SSO_ISSUER, 'last_login_at' => gmdate( 'c' ) ]
		);

		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true, is_ssl() );
		do_action( 'wp_login', $user->user_login, $user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		wp_safe_redirect( $pending['redirect_to'] ?: self::portal_url() );
		exit;
	}

	/**
	 * Find the linked account, link a verified-email match, or create one.
	 *
	 * @return \WP_User|\WP_Error
	 */
	private static function resolve_user( array $claims ) {
		$sub  = (string) $claims['sub'];
		$user = ExternalIdentity::find_user( ExternalIdentity::SOURCE_ERUDIFY, $sub );
		if ( $user ) {
			return $user;
		}

		$email    = sanitize_email( (string) ( $claims['email'] ?? '' ) );
		$verified = true === ( $claims['email_verified'] ?? false ) || 'true' === ( $claims['email_verified'] ?? '' );

		if ( ! $email ) {
			return new \WP_Error( 'no_email', 'Identity provider did not share an email address.' );
		}

		$existing = get_user_by( 'email', $email );
		if ( $existing ) {
			if ( ! $verified ) {
				return new \WP_Error( 'email_unverified', 'Email not verified by identity provider.' );
			}
			// Privileged accounts are never auto-linked; an admin links them deliberately.
			if ( user_can( $existing, 'edit_others_posts' ) || user_can( $existing, 'manage_options' ) ) {
				return new \WP_Error( 'link_manually', 'Privileged account requires manual linking.' );
			}
			if ( ExternalIdentity::is_linked( $existing->ID, ExternalIdentity::SOURCE_ERUDIFY ) ) {
				return new \WP_Error( 'already_linked', 'This email is linked to a different Erudify account.' );
			}
			return $existing;
		}

		return self::create_user( $email, $claims );
	}

	/**
	 * @return \WP_User|\WP_Error
	 */
	private static function create_user( string $email, array $claims ) {
		$meta   = is_array( $claims['user_metadata'] ?? null ) ? $claims['user_metadata'] : [];
		$name   = trim( (string) ( $claims['name'] ?? $meta['full_name'] ?? $meta['name'] ?? '' ) );
		$given  = (string) ( $claims['given_name'] ?? '' );
		$family = (string) ( $claims['family_name'] ?? '' );
		if ( '' === $name ) {
			$name = trim( $given . ' ' . $family ) ?: (string) strstr( $email, '@', true );
		}

		$base  = sanitize_user( strtolower( (string) strstr( $email, '@', true ) ), true );
		$base  = substr( preg_replace( '/[^a-z0-9_.-]/', '', $base ) ?: 'member', 0, 40 );
		$login = $base;
		for ( $i = 2; username_exists( $login ); $i++ ) {
			$login = $base . $i;
		}

		$role = apply_filters( 'aflanex/sso/new_user_role', 'subscriber' );
		if ( ! in_array( $role, [ 'subscriber' ], true ) && ! apply_filters( 'aflanex/sso/allow_privileged_role', false ) ) {
			$role = 'subscriber';
		}

		$user_id = wp_insert_user(
			[
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 40, true, true ),
				'display_name' => sanitize_text_field( $name ),
				'first_name'   => sanitize_text_field( $given ),
				'last_name'    => sanitize_text_field( $family ),
				'role'         => $role,
			]
		);

		if ( is_wp_error( $user_id ) ) {
			return new \WP_Error( 'create_failed', $user_id->get_error_message() );
		}

		// Create the FluentCommunity profile right away so the member lands in a ready portal.
		if ( class_exists( '\FluentCommunity\App\Models\User' ) ) {
			$fc_user = \FluentCommunity\App\Models\User::find( $user_id );
			if ( $fc_user ) {
				$fc_user->syncXProfile();
			}
		}

		update_user_meta( $user_id, 'aflx_account_source', ExternalIdentity::SOURCE_ERUDIFY );
		do_action( 'aflanex/sso/user_created', $user_id, $claims );

		return get_userdata( $user_id );
	}

	/**
	 * @param string              $code
	 * @param \WP_Error|null      $error Logged for admins; never shown raw to users.
	 */
	private static function fail( string $code, $error = null ): void {
		self::clear_cookie();
		if ( $error instanceof \WP_Error && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[aflanex-sso] ' . $code . ': ' . $error->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		$auth = class_exists( '\FluentCommunity\App\Services\Helper' ) ? \FluentCommunity\App\Services\Helper::getAuthUrl() : wp_login_url();
		wp_safe_redirect( add_query_arg( 'aflx_sso_error', sanitize_key( $code ), $auth ) );
		exit;
	}

	private static function clear_cookie(): void {
		setcookie( self::COOKIE, '', [ 'expires' => time() - HOUR_IN_SECONDS, 'path' => COOKIEPATH ?: '/', 'domain' => COOKIE_DOMAIN ?: '', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ] );
	}

	public static function error_message( string $code ): string {
		$messages = [
			'denied'           => __( 'Sign-in was cancelled.', 'aflanex-community' ),
			'expired'          => __( 'Your sign-in attempt timed out. Please try again.', 'aflanex-community' ),
			'no_email'         => __( 'We need an email address from your Erudify account to create your Community profile.', 'aflanex-community' ),
			'email_unverified' => __( 'Please verify your email address in Erudify, then try again.', 'aflanex-community' ),
			'link_manually'    => __( 'This account needs to be linked by a community admin. Please contact the Aflanex team.', 'aflanex-community' ),
			'already_linked'   => __( 'This email is already linked to a different Erudify account. Please contact the Aflanex team.', 'aflanex-community' ),
		];
		return $messages[ $code ] ?? __( 'We couldn’t sign you in with Erudify just now. Please try again in a moment.', 'aflanex-community' );
	}

	/* ------------------------------------------------------------------ */
	/* UI                                                                  */
	/* ------------------------------------------------------------------ */

	public static function render_auth_button(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$error = isset( $_GET['aflx_sso_error'] ) ? sanitize_key( wp_unslash( $_GET['aflx_sso_error'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$redirect = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '';
		?>
		<div class="aflx-sso">
			<?php if ( $error ) : ?>
				<div class="aflx-alert aflx-alert--danger" role="alert"><?php echo esc_html( self::error_message( $error ) ); ?></div>
			<?php endif; ?>
			<a class="aflx-sso__btn" href="<?php echo esc_url( self::start_url( $redirect ) ); ?>">
				<?php
				/* translators: %s: identity provider name, e.g. Erudify */
				echo esc_html( sprintf( __( 'Continue with %s', 'aflanex-community' ), self::provider_name() ) );
				?>
			</a>
			<div class="aflx-sso__divider"><?php esc_html_e( 'or use your community password', 'aflanex-community' ); ?></div>
		</div>
		<?php
	}

	public static function wp_login_message( string $message ): string {
		$link = sprintf(
			'<p class="message"><a href="%1$s"><strong>%2$s</strong></a></p>',
			esc_url( self::start_url() ),
			/* translators: %s: identity provider name */
			esc_html( sprintf( __( 'Continue with %s', 'aflanex-community' ), self::provider_name() ) )
		);
		return $link . $message;
	}
}
