<?php

namespace Aflanex\Community\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Minimal parser for the plugin's WordPress-format readme.txt, used for
 * the "View details" modal. Supports the subset the readme uses:
 * == Section ==, = Subheading =, paragraphs, "* " lists, **bold**, `code`
 * and [text](url) links.
 */
final class Readme {

	private static function raw(): string {
		$file = AFLANEX_COMMUNITY_DIR . 'readme.txt';
		return is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	public static function headers(): array {
		$map = [ 'Requires at least' => 'requires', 'Tested up to' => 'tested', 'Requires PHP' => 'requires_php', 'Stable tag' => 'stable' ];
		$out = [];
		foreach ( $map as $label => $key ) {
			if ( preg_match( '/^' . preg_quote( $label, '/' ) . ':\s*(.+)$/mi', self::raw(), $m ) ) {
				$out[ $key ] = trim( $m[1] );
			}
		}
		return $out;
	}

	/**
	 * @return array<string,string> section key => HTML
	 */
	public static function sections_html(): array {
		$parts    = preg_split( '/^==\s*(.+?)\s*==\s*$/m', self::raw(), -1, PREG_SPLIT_DELIM_CAPTURE );
		$sections = [];

		for ( $i = 1; $i < count( $parts ); $i += 2 ) {
			$key = sanitize_key( str_replace( ' ', '_', strtolower( $parts[ $i ] ) ) );
			if ( in_array( $key, [ 'description', 'installation', 'frequently_asked_questions', 'changelog', 'upgrade_notice' ], true ) ) {
				$sections[ 'frequently_asked_questions' === $key ? 'faq' : $key ] = self::to_html( (string) ( $parts[ $i + 1 ] ?? '' ) );
			}
		}

		return $sections;
	}

	private static function inline( string $text ): string {
		$text = esc_html( $text );
		$text = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text );
		$text = preg_replace( '/`(.+?)`/', '<code>$1</code>', $text );
		return preg_replace_callback(
			'/\[(.+?)\]\((https?:\/\/[^\s)]+)\)/',
			static fn( $m ) => '<a href="' . esc_url( html_entity_decode( $m[2] ) ) . '">' . $m[1] . '</a>',
			$text
		);
	}

	private static function to_html( string $body ): string {
		$html  = '';
		$list  = false;
		$para  = [];
		$flush = static function () use ( &$para, &$html ) {
			if ( $para ) {
				$html .= '<p>' . implode( ' ', $para ) . '</p>';
				$para  = [];
			}
		};

		foreach ( preg_split( '/\R/', trim( $body ) ) as $line ) {
			$line = rtrim( $line );
			if ( preg_match( '/^=\s*(.+?)\s*=$/', $line, $m ) ) {
				$flush();
				if ( $list ) { $html .= '</ul>'; $list = false; }
				$html .= '<h4>' . self::inline( $m[1] ) . '</h4>';
			} elseif ( preg_match( '/^[*-]\s+(.+)$/', $line, $m ) ) {
				$flush();
				if ( ! $list ) { $html .= '<ul>'; $list = true; }
				$html .= '<li>' . self::inline( $m[1] ) . '</li>';
			} elseif ( '' === trim( $line ) ) {
				$flush();
				if ( $list ) { $html .= '</ul>'; $list = false; }
			} else {
				if ( $list ) { $html .= '</ul>'; $list = false; }
				$para[] = self::inline( trim( $line ) );
			}
		}
		$flush();
		if ( $list ) {
			$html .= '</ul>';
		}

		return wp_kses_post( $html );
	}
}
