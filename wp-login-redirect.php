<?php
/**
 * Plugin Name:       WP Login Redirect
 * Plugin URI:        https://github.com/Azanumer/wp-login-redirect
 * Description:       Redirect users after login by role, with a default fallback, plus a custom logout redirect — no code edits needed.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Azan Umer
 * Author URI:        https://azanumer.cv
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-login-redirect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WLR_VERSION', '1.0.0' );
define( 'WLR_OPTION', 'wlr_settings' );

/**
 * Default settings.
 *
 * @return array
 */
function wlr_default_settings() {
	return array(
		'default_url' => '',
		'logout_url'  => '',
		'role_urls'   => array(), // role slug => URL.
	);
}

/**
 * Get the plugin settings merged over defaults.
 *
 * @return array
 */
function wlr_get_settings() {
	$saved = get_option( WLR_OPTION, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return array_merge( wlr_default_settings(), $saved );
}

/**
 * Normalise a redirect URL: only relative paths or http(s) URLs on this site.
 * Returns '' when the URL is not safe to redirect to.
 *
 * @param string $url Raw URL from settings.
 * @return string
 */
function wlr_safe_url( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}
	// Relative path (e.g. /dashboard/) is always fine.
	if ( '/' === $url[0] && ( ! isset( $url[1] ) || '/' !== $url[1] ) ) {
		return $url;
	}
	// Absolute URL: must be http(s) and on this host.
	$parts = wp_parse_url( $url );
	if ( empty( $parts['scheme'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
		return '';
	}
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	if ( empty( $parts['host'] ) || strtolower( $parts['host'] ) !== strtolower( $host ) ) {
		return '';
	}
	return $url;
}

/**
 * Pick the redirect URL for a user: first matching role (highest role first),
 * then the default, then WordPress's own $redirect_to.
 *
 * @param string  $redirect_to Default redirect WordPress computed.
 * @param string  $requested   URL requested via ?redirect_to= (may be empty).
 * @param WP_User $user        The user who just logged in.
 * @return string
 */
function wlr_login_redirect( $redirect_to, $requested, $user ) {
	if ( ! $user instanceof WP_User ) {
		return $redirect_to;
	}

	$settings = wlr_get_settings();

	// A ?redirect_to= on the login form wins over the default, but a
	// role rule still wins over both — the role rule is the admin's intent.
	if ( ! empty( $settings['role_urls'] ) && is_array( $settings['role_urls'] ) ) {
		foreach ( (array) $user->roles as $role ) {
			if ( ! empty( $settings['role_urls'][ $role ] ) ) {
				$url = wlr_safe_url( $settings['role_urls'][ $role ] );
				if ( '' !== $url ) {
					return apply_filters( 'wlr_redirect_url', $url, $user, $role );
				}
			}
		}
	}

	$default = wlr_safe_url( $settings['default_url'] );
	if ( '' !== $default ) {
		return apply_filters( 'wlr_redirect_url', $default, $user, '' );
	}

	return $redirect_to;
}
add_filter( 'login_redirect', 'wlr_login_redirect', 10, 3 );

/**
 * Redirect after logout when a logout URL is configured.
 */
function wlr_logout_redirect() {
	$settings = wlr_get_settings();
	$url      = wlr_safe_url( $settings['logout_url'] );
	if ( '' !== $url ) {
		wp_safe_redirect( $url );
		exit;
	}
}
add_action( 'wp_logout', 'wlr_logout_redirect' );

/**
 * Register the settings page under Settings.
 */
function wlr_register_settings_page() {
	add_options_page(
		__( 'Login Redirect', 'wp-login-redirect' ),
		__( 'Login Redirect', 'wp-login-redirect' ),
		'manage_options',
		'wp-login-redirect',
		'wlr_render_settings_page'
	);
}
add_action( 'admin_menu', 'wlr_register_settings_page' );

/**
 * Register the settings with the Settings API.
 */
function wlr_register_settings() {
	register_setting(
		'wlr_settings_group',
		WLR_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'wlr_sanitize_settings',
			'default'           => wlr_default_settings(),
		)
	);
}
add_action( 'admin_init', 'wlr_register_settings' );

/**
 * Sanitize settings input. Role URLs are keyed by real role slugs only.
 *
 * @param array $input Raw submitted values.
 * @return array
 */
function wlr_sanitize_settings( $input ) {
	$clean = wlr_default_settings();

	if ( ! is_array( $input ) ) {
		return $clean;
	}

	$clean['default_url'] = isset( $input['default_url'] ) ? esc_url_raw( trim( (string) $input['default_url'] ) ) : '';
	$clean['logout_url']  = isset( $input['logout_url'] ) ? esc_url_raw( trim( (string) $input['logout_url'] ) ) : '';

	if ( ! empty( $input['role_urls'] ) && is_array( $input['role_urls'] ) ) {
		$roles = wp_roles()->get_names();
		foreach ( $input['role_urls'] as $role => $url ) {
			$role = sanitize_key( $role );
			if ( isset( $roles[ $role ] ) ) {
				$url = esc_url_raw( trim( (string) $url ) );
				if ( '' !== $url ) {
					$clean['role_urls'][ $role ] = $url;
				}
			}
		}
	}

	return $clean;
}

/**
 * Render the settings page.
 */
function wlr_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings = wlr_get_settings();
	$roles    = wp_roles()->get_names();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Login Redirect', 'wp-login-redirect' ); ?></h1>
		<p>
			<?php esc_html_e( 'Send users somewhere useful after they log in — a different page per role, or one default for everyone. Leave a field empty to fall back to WordPress\'s default behaviour. Only pages on this site are allowed.', 'wp-login-redirect' ); ?>
		</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'wlr_settings_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="wlr-default-url"><?php esc_html_e( 'Default redirect', 'wp-login-redirect' ); ?></label>
					</th>
					<td>
						<input type="url" id="wlr-default-url" name="<?php echo esc_attr( WLR_OPTION ); ?>[default_url]" value="<?php echo esc_attr( $settings['default_url'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( home_url( '/dashboard/' ) ); ?>">
						<p class="description"><?php esc_html_e( 'Used when no role rule matches. Example: /my-account/ or a full URL on this site.', 'wp-login-redirect' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wlr-logout-url"><?php esc_html_e( 'After logout', 'wp-login-redirect' ); ?></label>
					</th>
					<td>
						<input type="url" id="wlr-logout-url" name="<?php echo esc_attr( WLR_OPTION ); ?>[logout_url]" value="<?php echo esc_attr( $settings['logout_url'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>">
						<p class="description"><?php esc_html_e( 'Where to send users after they log out. Empty keeps the default login screen.', 'wp-login-redirect' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Redirect by role', 'wp-login-redirect' ); ?></h2>
			<p><?php esc_html_e( 'A user with several roles takes the first matching rule in the order WordPress lists their roles.', 'wp-login-redirect' ); ?></p>
			<table class="form-table" role="presentation">
				<?php foreach ( $roles as $slug => $label ) : ?>
					<tr>
						<th scope="row">
							<label for="wlr-role-<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( translate_user_role( $label ) ); ?></label>
						</th>
						<td>
							<input type="url" id="wlr-role-<?php echo esc_attr( $slug ); ?>" name="<?php echo esc_attr( WLR_OPTION ); ?>[role_urls][<?php echo esc_attr( $slug ); ?>]" value="<?php echo esc_attr( isset( $settings['role_urls'][ $slug ] ) ? $settings['role_urls'][ $slug ] : '' ); ?>" class="regular-text" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>">
						</td>
					</tr>
				<?php endforeach; ?>
			</table>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
