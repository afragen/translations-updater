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
 * Class Bitbucket_API
 *
 * Get remote data from a Bitbucket repo.
 *
 * @package Fragen\Translations_Updater
 * @author  Andy Fragen
 */
class Bitbucket_API extends API {

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
	 * Language Packs cannot reside on Bitbucket Server.
	 *
	 * @param array $headers Array of headers of Language Pack.
	 *
	 * @return bool When invalid response.
	 */
	public function get_language_pack( $headers ) {
		$response = ! empty( $this->response['languages'] ) ? $this->response['languages'] : false;
		$type     = explode( '_', $this->type->type );

		if ( ! $response ) {
			$response = $this->api( '/1.0/repositories/' . $headers['owner'] . '/' . $headers['repo'] . '/src/master/language-pack.json' );

			if ( $this->validate_response( $response ) ) {
				return false;
			}

			if ( $response ) {
				$response = json_decode( $response->data );

				foreach ( $response as $locale ) {
					$package = array( 'https://bitbucket.org', $headers['owner'], $headers['repo'], 'raw/master' );
					$package = implode( '/', $package ) . $locale->package;

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
	 * Added due to abstract class designation, not used for Bitbucket.
	 *
	 * @param $git
	 * @param $endpoint
	 */
	protected function add_endpoints( $git, $endpoint ) {
	}

}
