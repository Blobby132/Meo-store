<?php
/**
 * Plugin Name: MEO Dropship Bridge (loader)
 * Description: Loads the MEO supplier-integration bridge. WordPress only auto-loads PHP files in the ROOT of mu-plugins, never in subdirectories — this file is that entry point, and the implementation lives in meo-dropship-bridge/.
 * Version: 0.1.0
 * Requires PHP: 8.0
 *
 * @package MEO\Dropship
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/meo-dropship-bridge/bootstrap.php';
