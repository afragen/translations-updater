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

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Plugin
 *
 * Update a WordPress plugin from a GitHub repo.
 *
 * @package Fragen\Translations_Updater
 * @author  Andy Fragen
 */
class Plugin extends Base {

	/**
	 * Plugin object.
	 *
	 * @var bool|Plugin
	 */
	private static $instance = false;

	/**
	 * Constructor.
	 */
	public function __construct() {

		// Get details of installed plugins.
		$this->config = $this->get_plugin_meta();

		if ( empty( $this->config ) ) {
			return;
		}
	}

	/**
	 * Returns an array of configurations for the known plugins.
	 *
	 * @return array
	 */
	public function get_plugin_configs() {
		return $this->config;
	}

	/**
	 * The Plugin object can be created/obtained via this
	 * method - this prevents unnecessary work in rebuilding the object and
	 * querying to construct a list of categories, etc.
	 *
	 * @return object $instance Plugin
	 */
	public static function instance() {
		if ( false === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Get details of plugins from those that are installed.
	 *
	 * @return array Indexed array of associative arrays of plugin details.
	 */
	protected function get_plugin_meta() {

		// Ensure get_plugins() function is available.
		include_once( ABSPATH . '/wp-admin/includes/plugin.php' );

		$plugins     = get_plugins();
		$git_plugins = array();

		foreach ( (array) $plugins as $plugin => $headers ) {
			$git_plugin = array();

			if ( empty( $headers['GitHub Languages'] ) &&
			     empty( $headers['Bitbucket Languages'] ) &&
			     empty( $headers['GitLab Languages'] )
			) {
				continue;
			}

			foreach ( (array) self::$extra_headers as $value ) {
				$header = null;

				if ( empty( $headers[ $value ] ) || false === stripos( $value, 'Languages' ) ) {
					continue;
				}

				$header_parts = explode( ' ', $value );
				$repo_parts   = $this->get_repo_parts( $header_parts[0], 'plugin' );

				if ( $repo_parts['bool'] ) {
					$header = $this->parse_header_uri( $headers[ $value ] );
					if ( empty( $header ) ) {
						continue;
					}
				}

				$header = $this->parse_extra_headers( $header, $headers, $header_parts, $repo_parts );

				$git_plugin['type']          = $repo_parts['type'];
				$git_plugin['owner']         = $header['owner'];
				$git_plugin['repo']          = dirname( $plugin );
				$git_plugin['slug']          = $plugin;
				$plugin_data                 = get_plugin_data( WP_PLUGIN_DIR . '/' . $git_plugin['slug'] );
				$git_plugin['local_version'] = strtolower( $plugin_data['Version'] );
				$git_plugin['languages']     = ! empty( $header['languages'] ) ? $header['languages'] : null;
			}

			$git_plugins[ $git_plugin['slug'] ] = (object) $git_plugin;
		}

		return $git_plugins;
	}

	/**
	 * Get remote plugin meta to populate $config plugin objects.
	 * Calls to remote APIs to get data.
	 */
	public function get_remote_plugin_meta() {
		foreach ( (array) $this->config as $plugin ) {
			if ( ! $this->get_remote_repo_meta( $plugin ) ) {
				continue;
			}
		}
	}

}
