<?php
defined( 'ABSPATH' ) || exit;

// ── メンテナンスモード ────────────────────────────────────────────────────────
// 公開/非公開は WordPress 管理画面「工具レンタル → 設定 → サイト公開状態」で切り替えます。
// （旧 KOGU_MAINTENANCE 定数による制御は廃止し、DBオプションで制御）
add_action( 'template_redirect', function() {
	if ( ! (int) get_option( 'kogu_maintenance_mode', 0 ) ) return;
	if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) return;
	http_response_code( 503 );
	header( 'Retry-After: 86400' );
	?><!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>準備中｜みんなの工具レンタル</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f5f0eb; font-family: 'Noto Sans JP', sans-serif; }
  .wrap { text-align: center; padding: 40px 24px; }
  .icon { font-size: 56px; margin-bottom: 24px; }
  h1 { font-size: 22px; font-weight: 700; color: #1f1d1a; margin-bottom: 16px; }
  p { font-size: 15px; color: #6b6560; line-height: 1.8; }
  .brand { margin-top: 40px; font-size: 13px; color: #9e9890; }
</style>
</head>
<body>
  <div class="wrap">
    <div class="icon">🔧</div>
    <h1>ただいま準備中です</h1>
    <p>サービス開始に向けて準備を進めています。<br>もうしばらくお待ちください。</p>
    <p class="brand">みんなの工具レンタル</p>
  </div>
</body>
</html><?php
	exit;
} );

if ( ! function_exists( 'minna_kogu_setup' ) ) {
	function minna_kogu_setup() {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'html5', [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ] );
		add_theme_support( 'automatic-feed-links' );
		register_nav_menus( [
			'primary' => 'ヘッダーメニュー',
		] );
		// WordPressコア標準のcanonical出力を無効化（minna_kogu_seo_head()で独自出力するため、重複を防ぐ）
		remove_action( 'wp_head', 'rel_canonical' );
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

// ── SEO ───────────────────────────────────────────────────────────────────────

/** ページごとの <title> タグをカスタマイズ */
add_filter( 'pre_get_document_title', 'minna_kogu_document_title' );
function minna_kogu_document_title( $title ) {
	if ( is_front_page() ) {
		return 'みんなの工具レンタル｜インパクトドライバーを1週間¥4,900から・3,000円以上送料無料';
	}
	if ( is_page( 'rental' ) ) {
		return '工具をレンタルする｜みんなの工具レンタル';
	}
	return $title;
}

/** Google Search Console 検証タグを出力 */
add_action( 'wp_head', function() {
	$code = get_option( 'kogu_gsc_verification' );
	if ( $code ) {
		echo '<meta name="google-site-verification" content="' . esc_attr( $code ) . '">' . "\n";
	}
}, 1 );

/** Google Tag Manager — <head> スニペット */
add_action( 'wp_head', function() {
	$gtm = get_option( 'kogu_gtm_container_id' );
	if ( ! $gtm ) return;
	$gtm = esc_js( $gtm );
	echo <<<HTML
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','{$gtm}');</script>
<!-- End Google Tag Manager -->
HTML;
}, 2 );

/** Google Tag Manager — <body> 直後のnoscriptスニペット */
add_action( 'wp_body_open', function() {
	$gtm = get_option( 'kogu_gtm_container_id' );
	if ( ! $gtm ) return;
	$gtm = esc_attr( $gtm );
	echo <<<HTML
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={$gtm}"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
HTML;
} );

/** サイトマップから不要なエントリを除外（著者ページ・WooCommerce商品） */
add_filter( 'wp_sitemaps_add_provider', function( $provider, $name ) {
	return in_array( $name, [ 'users' ], true ) ? false : $provider;
}, 10, 2 );

/** noindex 対象ページに robots タグを出力 */
add_action( 'wp_head', function() {
	if ( ! is_page() ) return;
	$slugs = array_map( 'trim', explode( ',', get_option( 'kogu_noindex_slugs', 'tokushoho,my-page,privacy,terms,contact' ) ) );
	global $post;
	if ( in_array( $post->post_name, $slugs, true ) ) {
		echo '<meta name="robots" content="noindex, nofollow">' . "\n";
	}
}, 1 );

/** meta description・OGP・Twitter Card・JSON-LD を出力 */
add_action( 'wp_head', 'minna_kogu_seo_head', 1 );
function minna_kogu_seo_head() {
	$site_name  = 'みんなの工具レンタル';
	$og_image   = get_template_directory_uri() . '/assets/images/top_banner.jpeg';

	if ( is_front_page() ) {
		$desc      = 'インパクトドライバーなど電動工具を1週間¥4,900からレンタル。3,000円以上のご注文で送料無料（北海道・沖縄・離島除く）。2週目以降30%OFF。デポジット不要、返却もゆうパックで送り返すだけ。';
		$canonical = home_url( '/' );
		$og_title  = 'みんなの工具レンタル｜インパクトドライバーを1週間¥4,900から';
		$og_type   = 'website';
		$output_jsonld = true;
	} elseif ( is_page( 'rental' ) ) {
		$desc      = '工具レンタルのお申し込みページ。日程・週数を選んでそのままカード決済。3,000円以上のご注文で送料無料（北海道・沖縄・離島除く）。インパクトドライバーを1週間¥4,900から。';
		$canonical = home_url( '/rental/' );
		$og_title  = '工具をレンタルする｜みんなの工具レンタル';
		$og_type   = 'website';
		$output_jsonld = false;
	} elseif ( is_page() ) {
		global $post;
		$excerpt   = wp_trim_words( strip_tags( get_the_content() ), 55, '…' );
		$desc      = $excerpt ?: ( get_the_title() . '｜' . $site_name );
		$canonical = get_permalink();
		$og_title  = get_the_title() . '｜' . $site_name;
		$og_type   = 'article';
		$output_jsonld = false;
	} elseif ( is_singular() ) {
		// 個別投稿・カスタム投稿タイプ — canonical を各記事の本来のURLに設定
		$excerpt   = wp_trim_words( strip_tags( get_the_excerpt() ?: get_the_content() ), 55, '…' );
		$desc      = $excerpt ?: ( get_the_title() . '｜' . $site_name );
		$canonical = get_permalink();
		$og_title  = get_the_title() . '｜' . $site_name;
		$og_type   = 'article';
		$output_jsonld = false;
	} elseif ( is_home() || is_archive() ) {
		// ブログ一覧・カテゴリー・タグアーカイブ
		if ( is_category() ) {
			$cat_name = single_cat_title( '', false );
			$og_title = $cat_name . '｜' . $site_name;
			$desc     = $cat_name . 'のDIYコラム一覧｜' . $site_name;
		} elseif ( is_tag() ) {
			$tag_name = single_tag_title( '', false );
			$og_title = $tag_name . '｜' . $site_name;
			$desc     = $tag_name . 'のDIYコラム一覧｜' . $site_name;
		} else {
			$og_title = 'DIYコラム｜' . $site_name;
			$desc     = 'DIYと工具に関するコラム一覧｜' . $site_name;
		}
		$canonical = get_pagenum_link();
		$og_type   = 'website';
		$output_jsonld = false;
	} else {
		$desc      = 'インパクトドライバーなど電動工具を1週間¥4,900からレンタル。3,000円以上で送料無料（北海道・沖縄・離島除く）。';
		$canonical = get_pagenum_link();
		$og_title  = $site_name;
		$og_type   = 'website';
		$output_jsonld = false;
	}

	// meta description
	echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
	// canonical
	echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";

	// Open Graph
	echo '<meta property="og:type"        content="' . esc_attr( $og_type ) . '">' . "\n";
	echo '<meta property="og:title"       content="' . esc_attr( $og_title ) . '">' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
	echo '<meta property="og:url"         content="' . esc_url( $canonical ) . '">' . "\n";
	echo '<meta property="og:site_name"   content="' . esc_attr( $site_name ) . '">' . "\n";
	echo '<meta property="og:image"       content="' . esc_url( $og_image ) . '">' . "\n";
	echo '<meta property="og:locale"      content="ja_JP">' . "\n";

	// Twitter Card
	echo '<meta name="twitter:card"        content="summary_large_image">' . "\n";
	echo '<meta name="twitter:title"       content="' . esc_attr( $og_title ) . '">' . "\n";
	echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '">' . "\n";
	echo '<meta name="twitter:image"       content="' . esc_url( $og_image ) . '">' . "\n";

	// JSON-LD（トップページのみ）
	if ( $output_jsonld ) {
		$products    = minna_kogu_get_products();
		$offers      = [];
		foreach ( $products as $p ) {
			$offers[] = [
				'@type'           => 'Offer',
				'name'            => $p->name . ' 1週間レンタル',
				'price'           => (int) $p->price_per_week,
				'priceCurrency'   => 'JPY',
				'description'     => '2週目以降30%OFF。3,000円以上のご注文で送料無料（北海道・沖縄・離島除く）。',
				'seller'          => [ '@type' => 'Organization', 'name' => $site_name ],
			];
		}
		if ( empty( $offers ) ) {
			$offers[] = [
				'@type'         => 'Offer',
				'name'          => 'インパクトドライバー 1週間レンタル',
				'price'         => 4900,
				'priceCurrency' => 'JPY',
				'description'   => '2週目以降30%OFF。3,000円以上のご注文で送料無料（北海道・沖縄・離島除く）。',
			];
		}
		$jsonld = [
			'@context'    => 'https://schema.org',
			'@type'       => 'LocalBusiness',
			'name'        => $site_name,
			'description' => '電動工具のレンタルサービス。インパクトドライバーなどを1週間単位でお届け（北海道・沖縄・離島除く）。3,000円以上のご注文で送料無料。',
			'url'         => home_url( '/' ),
			'image'       => $og_image,
			'areaServed'  => [ '@type' => 'Country', 'name' => 'Japan' ],
			'currenciesAccepted' => 'JPY',
			'paymentAccepted'    => 'Credit Card',
			'hasOfferCatalog'    => [
				'@type'     => 'OfferCatalog',
				'name'      => '工具レンタル料金',
				'itemListElement' => $offers,
			],
		];
		echo '<script type="application/ld+json">' . "\n";
		echo wp_json_encode( $jsonld, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		echo "\n" . '</script>' . "\n";
	}
}

/**
 * 旧URL（日付あり /YYYY/MM/DD/slug/、日付なし /slug/ など）を
 * 現在のパーマリンク構造の正しいURLへ301リダイレクトする。
 * 404になったリクエストの末尾セグメントを投稿スラッグとして解決するため、
 * 今後パーマリンク構造を再変更しても個別に手を入れる必要がない。
 */
add_action( 'template_redirect', function() {
	if ( ! is_404() ) return;
	$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
	if ( '' === $path ) return;
	$segments = explode( '/', $path );
	$slug     = end( $segments );
	$post     = get_page_by_path( $slug, OBJECT, 'post' );
	if ( $post && 'publish' === $post->post_status ) {
		$target = get_permalink( $post );
		if ( $target ) {
			wp_safe_redirect( $target, 301 );
			exit;
		}
	}
} );


// ── Theme helpers ─────────────────────────────────────────────────────────────

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
