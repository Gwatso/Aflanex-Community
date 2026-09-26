<?php

namespace Aflanex\Community\Identity;

defined( 'ABSPATH' ) || exit;

/**
 * Minimal, dependency-free OpenID Connect relying party:
 * discovery, authorization-code + PKCE (S256), token exchange, and ID
 * token verification (RS256 / ES256 against the provider's JWKS).
 *
 * Built for Supabase Auth's OAuth 2.1 / OIDC server (which Erudify on
 * Lovable uses) but standards-based, so a future central "Aflanex ID"
 * provider needs configuration only.
 */
final class OidcClient {

	private const LEEWAY = 60;

	public function __construct(
		private string $issuer,
		private string $client_id,
		private string $client_secret
	) {
		$this->issuer = untrailingslashit( $issuer );
	}

	/* ------------------------------------------------------------------ */
	/* Discovery & keys                                                    */
	/* ------------------------------------------------------------------ */

	/**
	 * @return array|\WP_Error
	 */
	public function discovery( bool $refresh = false ) {
		$cache_key = 'aflx_oidc_disc_' . md5( $this->issuer );
		$doc       = $refresh ? false : get_transient( $cache_key );

		if ( ! is_array( $doc ) ) {
			$doc = $this->get_json( $this->issuer . '/.well-known/openid-configuration' );
			if ( is_wp_error( $doc ) ) {
				return $doc;
			}
			foreach ( [ 'issuer', 'authorization_endpoint', 'token_endpoint', 'jwks_uri' ] as $required ) {
				if ( empty( $doc[ $required ] ) ) {
					return new \WP_Error( 'oidc_discovery', 'Discovery document is missing ' . $required );
				}
			}
			if ( untrailingslashit( $doc['issuer'] ) !== $this->issuer ) {
				return new \WP_Error( 'oidc_issuer_mismatch', 'Discovery issuer does not match configured issuer.' );
			}
			set_transient( $cache_key, $doc, 12 * HOUR_IN_SECONDS );
		}

		return $doc;
	}

	/**
	 * @return array|\WP_Error Keys indexed by kid.
	 */
	private function jwks( bool $refresh = false ) {
		$doc = $this->discovery();
		if ( is_wp_error( $doc ) ) {
			return $doc;
		}

		$cache_key = 'aflx_oidc_jwks_' . md5( $doc['jwks_uri'] );
		$keys      = $refresh ? false : get_transient( $cache_key );

		if ( ! is_array( $keys ) ) {
			$set = $this->get_json( $doc['jwks_uri'] );
			if ( is_wp_error( $set ) ) {
				return $set;
			}
			$keys = [];
			foreach ( (array) ( $set['keys'] ?? [] ) as $jwk ) {
				if ( ! empty( $jwk['kid'] ) ) {
					$keys[ $jwk['kid'] ] = $jwk;
				}
			}
			set_transient( $cache_key, $keys, HOUR_IN_SECONDS );
		}

		return $keys;
	}

	/* ------------------------------------------------------------------ */
	/* Flow                                                                */
	/* ------------------------------------------------------------------ */

	/**
	 * @return string|\WP_Error
	 */
	public function authorization_url( string $redirect_uri, string $state, string $nonce, string $code_challenge ) {
		$doc = $this->discovery();
		if ( is_wp_error( $doc ) ) {
			return $doc;
		}

		return add_query_arg(
			array_map(
				'rawurlencode',
				[
					'response_type'         => 'code',
					'client_id'             => $this->client_id,
					'redirect_uri'          => $redirect_uri,
					'scope'                 => 'openid email profile',
					'state'                 => $state,
					'nonce'                 => $nonce,
					'code_challenge'        => $code_challenge,
					'code_challenge_method' => 'S256',
				]
			),
			$doc['authorization_endpoint']
		);
	}

	/**
	 * @return array|\WP_Error Token response.
	 */
	public function exchange_code( string $code, string $redirect_uri, string $code_verifier ) {
		$doc = $this->discovery();
		if ( is_wp_error( $doc ) ) {
			return $doc;
		}

		$body = [
			'grant_type'    => 'authorization_code',
			'code'          => $code,
			'redirect_uri'  => $redirect_uri,
			'code_verifier' => $code_verifier,
			'client_id'     => $this->client_id,
		];

		$methods = (array) ( $doc['token_endpoint_auth_methods_supported'] ?? [ 'client_secret_basic' ] );
		$headers = [ 'Accept' => 'application/json' ];
		if ( in_array( 'client_secret_basic', $methods, true ) ) {
			$headers['Authorization'] = 'Basic ' . base64_encode( rawurlencode( $this->client_id ) . ':' . rawurlencode( $this->client_secret ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		} else {
			$body['client_secret'] = $this->client_secret;
		}

		$response = wp_remote_post(
			$doc['token_endpoint'],
			[
				'timeout' => 10,
				'headers' => $headers,
				'body'    => $body,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$json = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) || ! is_array( $json ) || empty( $json['id_token'] ) ) {
			return new \WP_Error( 'oidc_token', 'Token exchange failed.' );
		}

		return $json;
	}

	/**
	 * Optional profile enrichment from the UserInfo endpoint.
	 */
	public function userinfo( string $access_token ): array {
		$doc = $this->discovery();
		if ( is_wp_error( $doc ) || empty( $doc['userinfo_endpoint'] ) || '' === $access_token ) {
			return [];
		}
		$response = wp_remote_get(
			$doc['userinfo_endpoint'],
			[
				'timeout' => 8,
				'headers' => [ 'Authorization' => 'Bearer ' . $access_token, 'Accept' => 'application/json' ],
			]
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return [];
		}
		$json = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return is_array( $json ) ? $json : [];
	}

	/* ------------------------------------------------------------------ */
	/* ID token verification                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * @return array|\WP_Error Verified claims.
	 */
	public function verify_id_token( string $jwt, string $expected_nonce ) {
		$parts = explode( '.', $jwt );
		if ( 3 !== count( $parts ) ) {
			return new \WP_Error( 'oidc_jwt', 'Malformed ID token.' );
		}
		[ $h64, $p64, $s64 ] = $parts;

		$header  = json_decode( self::b64url_decode( $h64 ), true );
		$claims  = json_decode( self::b64url_decode( $p64 ), true );
		$sig     = self::b64url_decode( $s64 );
		if ( ! is_array( $header ) || ! is_array( $claims ) || '' === $sig ) {
			return new \WP_Error( 'oidc_jwt', 'Malformed ID token.' );
		}

		$alg = (string) ( $header['alg'] ?? '' );
		if ( ! in_array( $alg, [ 'RS256', 'ES256' ], true ) ) {
			// Symmetric or "none" algorithms are never accepted.
			return new \WP_Error( 'oidc_alg', 'Unsupported ID token algorithm.' );
		}

		$kid  = (string) ( $header['kid'] ?? '' );
		$keys = $this->jwks();
		if ( ! is_wp_error( $keys ) && ! isset( $keys[ $kid ] ) ) {
			$keys = $this->jwks( true ); // Key rotation.
		}
		if ( is_wp_error( $keys ) ) {
			return $keys;
		}
		if ( ! isset( $keys[ $kid ] ) ) {
			return new \WP_Error( 'oidc_kid', 'Unknown signing key.' );
		}

		$pem = self::jwk_to_pem( $keys[ $kid ], $alg );
		if ( ! $pem ) {
			return new \WP_Error( 'oidc_key', 'Unusable signing key.' );
		}

		if ( 'ES256' === $alg ) {
			$sig = self::es256_raw_to_der( $sig );
			if ( ! $sig ) {
				return new \WP_Error( 'oidc_sig', 'Invalid signature encoding.' );
			}
		}

		if ( 1 !== openssl_verify( $h64 . '.' . $p64, $sig, $pem, OPENSSL_ALGO_SHA256 ) ) {
			return new \WP_Error( 'oidc_sig', 'ID token signature is invalid.' );
		}

		$now = time();
		$aud = (array) ( $claims['aud'] ?? [] );

		if ( untrailingslashit( (string) ( $claims['iss'] ?? '' ) ) !== $this->issuer ) {
			return new \WP_Error( 'oidc_iss', 'Unexpected issuer.' );
		}
		if ( ! in_array( $this->client_id, $aud, true ) ) {
			return new \WP_Error( 'oidc_aud', 'Token was not issued for this site.' );
		}
		if ( count( $aud ) > 1 && ( $claims['azp'] ?? '' ) !== $this->client_id ) {
			return new \WP_Error( 'oidc_azp', 'Unexpected authorized party.' );
		}
		if ( empty( $claims['exp'] ) || (int) $claims['exp'] < $now - self::LEEWAY ) {
			return new \WP_Error( 'oidc_exp', 'ID token expired.' );
		}
		if ( ! empty( $claims['iat'] ) && (int) $claims['iat'] > $now + self::LEEWAY ) {
			return new \WP_Error( 'oidc_iat', 'ID token issued in the future.' );
		}
		if ( ! hash_equals( $expected_nonce, (string) ( $claims['nonce'] ?? '' ) ) ) {
			return new \WP_Error( 'oidc_nonce', 'Nonce mismatch.' );
		}
		if ( empty( $claims['sub'] ) || ! is_string( $claims['sub'] ) ) {
			return new \WP_Error( 'oidc_sub', 'Missing subject.' );
		}

		return $claims;
	}

	/* ------------------------------------------------------------------ */
	/* Helpers                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * @return array|\WP_Error
	 */
	private function get_json( string $url ) {
		if ( 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) ) {
			return new \WP_Error( 'oidc_insecure', 'Identity provider URLs must use HTTPS.' );
		}
		$response = wp_remote_get( $url, [ 'timeout' => 8, 'headers' => [ 'Accept' => 'application/json' ] ] );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error( 'oidc_http', 'Identity provider returned HTTP ' . wp_remote_retrieve_response_code( $response ) );
		}
		$json = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return is_array( $json ) ? $json : new \WP_Error( 'oidc_json', 'Invalid JSON from identity provider.' );
	}

	public static function b64url_encode( string $bin ): string {
		return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	public static function b64url_decode( string $str ): string {
		$pad = strlen( $str ) % 4;
		if ( $pad ) {
			$str .= str_repeat( '=', 4 - $pad );
		}
		$out = base64_decode( strtr( $str, '-_', '+/' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		return false === $out ? '' : $out;
	}

	private static function der_len( int $len ): string {
		if ( $len < 0x80 ) {
			return chr( $len );
		}
		$bytes = ltrim( pack( 'N', $len ), "\0" );
		return chr( 0x80 | strlen( $bytes ) ) . $bytes;
	}

	private static function der_uint( string $bytes ): string {
		$bytes = ltrim( $bytes, "\0" );
		if ( '' === $bytes ) {
			$bytes = "\0";
		}
		if ( ord( $bytes[0] ) > 0x7f ) {
			$bytes = "\0" . $bytes;
		}
		return "\x02" . self::der_len( strlen( $bytes ) ) . $bytes;
	}

	private static function jwk_to_pem( array $jwk, string $alg ): string {
		if ( 'RS256' === $alg && 'RSA' === ( $jwk['kty'] ?? '' ) && ! empty( $jwk['n'] ) && ! empty( $jwk['e'] ) ) {
			$rsa    = self::der_uint( self::b64url_decode( $jwk['n'] ) ) . self::der_uint( self::b64url_decode( $jwk['e'] ) );
			$rsa    = "\x30" . self::der_len( strlen( $rsa ) ) . $rsa;
			$bits   = "\x03" . self::der_len( strlen( $rsa ) + 1 ) . "\x00" . $rsa;
			$alg_id = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
		} elseif ( 'ES256' === $alg && 'EC' === ( $jwk['kty'] ?? '' ) && 'P-256' === ( $jwk['crv'] ?? '' ) ) {
			$x = self::b64url_decode( (string) ( $jwk['x'] ?? '' ) );
			$y = self::b64url_decode( (string) ( $jwk['y'] ?? '' ) );
			if ( 32 !== strlen( $x ) || 32 !== strlen( $y ) ) {
				return '';
			}
			$point  = "\x04" . $x . $y;
			$bits   = "\x03" . self::der_len( strlen( $point ) + 1 ) . "\x00" . $point;
			$alg_id = "\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";
		} else {
			return '';
		}

		$spki = "\x30" . self::der_len( strlen( $alg_id . $bits ) ) . $alg_id . $bits;

		return "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $spki ), 64, "\n" ) . "-----END PUBLIC KEY-----\n"; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * JWS ES256 signatures are raw r||s (64 bytes); OpenSSL expects DER.
	 */
	private static function es256_raw_to_der( string $raw ): string {
		if ( 64 !== strlen( $raw ) ) {
			return '';
		}
		$seq = self::der_uint( substr( $raw, 0, 32 ) ) . self::der_uint( substr( $raw, 32 ) );
		return "\x30" . self::der_len( strlen( $seq ) ) . $seq;
	}
}
