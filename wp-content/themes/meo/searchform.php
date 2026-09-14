<?php
/**
 * Search form.
 *
 * @package MEO
 */

defined( 'ABSPATH' ) || exit;
?>
<form role="search" method="get" class="meo-search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="meo-label" for="meo-search-field"><?php esc_html_e( 'Search the catalogue', 'meo' ); ?></label>
	<div class="meo-search__row">
		<input
			type="search"
			id="meo-search-field"
			class="meo-search__input"
			name="s"
			value="<?php echo esc_attr( get_search_query() ); ?>"
			placeholder="<?php esc_attr_e( 'Item name or SKU', 'meo' ); ?>"
		/>
		<button type="submit" class="meo-btn meo-btn--small"><?php esc_html_e( 'Search', 'meo' ); ?></button>
	</div>
</form>
