<?php
/**
 * @var string $type    success|warning|danger|info
 * @var string $message
 */
defined( 'ABSPATH' ) || exit;

$aflx_icons = [ 'success' => '✓', 'warning' => '!', 'danger' => '!', 'info' => 'i' ];
$aflx_type  = isset( $aflx_icons[ $type ] ) ? $type : 'info';
?>
<div class="aflx-alert aflx-alert--<?php echo esc_attr( $aflx_type ); ?>" role="<?php echo 'danger' === $aflx_type ? 'alert' : 'status'; ?>">
	<span class="aflx-alert__icon" aria-hidden="true"><?php echo esc_html( $aflx_icons[ $aflx_type ] ); ?></span>
	<span><?php echo esc_html( $message ); ?></span>
</div>
