<?php defined( 'ABSPATH' ) || exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-nav">
	<div class="container">
		<nav class="nav-inner">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="nav-logo">
				<span class="mark">🔧</span>
				みんなの工具レンタル
			</a>

			<?php
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu( [
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'nav-links',
					'menu_id'        => 'primary-menu',
				] );
			} else {
				?>
				<ul class="nav-links" id="primary-menu">
					<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">工具レンタルTOP</a></li>
					<li><a href="<?php echo esc_url( home_url( '/rental' ) ); ?>">工具を借りる</a></li>
					<li><a href="<?php echo esc_url( home_url( '/?post_type=post' ) ); ?>">DIYコラム</a></li>
					<li><a href="<?php echo esc_url( home_url( '/my-page' ) ); ?>">予約確認・返却</a></li>
					<li class="nav-links-mobile-only"><a href="<?php echo esc_url( home_url( '/terms' ) ); ?>">利用規約</a></li>
					<li class="nav-links-mobile-only"><a href="<?php echo esc_url( home_url( '/tokushoho' ) ); ?>">特商法表記</a></li>
				</ul>
				<?php
			}
			?>

			<div class="nav-actions">
				<a class="nav-cta" href="<?php echo esc_url( home_url( '/rental' ) ); ?>">
					🔧 レンタルする
				</a>
				<button class="nav-menu-btn" aria-label="メニューを開く" aria-expanded="false">
					<span></span><span></span><span></span>
				</button>
			</div>
		</nav>
	</div>
</header>

<script>
( function () {
	var btn = document.querySelector( '.nav-menu-btn' );
	var menu = document.getElementById( 'primary-menu' );
	if ( btn && menu ) {
		btn.addEventListener( 'click', function () {
			var open = menu.classList.toggle( 'open' );
			btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		} );
	}
} )();
</script>
