<?php
/**
 * Translations Updater
 *
 * @package   Fragen\Translations_Updater
 * @author    Andy Fragen
 * @license   MIT
 * @link      https://github.com/afragen/translations-updater
 */

/**
 * Plugin Name:       Translations Updater
 * Plugin URI:        https://github.com/afragen/translations-updater
 * Description:       A plugin to automatically update GitHub, Bitbucket, or GitLab hosted language packs.
 * Version:           2.0.0
 * Author:            Andy Fragen
 * License:           MIT
 * License URI:       http://www.opensource.org/licenses/MIT
 * Domain Path:       /languages
 * Text Domain:       translations-updater
 * Network:           true
 * GitHub Plugin URI: https://github.com/afragen/translations-updater
 * Requires WP:       4.6
 * Requires PHP:      5.4
 */

/*
 * Exit if called directly.
 * PHP version check and exit.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( version_compare( '5.4.0', PHP_VERSION, '>=' ) ) {
	?>
	<div class="error notice is-dismissible">
		<p>
			<?php
			/* translators: %s: version number */
			printf( esc_html__( 'Translations Updater cannot run on PHP versions older than %s. Please contact your hosting provider to update your site.', 'translations-updater' ), '5.4.0' );
			?>
		</p>
	</div>
	<?php

	return false;
}

// Load textdomain.
load_plugin_textdomain( 'translations-updater' );

// Plugin namespace root.
$translations_updater['root'] = array( 'Fragen\\Translations_Updater' => __DIR__ . '/src/Translations_Updater' );

// Add extra classes.
$translations_updater['extra_classes'] = array( 'Fragen\\Singleton' => __DIR__ . '/src/Singleton.php');

// Load Autoloader.
require_once __DIR__ . '/src/Autoloader.php';
$translations_updater['loader'] = 'Fragen\\Autoloader';
new $translations_updater['loader']( $translations_updater['root'], $translations_updater['extra_classes'] );

// Instantiate class Fragen\Translations_Updater.
$translations_updater['instantiate'] = 'Fragen\\Translations_Updater\\Init';
$translations_updater['init']        = new $translations_updater['instantiate'];
$translations_updater['init']->run();
