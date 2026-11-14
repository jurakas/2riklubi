<?php
/**
 * Local mail: route wp_mail() to an SMTP catcher (Mailpit) when AK_SMTP_HOST is defined in wp-config.php.
 *
 * @package ariklubi-core
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'AK_SMTP_HOST' ) && AK_SMTP_HOST ) {
	/**
	 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer Mailer instance.
	 */
	function ak_phpmailer_smtp( $phpmailer ): void {
		$phpmailer->isSMTP();
		$phpmailer->Host        = (string) AK_SMTP_HOST;
		$phpmailer->Port        = defined( 'AK_SMTP_PORT' ) && AK_SMTP_PORT ? (int) AK_SMTP_PORT : 1025;
		$phpmailer->SMTPAuth    = false;
		$phpmailer->SMTPSecure  = '';
		$phpmailer->SMTPAutoTLS = false;
	}
	add_action( 'phpmailer_init', 'ak_phpmailer_smtp' );
}

if ( defined( 'AK_MAIL_FROM' ) && is_email( (string) AK_MAIL_FROM ) ) {
	add_filter( 'wp_mail_from', static fn() => (string) AK_MAIL_FROM );
}
