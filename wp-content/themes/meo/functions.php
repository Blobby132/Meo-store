<?php
/**
 * MEO theme bootstrap.
 *
 * Kept deliberately thin: every concern lives in its own file under inc/.
 *
 * @package MEO
 */

defined( 'ABSPATH' ) || exit;

define( 'MEO_VERSION', '0.1.0' );
define( 'MEO_DIR', get_template_directory() );
define( 'MEO_URI', get_template_directory_uri() );

require_once MEO_DIR . '/inc/setup.php';
require_once MEO_DIR . '/inc/enqueue.php';
require_once MEO_DIR . '/inc/template-tags.php';
require_once MEO_DIR . '/inc/woocommerce.php';
