<?php
/**
 * Translations Updater
 *
 * @package   Fragen\Translations_Updater
 * @author    Andy Fragen
 * @license   MIT
 * @link      https://github.com/afragen/translations-updater
 */

namespace Fragen\Translations_Updater;

/**
 * Class Init
 *
 * @package Fragen\Translations_Updater
 */
class Init {
	use Base;

	/**
	 * Variable to hold boolean to check user privileges.
	 *
	 * @var bool
	 */
	protected static $can_user_update;


	/**
	 * Let's get going.
	 */
	public function run() {
		$this->load_hooks();
	}

	/**
	 * Load relevant action/filter hooks.
	 * Use 'init' hook for user capabilities.
	 */
	protected function load_hooks() {
		add_action( 'init', array( &$this, 'load' ) );
		add_action( 'init', array( &$this, 'background_update' ) );

		add_filter( 'extra_theme_headers', array( &$this, 'add_headers' ) );
		add_filter( 'extra_plugin_headers', array( &$this, 'add_headers' ) );
	}

	/**
	 * Instantiate Plugin and Theme for proper user capabilities.
	 *
	 * @return bool
	 */
	public function can_update() {
		global $pagenow;

		$load_multisite        = ( is_network_admin() && current_user_can( 'manage_network' ) );
		$load_single_site      = ( ! is_multisite() && current_user_can( 'manage_options' ) );
		self::$can_user_update = $load_multisite || $load_single_site;

		$admin_pages = array(
			'plugins.php',
			'themes.php',
			'update-core.php',
			'update.php',
		);

		return self::$can_user_update && in_array( $pagenow, array_unique( $admin_pages ), true );
	}

}
