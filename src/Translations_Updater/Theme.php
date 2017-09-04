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
 * Class Theme
 *
 * Update a WordPress theme from a GitHub repo.
 *
 * @package   Fragen\Translations_Updater
 * @author    Andy Fragen
 */
class Theme extends Base {

	/**
	 * Theme object.
	 *
	 * @var bool|Theme
	 */
	private static $instance = false;

	/**
	 * Constructor.
	 */
	public function __construct() {

		// Get details of installed themes.
		$this->config = $this->get_theme_meta();

		if ( null === $this->config ) {
			return;
		}
	}

	/**
	 * The Theme object can be created/obtained via this
	 * method - this prevents unnecessary work in rebuilding the object and
	 * querying to construct a list of categories, etc.
	 *
	 * @return object Theme
	 */
	public static function instance() {
		if ( false === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Returns an array of configurations for the known themes.
	 *
	 * @return array
	 */
	public function get_theme_configs() {
		return $this->config;
	}

	/**
	 * Reads in WP_Theme class of each theme.
	 * Populates variable array.
	 *
	 * @return array Indexed array of associative arrays of theme details.
	 */
	protected function get_theme_meta() {
		$git_themes = array();
		$themes     = wp_get_themes( array( 'errors' => null ) );

		foreach ( (array) $themes as $theme ) {
			$git_theme = array();

			foreach ( (array) self::$extra_headers as $value ) {
				$header   = null;
				$repo_uri = $theme->get( $value );


				if ( empty( $repo_uri ) || false === stripos( $value, 'Languages' ) ) {
					continue;
				}

				$header_parts = explode( ' ', $value );
				$repo_parts   = $this->get_repo_parts( $header_parts[0], 'theme' );

				if ( $repo_parts['bool'] ) {
					$header = $this->parse_header_uri( $repo_uri );
					if ( empty( $header ) ) {
						continue;
					}
				}

				$header = $this->parse_extra_headers( $header, $theme, $header_parts, $repo_parts );

				$git_theme['type']          = $repo_parts['type'];
				$git_theme['uri']           = $header['base_uri'] . '/' . $header['owner_repo'];
				$git_theme['owner']         = $header['owner'];
				$git_theme['repo']          = $theme->stylesheet;
				$git_theme['local_version'] = strtolower( $theme->get( 'Version' ) );
				$git_theme['languages']     = ! empty( $header['languages'] ) ? $header['languages'] : null;

				break;
			}

			// Exit if not git hosted theme.
			if ( empty( $git_theme ) ) {
				continue;
			}

			$git_themes[ $git_theme['repo'] ] = (object) $git_theme;
		}

		return $git_themes;
	}

	/**
	 * Get remote theme meta to populate $config theme objects.
	 * Calls to remote APIs to get data.
	 */
	public function get_remote_theme_meta() {
		foreach ( (array) $this->config as $theme ) {
			if ( ! $this->get_remote_repo_meta( $theme ) ) {
				continue;
			}
		}
	}

}
