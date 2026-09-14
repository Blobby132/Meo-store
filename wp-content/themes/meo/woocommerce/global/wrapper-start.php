<?php
/**
 * Content wrapper opening markup for WooCommerce pages.
 *
 * Overrides woocommerce/templates/global/wrapper-start.php.
 *
 * @package MEO
 * @version 3.3.0
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="meo-wrap meo-section meo-shop">
	<?php woocommerce_breadcrumb(); ?>
