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
 * Class Base
 *
 * Update WordPress language packs from a git hosted repo.
 *
 * @package Fragen\Translations_Updater
 * @author  Andy Fragen
 */
class Base {

	/**
	 * Store details of all repositories that are installed.
	 *
	 * @var object $config
	 */
	protected $config;

	/**
	 * Class Object for API.
	 *
	 * @var object $repo_api
	 */
	protected $repo_api;

	/**
	 * Class Object for Language Packs.
	 *
	 * @var object $languages
	 */
	protected $languages;

	/**
	 * Variable for setting update transient hours.
	 *
	 * @var integer $hours
	 */
	protected static $hours;

	/**
	 * Variable for holding extra theme and plugin headers.
	 *
	 * @var array $extra_headers
	 */
	protected static $extra_headers = array();

	/**
	 * Holds git server types.
	 *
	 * @var array $git_servers
	 */
	protected static $git_servers = array(
		'github'    => 'GitHub',
		'bitbucket' => 'Bitbucket',
		'gitlab'    => 'GitLab',
	);

	/**
	 * Holds extra repo header types.
	 *
	 * @var array $extra_repo_headers
	 */
	protected static $extra_repo_headers = array(
		'languages' => 'Languages',
	);

	/**
	 * Variable to hold boolean to load remote meta.
	 * Checks user privileges and when to load.
	 *
	 * @var bool $load_repo_meta
	 */
	protected static $load_repo_meta;

	/**
	 * Constructor.
	 * Loads options to private static variable.
	 */
	public function __construct() {
		if ( isset( $_GET['force-check'] ) && ! class_exists( 'Fragen\\GitHub_Updater\\Base' ) ) {
			$this->delete_all_cached_data();
		}

		$this->load_hooks();
	}

	/**
	 * Load relevant action/filter hooks.
	 * Use 'init' hook for user capabilities.
	 */
	protected function load_hooks() {
		add_action( 'init', array( &$this, 'init' ) );
		add_action( 'init', array( &$this, 'background_update' ) );

		add_filter( 'extra_theme_headers', array( &$this, 'add_headers' ) );
		add_filter( 'extra_plugin_headers', array( &$this, 'add_headers' ) );
	}

	/**
	 * Remove hooks after use.
	 */
	public function remove_hooks() {
		remove_filter( 'extra_theme_headers', array( &$this, 'add_headers' ) );
		remove_filter( 'extra_plugin_headers', array( &$this, 'add_headers' ) );
		remove_filter( 'http_request_args', array( 'Fragen\\Translations_Updater\\API', 'http_request_args' ) );
	}

	/**
	 * Instantiate Plugin and Theme for proper user capabilities.
	 *
	 * @return bool
	 */
	public function init() {
		global $pagenow;

		$load_multisite       = ( is_network_admin() && current_user_can( 'manage_network' ) );
		$load_single_site     = ( ! is_multisite() && current_user_can( 'manage_options' ) );
		self::$load_repo_meta = $load_multisite || $load_single_site;

		// Set $force_meta_update = true on appropriate admin pages.
		$force_meta_update = false;
		$admin_pages       = array(
			'plugins.php',
			'themes.php',
			'update-core.php',
			'update.php',
		);

		if ( in_array( $pagenow, array_unique( $admin_pages ), true ) ) {
			$force_meta_update = true;
		}

		if ( $force_meta_update ) {
			$this->forced_meta_update_plugins();
		}
		if ( $force_meta_update ) {
			$this->forced_meta_update_themes();
		}

		return true;
	}

	/**
	 * Piggyback on built-in update function to get metadata.
	 */
	public function background_update() {
		add_action( 'wp_update_plugins', array( &$this, 'forced_meta_update_plugins' ) );
		add_action( 'wp_update_themes', array( &$this, 'forced_meta_update_themes' ) );
	}

	/**
	 * Performs actual plugin metadata fetching.
	 */
	public function forced_meta_update_plugins() {
		if ( self::$load_repo_meta ) {
			Plugin::instance()->get_remote_plugin_meta();
		}
	}

	/**
	 * Performs actual theme metadata fetching.
	 */
	public function forced_meta_update_themes() {
		if ( self::$load_repo_meta ) {
			Theme::instance()->get_remote_theme_meta();
		}
	}

	/**
	 * Add extra headers to get_plugins() or wp_get_themes().
	 *
	 * @param $extra_headers
	 *
	 * @return array
	 */
	public function add_headers( $extra_headers ) {
		$ghu_extra_headers = array();

		foreach ( self::$git_servers as $server ) {
			foreach ( self::$extra_repo_headers as $header ) {
				$ghu_extra_headers[ $server . ' ' . $header ] = $server . ' ' . $header;
			}
		}

		self::$extra_headers = array_unique( array_merge( self::$extra_headers, $ghu_extra_headers ) );
		$extra_headers       = array_merge( (array) $extra_headers, (array) $ghu_extra_headers );
		ksort( self::$extra_headers );

		return $extra_headers;
	}

	/**
	 * Get remote repo meta data for language-pack.json file.
	 * Calls remote APIs for data.
	 *
	 * @param $repo
	 *
	 * @return bool
	 */
	public function get_remote_repo_meta( $repo ) {
		self::$hours         = 6 + mt_rand( 0, 12 );
		$this->{$repo->type} = $repo;
		$this->languages     = new Language_Pack( $repo, new Language_Pack_API( $repo ) );

		$this->remove_hooks();

		return true;
	}

	/**
	 * Parse URI param returning array of parts.
	 *
	 * @param $repo_header
	 *
	 * @return array
	 */
	protected function parse_header_uri( $repo_header ) {
		$header_parts     = parse_url( $repo_header );
		$header['scheme'] = isset( $header_parts['scheme'] ) ? $header_parts['scheme'] : null;
		$header['host']   = isset( $header_parts['host'] ) ? $header_parts['host'] : null;
		$owner_repo       = trim( $header_parts['path'], '/' );  // strip surrounding slashes
		$owner_repo       = str_replace( '.git', '', $owner_repo ); //strip incorrect URI ending
		$header['path']   = $owner_repo;
		list( $header['owner'], $header['repo'] ) = explode( '/', $owner_repo );
		$header['owner_repo'] = isset( $header['owner'] ) ? $header['owner'] . '/' . $header['repo'] : null;
		$header['base_uri']   = str_replace( $header_parts['path'], '', $repo_header );
		$header['uri']        = isset( $header['scheme'] ) ? trim( $repo_header, '/' ) : null;

		$header = Base::sanitize( $header );

		return $header;
	}

	/**
	 * Create repo parts.
	 *
	 * @param $repo
	 * @param $type
	 *
	 * @return mixed
	 */
	protected function get_repo_parts( $repo, $type ) {
		$arr['bool']    = false;
		$pattern        = '/' . strtolower( $repo ) . '_/';
		$type           = preg_replace( $pattern, '', $type );
		$repo_types     = array(
			'GitHub'    => 'github_' . $type,
			'Bitbucket' => 'bitbucket_' . $type,
			'GitLab'    => 'gitlab_' . $type,
		);
		$repo_base_uris = array(
			'GitHub'    => 'https://github.com/',
			'Bitbucket' => 'https://bitbucket.org/',
			'GitLab'    => 'https://gitlab.com/',
		);

		if ( array_key_exists( $repo, $repo_types ) ) {
			$arr['type']       = $repo_types[ $repo ];
			$arr['git_server'] = strtolower( $repo );
			$arr['base_uri']   = $repo_base_uris[ $repo ];
			$arr['bool']       = true;
			foreach ( self::$extra_repo_headers as $key => $value ) {
				$arr[ $key ] = $repo . ' ' . $value;
			}
		}

		return $arr;
	}

	/**
	 * Delete all `ghu-` prefixed data from options table.
	 *
	 * @return bool
	 */
	public function delete_all_cached_data() {
		global $wpdb;

		$table         = is_multisite() ? $wpdb->base_prefix . 'sitemeta' : $wpdb->base_prefix . 'options';
		$column        = is_multisite() ? 'meta_key' : 'option_name';
		$delete_string = 'DELETE FROM ' . $table . ' WHERE ' . $column . ' LIKE %s LIMIT 1000';

		$wpdb->query( $wpdb->prepare( $delete_string, array( '%ghu-%' ) ) );

		return true;
	}

	/**
	 * Parse Languages headers for plugins and themes.
	 *
	 * @param array           $header
	 * @param array|\WP_Theme $headers
	 * @param array           $header_parts
	 * @param array           $repo_parts
	 *
	 * @return array $header
	 */
	protected function parse_extra_headers( $header, $headers, $header_parts, $repo_parts ) {
		$theme = null;

		$header['languages'] = null;


		if ( $headers instanceof \WP_Theme ) {
			$theme   = $headers;
			$headers = array();
		}

		$self_hosted_parts = array_diff( array_keys( self::$extra_repo_headers ), array( 'branch' ) );
		foreach ( $self_hosted_parts as $part ) {
			if ( $theme instanceof \WP_Theme ) {
				$headers[ $repo_parts[ $part ] ] = $theme->get( $repo_parts[ $part ] );
			}
			if ( array_key_exists( $repo_parts[ $part ], $headers ) &&
			     ! empty( $headers[ $repo_parts[ $part ] ] )
			) {
				switch ( $part ) {
					case 'languages':
						$header['languages'] = $headers[ $repo_parts[ $part ] ];
						break;
					default:
						break;
				}
			}
		}

		return $header;
	}

	/**
	 * Sanitize each setting field as needed.
	 *
	 * @param array $input Contains all settings fields as array keys
	 *
	 * @return array
	 */
	public static function sanitize( $input ) {
		$new_input = array();
		foreach ( array_keys( (array) $input ) as $id ) {
			$new_input[ sanitize_file_name( $id ) ] = sanitize_text_field( $input[ $id ] );
		}

		return $new_input;
	}

}
