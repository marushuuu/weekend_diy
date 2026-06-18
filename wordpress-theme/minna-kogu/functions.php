<?php
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'minna_kogu_setup' ) ) {
	function minna_kogu_setup() {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'html5', [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ] );
		add_theme_support( 'automatic-feed-links' );
		register_nav_menus( [
			'primary' => 'ヘッダーメニュー',
		] );
	}
}
add_action( 'after_setup_theme', 'minna_kogu_setup' );

function minna_kogu_assets() {
	wp_enqueue_style(
		'minna-kogu-fonts',
		'https://fonts.googleapis.com/css2?family=Klee+One:wght@400;600&family=Noto+Sans+JP:wght@400;600;700&display=swap',
		[],
		null
	);
	wp_enqueue_style(
		'minna-kogu-style',
		get_stylesheet_uri(),
		[ 'minna-kogu-fonts' ],
		wp_get_theme()->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', 'minna_kogu_assets' );

/**
 * プラグインの有効商品を取得（プラグイン未有効時は空配列）。
 */
function minna_kogu_get_products() {
	if ( ! class_exists( 'Kogu_Database' ) ) {
		return [];
	}
	$products = Kogu_Database::get_active_products();
	return is_array( $products ) ? $products : [];
}

/**
 * 商品名から同梱画像を推定。該当なしはプレースホルダ。
 */
function minna_kogu_product_image_url( $name ) {
	$base = get_template_directory_uri() . '/assets/images/';
	if ( mb_strpos( (string) $name, 'インパクト' ) !== false ) {
		return $base . 'product_impact.jpg';
	}
	if ( mb_strpos( (string) $name, 'ビット' ) !== false ) {
		return $base . 'product_bitset.jpg';
	}
	return '';
}

/**
 * 商品カード1枚を出力（クリックで /rental へ）。
 */
function minna_kogu_render_card( $product ) {
	$rental_url = home_url( '/rental' );
	$name       = esc_html( $product->name );
	$desc       = esc_html( $product->description );
	$week_price = (int) $product->price_per_week;
	$img        = minna_kogu_product_image_url( $product->name );
	?>
	<a class="tool-card" href="<?php echo esc_url( $rental_url ); ?>">
		<div class="tool-card-img">
			<?php if ( $img ) : ?>
				<img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $product->name ); ?>" loading="lazy" />
			<?php else : ?>
				<div class="tool-card-placeholder"><?php echo $name; ?></div>
			<?php endif; ?>
		</div>
		<div class="tool-card-body">
			<h3 class="tool-card-name"><?php echo $name; ?></h3>
			<?php if ( $desc ) : ?>
				<p class="tool-card-desc"><?php echo $desc; ?></p>
			<?php endif; ?>
			<div class="tool-card-price">
				<span class="day">&yen;<?php echo number_format( $week_price ); ?></span>
				<span class="day-unit">/週</span>
				<span class="week">· 2週目〜30%OFF</span>
			</div>
			<span class="add-btn">レンタルする →</span>
		</div>
	</a>
	<?php
}
