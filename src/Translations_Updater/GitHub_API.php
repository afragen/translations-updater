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
 * Class GitHub_API
 *
 * Get remote data from a GitHub repo.
 *
 * @package Fragen\Translations_Updater
 * @author  Andy Fragen
 */
class GitHub_API extends API {

	/**
	 * Holds loose class method name.
	 *
	 * @var null
	 */
	private static $method = null;

	/**
	 * Constructor.
	 *
	 * @param object $type
	 */
	public function __construct( $type ) {
		$this->type     = $type;
		$this->response = $this->get_repo_cache();
	}


	/**
	 * Get/process Language Packs.
	 * Language Packs cannot reside on GitHub Enterprise.
	 *
	 * @param array $headers Array of headers of Language Pack.
	 *
	 * @return bool When invalid response.
	 */
	public function get_language_pack( $headers ) {
		$response = ! empty( $this->response['languages'] ) ? $this->response['languages'] : false;
		$type     = explode( '_', $this->type->type );

		if ( ! $response ) {
			self::$method = 'translation';
			$response     = $this->api( '/repos/' . $headers['owner'] . '/' . $headers['repo'] . '/contents/language-pack.json' );

			if ( $this->validate_response( $response ) ) {
				return false;
			}

			if ( $response ) {
				$contents = base64_decode( $response->content );
				$response = json_decode( $contents );

				foreach ( $response as $locale ) {
					$package = array( 'https://github.com', $headers['owner'], $headers['repo'], 'blob/master' );
					$package = implode( '/', $package ) . $locale->package;
					$package = add_query_arg( array( 'raw' => 'true' ), $package );

					$response->{$locale->language}->package = $package;
					$response->{$locale->language}->type    = $type[1];
					$response->{$locale->language}->version = $this->type->local_version;
				}

				$this->set_repo_cache( 'languages', $response );
			}
		}
		$this->type->language_packs = $response;
	}

	/**
	 * Add appropriate access token to endpoint.
	 *
	 * @param $git
	 * @param $endpoint
	 *
	 * @access private
	 *
	 * @return string
	 */
	private function add_access_token_endpoint( $git, $endpoint ) {
		// This will return if checking during shiny updates.
		if ( ! isset( parent::$options ) ) {
			return $endpoint;
		}

		// Add GitHub.com access token.
		if ( ! empty( parent::$options['github_access_token'] ) ) {
			$endpoint = add_query_arg( 'access_token', parent::$options['github_access_token'], $endpoint );
		}

		// Add repo access token.
		if ( ! empty( parent::$options[ $git->type->repo ] ) ) {
			$endpoint = remove_query_arg( 'access_token', $endpoint );
			$endpoint = add_query_arg( 'access_token', parent::$options[ $git->type->repo ], $endpoint );
		}

		return $endpoint;
	}

	/**
	 * Create GitHub API endpoints.
	 *
	 * @param $git      object
	 * @param $endpoint string
	 *
	 * @return string $endpoint
	 */
	protected function add_endpoints( $git, $endpoint ) {
		switch ( self::$method ) {
			case 'translation':
				break;
			default:
				break;
		}

		$endpoint = $this->add_access_token_endpoint( $git, $endpoint );

		return $endpoint;
	}

}
