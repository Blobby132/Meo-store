<?php
/**
 * Site header.
 *
 * @package MEO
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link" href="#meo-main"><?php esc_html_e( 'Skip to content', 'meo' ); ?></a>

<header class="meo-header">
	<div class="meo-announce">
		<?php
		printf(
			/* translators: %s: emphasised shipping threshold. */
			esc_html__( 'Flat $12 tracked shipping across Canada — %s', 'meo' ),
			'<strong>' . esc_html__( 'free over $95 CAD', 'meo' ) . '</strong>'
		);
		?>
	</div>

	<div class="meo-wrap meo-header__inner">
		<a class="meo-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				M<span class="meo-brand__mark">E</span>O
				<span class="meo-brand__tag"><?php esc_html_e( 'Home &amp; desk goods', 'meo' ); ?></span>
			<?php endif; ?>
		</a>

		<nav class="meo-nav" id="meo-nav" aria-label="<?php esc_attr_e( 'Primary', 'meo' ); ?>">
			<?php
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu( array(
					'theme_location' => 'primary',
					'container'      => false,
					'depth'          => 2,
					'fallback_cb'    => false,
				) );
			} else {
				/*
				 * Fallback menu: the four categories plus About. Shown until a
				 * menu is assigned in Appearance > Menus, so a fresh install is
				 * never a bare header. scripts/setup-store.sh assigns the real one.
				 */
				echo '<ul>';
				foreach ( meo_categories() as $cat ) {
					printf(
						'<li><a href="%1$s">%2$s</a></li>',
						esc_url( meo_is_woocommerce_active() ? get_term_link( $cat['slug'], 'product_cat' ) : home_url( '/' ) ),
						esc_html( $cat['name'] )
					);
				}
				printf(
					'<li><a href="%1$s">%2$s</a></li>',
					esc_url( home_url( '/about/' ) ),
					esc_html__( 'About', 'meo' )
				);
				echo '</ul>';
			}
			?>
		</nav>

		<div class="meo-header__actions">
			<button
				type="button"
				class="meo-iconbtn meo-theme-toggle"
				data-meo-theme-toggle
				aria-label="<?php esc_attr_e( 'Switch colour theme', 'meo' ); ?>"
			>
				<span class="meo-theme-toggle__sun" aria-hidden="true"><?php echo meo_icon( 'sun' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static inline SVG. ?></span>
				<span class="meo-theme-toggle__moon" aria-hidden="true"><?php echo meo_icon( 'moon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static inline SVG. ?></span>
			</button>

			<?php if ( meo_is_woocommerce_active() ) : ?>
				<a
					class="meo-iconbtn meo-cart-link"
					href="<?php echo esc_url( wc_get_cart_url() ); ?>"
					aria-label="<?php esc_attr_e( 'View cart', 'meo' ); ?>"
				>
					<?php echo meo_icon( 'cart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static inline SVG. ?>
					<span class="meo-cart-count" data-meo-cart-count><?php echo esc_html( (string) meo_cart_count() ); ?></span>
				</a>
			<?php endif; ?>

			<button
				type="button"
				class="meo-iconbtn meo-menu-toggle"
				data-meo-menu-toggle
				aria-controls="meo-nav"
				aria-expanded="false"
				aria-label="<?php esc_attr_e( 'Menu', 'meo' ); ?>"
			>
				<?php echo meo_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static inline SVG. ?>
			</button>
		</div>
	</div>
</header>

<main id="meo-main" class="meo-main">
