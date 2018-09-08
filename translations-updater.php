<?php
/**
 * Translations Updater
 *
 * @package   Fragen\Translations_Updater
 * @author    Andy Fragen
 * @license   MIT
 * @link      https://github.com/afragen/edd-translations-updater
 */

/**
 * Plugin Name:       Translations Updater
 * Plugin URI:        https://github.com/afragen/translations-updater
 * Description:       An EDD Software Licensing extension to automatically update language packs.
 * Version:           1.2.1
 * Author:            Andy Fragen
 * License:           MIT
 * License URI:       http://www.opensource.org/licenses/MIT
 * Network:           true
 * GitHub Plugin URI: https://github.com/afragen/translations-updater
 * Requires WP:       4.6
 * Requires PHP:      5.6
 */

/*
 * Exit if called directly.
 * PHP version check and exit.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( version_compare( '5.6.0', PHP_VERSION, '>=' ) ) {
	echo '<div class="error notice is-dismissible"><p>';
	printf(
		/* translators: 1: minimum PHP version required, 2: Upgrade PHP URL */
		wp_kses_post( __( 'Translations Updater cannot run on PHP versions older than %1$s. <a href="%2$s">Learn about upgrading your PHP.</a>', 'translations-updater' ) ),
		'5.6.0',
		esc_url( __( 'https://wordpress.org/support/upgrade-php/' ) )
	);
	echo '</p></div>';

	return false;
}

// Plugin namespace root.
$translations_updater['root'] = [ 'Fragen\\Translations_Updater' => __DIR__ . '/src/Translations_Updater' ];

// Add extra classes.
$translations_updater['extra_classes'] = [];

// TODO: convert to using Composer autoload.
require_once __DIR__ . '/src/Autoloader.php';
( new \Fragen\Autoloader( $translations_updater['root'], $translations_updater['extra_classes'] ) );

( new \Fragen\Translations_Updater\Init() )->edd_run();


add_action(
	'admin_init', function() {
		$tu_config = [
			'git'       => 'github',
			'type'      => 'plugin',
			'slug'      => 'translations-updater',
			'version'   => '1.2.1',
			'languages' => 'https://github.com/afragen/github-updater-translations',
		];
		( new \Fragen\Translations_Updater\Init() )->run( $tu_config );
	}
);
