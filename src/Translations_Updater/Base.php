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
trait Base {
	use API;

	/**
	 * Store details of all repositories that are installed.
	 *
	 * @var \stdClass $config
	 */
	protected $config;

	/**
	 * Class Object for API.
	 *
	 * @var \stdClass $repo_api
	 */
	protected $repo_api;

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
	 * Variable to hold boolean to check user privileges.
	 *
	 * @var bool
	 */
	protected static $can_user_update;


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
	public function load() {
		if ( ! Singleton::get_instance( 'Init' )->can_update() ) {
			return false;
		}

		if ( self::$can_user_update ) {
			$this->forced_meta_update_plugins();
		}
		if ( self::$can_user_update ) {
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
		if ( self::$can_user_update ) {
			Singleton::get_instance( 'Plugin' )->get_remote_plugin_meta();
		}
	}

	/**
	 * Performs actual theme metadata fetching.
	 */
	public function forced_meta_update_themes() {
		if ( self::$can_user_update ) {
			Singleton::get_instance( 'Theme' )->get_remote_theme_meta();
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
		$extra_headers       = array_merge( (array) $extra_headers, $ghu_extra_headers );
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
		$this->{$repo->type} = $repo;
		$language_pack       = new Language_Pack( $repo, new Language_Pack_API( $repo ) );
		$language_pack->run();

		$this->remove_hooks();

		return true;
	}

	/**
	 * Parse URI param returning array of parts.
	 *
	 * @param string $repo_header
	 *
	 * @return array $header
	 */
	protected function parse_header_uri( $repo_header ) {
		$header_parts         = parse_url( $repo_header );
		$header_path          = pathinfo( $header_parts['path'] );
		$header['scheme']     = isset( $header_parts['scheme'] ) ? $header_parts['scheme'] : null;
		$header['host']       = isset( $header_parts['host'] ) ? $header_parts['host'] : null;
		$header['owner']      = trim( $header_path['dirname'], '/' );
		$header['repo']       = $header_path['filename'];
		$header['owner_repo'] = implode( '/', array( $header['owner'], $header['repo'] ) );
		$header['base_uri']   = str_replace( $header_parts['path'], '', $repo_header );
		$header['uri']        = isset( $header['scheme'] ) ? trim( $repo_header, '/' ) : null;

		$header = self::sanitize( $header );

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
