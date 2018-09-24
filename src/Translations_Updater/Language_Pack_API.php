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
 * Class Language_Pack_API
 *
 * @package Fragen\Translations_Updater
 */
class Language_Pack_API {

	use API;

	/**
	 * Constructor.
	 *
	 * @param \stdClass $config Repo config data.
	 */
	public function __construct( $config ) {
		$this->repo     = $config;
		$this->response = $this->get_repo_cache( $config->slug );
	}

	/**
	 * Get/process Language Packs.
	 *
	 * @param array $headers Array of headers of Language Pack.
	 *
	 * @return bool When invalid response.
	 */
	public function get_language_pack( $headers ) {
		$response = ! empty( $this->response['languages'] ) ? $this->response['languages'] : false;

		if ( ! $response ) {
			$response = $this->get_language_pack_json( $this->repo->git, $headers );

			if ( $response ) {
				foreach ( $response as $locale ) {
					$package = $this->process_language_pack_package( $this->repo->git, $locale, $headers );

					$response->{$locale->language}->package = $package;
					$response->{$locale->language}->type    = $this->repo->type;
					$response->{$locale->language}->version = $this->repo->version;
				}
				$this->set_repo_cache( 'languages', $response, $this->repo->slug );
			} else {
				return false;
			}
		}
		$this->repo->language_packs = $response;

		return $this->repo;
	}

	/**
	 * Get language-pack.json from appropriate host.
	 *
	 * @param string $git ( github|bitbucket|gitlab|gitea )
	 * @param array  $headers
	 *
	 * @return array|bool|mixed|object $response API response object.
	 */
	private function get_language_pack_json( $git, $headers ) {
		switch ( $git ) {
			case 'github':
				$response = $this->api( '/repos/' . $headers['owner'] . '/' . $headers['repo'] . '/contents/language-pack.json' );
				$response = isset( $response->content )
					? json_decode( base64_decode( $response->content ) )
					: null;
				break;
			case 'bitbucket':
				$response = $this->api( '/2.0/repositories/' . $headers['owner'] . '/' . $headers['repo'] . '/src/master/language-pack.json' );
				break;
			case 'gitlab':
				$id       = urlencode( $headers['owner'] . '/' . $headers['repo'] );
				$response = $this->api( '/projects/' . $id . '/repository/files/language-pack.json' );
				$response = isset( $response->content )
					? json_decode( base64_decode( $response->content ) )
					: null;
				break;
			case 'gitea':
				$response = $this->api( '/repos/' . $headers['owner'] . '/' . $headers['repo'] . '/raw/master/language-pack.json' );
				$response = isset( $response->content )
					? json_decode( base64_decode( $response->content ) )
					: null;
				break;
		}

		if ( $this->validate_response( $response ) ) {
			return false;
		}

		return $response;
	}

	/**
	 * Process $package for update transient.
	 *
	 * @param string $git ( github|bitbucket|gitlab|gitea )
	 * @param string $locale
	 * @param array  $headers
	 *
	 * @return array|null|string
	 */
	private function process_language_pack_package( $git, $locale, $headers ) {
		$package = null;
		switch ( $git ) {
			case 'github':
				$package = [ 'https://github.com', $headers['owner'], $headers['repo'], 'blob/master' ];
				$package = implode( '/', $package ) . $locale->package;
				$package = add_query_arg( [ 'raw' => 'true' ], $package );
				break;
			case 'bitbucket':
				$package = [ 'https://bitbucket.org', $headers['owner'], $headers['repo'], 'raw/master' ];
				$package = implode( '/', $package ) . $locale->package;
				break;
			case 'gitlab':
				$package = [ 'https://gitlab.com', $headers['owner'], $headers['repo'], 'raw/master' ];
				$package = implode( '/', $package ) . $locale->package;
				break;
			case 'gitea':
				// TODO: make sure this works
				$package = [ $headers['uri'], 'raw/master' ];
				$package = implode( '/', $package ) . $local->package;
				break;
		}

		return $package;
	}
}
