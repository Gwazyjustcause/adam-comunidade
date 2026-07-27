<?php
/**
 * Runtime-generated ADAM placeholder artwork.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade;

defined( 'ABSPATH' ) || exit;

/**
 * Generates deterministic inline SVG covers and avatars without media files.
 */
final class Placeholder_Image {
	private static int $instance = 0;

	/**
	 * Renders a branded landscape cover.
	 */
	public static function cover( string $type, string $name, string $class = '' ): string {
		return self::render( $type, $name, 'cover', $class );
	}

	/**
	 * Renders a branded square identity/avatar.
	 */
	public static function avatar( string $type, string $name, string $class = '' ): string {
		return self::render( $type, $name, 'avatar', $class );
	}

	/**
	 * Builds one self-contained SVG.
	 */
	private static function render( string $type, string $name, string $variant, string $class ): string {
		$type     = self::type( $type );
		$name     = self::name( $name, self::label( $type ) );
		$avatar   = 'avatar' === $variant;
		$width    = $avatar ? 400 : 1200;
		$height   = $avatar ? 400 : 675;
		$seed     = abs( crc32( $type . '|' . strtolower( $name ) ) );
		$accent_x = 120 + ( $seed % max( 1, $width - 240 ) );
		$accent_y = 100 + ( (int) floor( $seed / 7 ) % max( 1, $height - 200 ) );
		$id       = 'adam-placeholder-' . (++self::$instance) . '-' . substr( md5( $type . $name . $variant ), 0, 8 );
		$classes  = trim( 'adam-generated-placeholder adam-generated-placeholder--' . $variant . ' adam-generated-placeholder--' . $type . ' ' . $class );
		$title    = $avatar
			? sprintf( __( 'Emblema provisório de %s', 'adam-comunidade' ), $name )
			: sprintf( __( 'Sem fotografia: %s', 'adam-comunidade' ), $name );
		$display_name = self::name( $name, self::label( $type ), $avatar ? 22 : 42 );
		$initials     = self::initials( $name );

		ob_start();
		?>
		<svg class="<?php echo esc_attr( $classes ); ?>" viewBox="0 0 <?php echo esc_attr( (string) $width ); ?> <?php echo esc_attr( (string) $height ); ?>" role="img" aria-label="<?php echo esc_attr( $title ); ?>" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid slice">
			<title><?php echo esc_html( $title ); ?></title>
			<defs>
				<linearGradient id="<?php echo esc_attr( $id ); ?>-background" x1="0" y1="0" x2="1" y2="1">
					<stop offset="0" stop-color="#0b160d"/>
					<stop offset=".52" stop-color="#29421e"/>
					<stop offset="1" stop-color="#78920e"/>
				</linearGradient>
				<radialGradient id="<?php echo esc_attr( $id ); ?>-glow" cx=".5" cy=".5" r=".5">
					<stop offset="0" stop-color="#c9f6a1" stop-opacity=".28"/>
					<stop offset="1" stop-color="#c9f6a1" stop-opacity="0"/>
				</radialGradient>
				<pattern id="<?php echo esc_attr( $id ); ?>-texture" width="96" height="96" patternUnits="userSpaceOnUse" patternTransform="rotate(<?php echo esc_attr( (string) ( 18 + ( $seed % 24 ) ) ); ?>)">
					<path d="M-20 18L24-8 62 12 24 38zM42 72L86 46l38 20-38 26z" fill="#d9efbf" opacity=".055"/>
					<path d="M4 66L38 46l28 15-34 20z" fill="none" stroke="#fff" stroke-opacity=".045" stroke-width="2"/>
				</pattern>
				<filter id="<?php echo esc_attr( $id ); ?>-shadow" x="-30%" y="-30%" width="160%" height="160%">
					<feDropShadow dx="0" dy="10" stdDeviation="14" flood-color="#020803" flood-opacity=".45"/>
				</filter>
			</defs>
			<rect width="100%" height="100%" fill="url(#<?php echo esc_attr( $id ); ?>-background)"/>
			<rect width="100%" height="100%" fill="url(#<?php echo esc_attr( $id ); ?>-texture)"/>
			<circle cx="<?php echo esc_attr( (string) $accent_x ); ?>" cy="<?php echo esc_attr( (string) $accent_y ); ?>" r="<?php echo $avatar ? '220' : '340'; ?>" fill="url(#<?php echo esc_attr( $id ); ?>-glow)"/>
			<path d="M0 <?php echo esc_attr( (string) ( $height * .72 ) ); ?>C<?php echo esc_attr( (string) ( $width * .24 ) ); ?> <?php echo esc_attr( (string) ( $height * .56 ) ); ?> <?php echo esc_attr( (string) ( $width * .62 ) ); ?> <?php echo esc_attr( (string) ( $height * .9 ) ); ?> <?php echo esc_attr( (string) $width ); ?> <?php echo esc_attr( (string) ( $height * .62 ) ); ?>V<?php echo esc_attr( (string) $height ); ?>H0z" fill="#061008" opacity=".52"/>

			<?php if ( $avatar ) : ?>
				<g transform="translate(200 190)" filter="url(#<?php echo esc_attr( $id ); ?>-shadow)">
					<path d="M0-126L104-86v88c0 71-43 120-104 150C-61 122-104 73-104-2v-84z" fill="#152a16" stroke="#c9f6a1" stroke-width="9"/>
					<path d="M0-92L72-64v58c0 48-27 82-72 107-45-25-72-59-72-107v-58z" fill="#78920e" opacity=".5"/>
					<text x="0" y="18" text-anchor="middle" fill="#fff" font-family="Arial,Helvetica,sans-serif" font-size="74" font-weight="800" letter-spacing="-3"><?php echo esc_html( $initials ); ?></text>
				</g>
				<text x="200" y="352" text-anchor="middle" fill="#dff8c6" font-family="Arial,Helvetica,sans-serif" font-size="18" font-weight="700" letter-spacing="3">ADAM · <?php echo esc_html( strtoupper( self::label( $type ) ) ); ?></text>
			<?php else : ?>
				<g transform="translate(<?php echo esc_attr( (string) ( $width - 238 ) ); ?> 214)" opacity=".94" filter="url(#<?php echo esc_attr( $id ); ?>-shadow)">
					<circle r="112" fill="#102612" stroke="#c9f6a1" stroke-width="7"/>
					<?php echo self::icon( $type ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed internal SVG paths. ?>
				</g>
				<g transform="translate(64 62)">
					<circle cx="25" cy="25" r="24" fill="#c9f6a1"/>
					<text x="25" y="34" text-anchor="middle" fill="#173016" font-family="Arial,Helvetica,sans-serif" font-size="25" font-weight="900">A</text>
					<text x="63" y="33" fill="#effbe3" font-family="Arial,Helvetica,sans-serif" font-size="25" font-weight="800" letter-spacing="5">ADAM</text>
				</g>
				<text x="64" y="<?php echo esc_attr( (string) ( $height - 128 ) ); ?>" fill="#c9f6a1" font-family="Arial,Helvetica,sans-serif" font-size="22" font-weight="800" letter-spacing="4"><?php echo esc_html( strtoupper( self::label( $type ) ) ); ?></text>
				<text x="64" y="<?php echo esc_attr( (string) ( $height - 76 ) ); ?>" fill="#fff" font-family="Arial,Helvetica,sans-serif" font-size="48" font-weight="800">Sem Fotografia</text>
				<text x="64" y="<?php echo esc_attr( (string) ( $height - 34 ) ); ?>" fill="#e1edd7" font-family="Arial,Helvetica,sans-serif" font-size="25" font-weight="600"><?php echo esc_html( $display_name ); ?></text>
			<?php endif; ?>
		</svg>
		<?php
		return trim( (string) ob_get_clean() );
	}

	/**
	 * Fixed icon artwork per entity type.
	 */
	private static function icon( string $type ): string {
		if ( 'field' === $type ) {
			return '<path d="M-66 55V12l28-34 25 24 35-52 48 62v43z" fill="#78920e"/><path d="M-84 58H84M0-78v136M-42-50l42 26 42-26" fill="none" stroke="#e8f8d8" stroke-width="9" stroke-linecap="round" stroke-linejoin="round"/>';
		}
		if ( 'team' === $type ) {
			return '<path d="M0-76L66-50v55c0 46-26 79-66 101C-40 84-66 51-66 5v-55z" fill="#78920e" stroke="#e8f8d8" stroke-width="8"/><path d="M-35 24L35-24M-35-24L35 24" stroke="#fff" stroke-width="10" stroke-linecap="round"/><circle r="13" fill="#fff"/>';
		}
		if ( 'institution' === $type ) {
			return '<path d="M-78-34L0-78l78 44zM-68-20H68M-58-10v62M-20-10v62M20-10v62M58-10v62M-76 66H76" fill="none" stroke="#e8f8d8" stroke-width="10" stroke-linecap="round" stroke-linejoin="round"/>';
		}
		if ( 'brand' === $type ) {
			return '<path d="M0-80L69-40v80L0 80l-69-40v-80z" fill="#78920e" stroke="#e8f8d8" stroke-width="8"/><path d="M-34 4h68M0-34v68" stroke="#fff" stroke-width="11" stroke-linecap="round"/>';
		}
		return '<circle cx="-34" cy="0" r="35" fill="none" stroke="#e8f8d8" stroke-width="10"/><circle cx="34" cy="0" r="35" fill="none" stroke="#e8f8d8" stroke-width="10"/><path d="M-4-18L4 18" stroke="#c9f6a1" stroke-width="12" stroke-linecap="round"/>';
	}

	private static function type( string $type ): string {
		$type = sanitize_key( $type );
		return in_array( $type, array( 'field', 'team', 'partner', 'institution', 'brand' ), true ) ? $type : 'partner';
	}

	private static function label( string $type ): string {
		return array(
			'field'       => __( 'Campo', 'adam-comunidade' ),
			'team'        => __( 'Equipa', 'adam-comunidade' ),
			'partner'     => __( 'Parceiro', 'adam-comunidade' ),
			'institution' => __( 'Instituição', 'adam-comunidade' ),
			'brand'       => __( 'Marca', 'adam-comunidade' ),
		)[ $type ];
	}

	private static function name( string $name, string $fallback, int $limit = 42 ): string {
		$name = trim( wp_strip_all_tags( $name ) );
		if ( '' === $name ) {
			return $fallback;
		}
		return function_exists( 'mb_strimwidth' ) ? mb_strimwidth( $name, 0, $limit, '…', 'UTF-8' ) : substr( $name, 0, $limit );
	}

	private static function initials( string $name ): string {
		$words    = preg_split( '/\s+/u', trim( $name ) ) ?: array();
		$initials = '';
		foreach ( array_slice( array_filter( $words ), 0, 2 ) as $word ) {
			$initials .= function_exists( 'mb_substr' ) ? mb_substr( $word, 0, 1, 'UTF-8' ) : substr( $word, 0, 1 );
		}
		return strtoupper( '' !== $initials ? $initials : 'AD' );
	}
}
