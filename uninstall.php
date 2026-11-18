<?php
/**
 * Uninstall WP Login Redirect.
 *
 * Removes the plugin's settings. Nothing else is stored.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wlr_settings' );
