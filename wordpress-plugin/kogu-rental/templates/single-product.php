<?php
defined( 'ABSPATH' ) || exit;

$product = $GLOBALS['kogu_current_product'] ?? null;
if ( ! $product ) { wp_redirect( home_url( '/' ) ); exit; }

$img_base      = KOGU_PLUGIN_URL . 'assets/images/';
$gallery_imgs  = [];
if ( ! empty( $product->gallery ) ) {
    foreach ( array_map( 'trim', explode( ',', $product->gallery ) ) as $f ) {
        if ( $f ) $gallery_imgs[] = $img_base . $f;
    }
}
// フォールバック: 商品名から推定
if ( empty( $gallery_imgs ) ) {
    if ( mb_strpos( $product->name, 'インパクト' ) !== false ) {
        $gallery_imgs[] = $img_base . 'product_impact.jpg';
    } elseif ( mb_strpos( $product->name, 'ビット' ) !== false ) {
        $gallery_imgs[] = $img_base . 'product_bitset.jpg';
    }
}

$contents_list = array_filter( array_map( 'trim', explode( "\n", $product->contents ?? '' ) ) );
$week_price    = (int) $product->price_per_week;
$discount_price = (int) round( $week_price * 0.7 );

// スペック表 — "key|value" 形式を [key => value] 配列に変換
$specs_rows = [];
foreach ( array_filter( array_map( 'trim', explode( "\n", $product->specs ?? '' ) ) ) as $line ) {
    $parts = explode( '|', $line, 2 );
    if ( count( $parts ) === 2 ) {
        $specs_rows[] = [ trim( $parts[0] ), trim( $parts[1] ) ];
    }
}
$product_url   = home_url( '/products/' . $product->slug . '/' );
$rental_url    = home_url( '/rental/' );

// パンくず JSON-LD
$breadcrumb_jsonld = [
    '@context'        => 'https://schema.org',
    '@type'           => 'BreadcrumbList',
    'itemListElement' => [
        [ '@type' => 'ListItem', 'position' => 1, 'name' => 'ホーム',      'item' => home_url( '/' ) ],
        [ '@type' => 'ListItem', 'position' => 2, 'name' => '工具を借りる', 'item' => home_url( '/rental/' ) ],
        [ '@type' => 'ListItem', 'position' => 3, 'name' => esc_html( $product->name ), 'item' => $product_url ],
    ],
];

// Product JSON-LD
$product_jsonld = [
    '@context'    => 'https://schema.org',
    '@type'       => 'Product',
    'name'        => $product->name,
    'description' => $product->description,
    'image'       => $gallery_imgs[0] ?? '',
    'offers'      => [
        '@type'           => 'Offer',
        'price'           => $week_price,
        'priceCurrency'   => 'JPY',
        'priceSpecification' => [
            '@type'            => 'UnitPriceSpecification',
            'price'            => $week_price,
            'priceCurrency'    => 'JPY',
            'unitText'         => '週',
        ],
        'availability'    => 'https://schema.org/InStock',
        'url'             => $rental_url,
        'description'     => '2週目以降30%OFF。3,000円以上のご注文で送料無料（北海道・沖縄・離島除く）。',
    ],
];

add_action( 'wp_head', function() use ( $breadcrumb_jsonld, $product_jsonld, $product, $product_url ) {
    $site_name = 'みんなの工具レンタル';
    $title     = esc_attr( $product->name . 'レンタル｜' . $site_name );
    echo '<meta name="description" content="' . esc_attr( $product->name . 'をレンタル。1週間¥' . number_format( (int)$product->price_per_week ) . 'から。2週目以降30%OFF。3,000円以上送料無料（北海道・沖縄・離島除く）。' ) . '">' . "\n";
    echo '<link rel="canonical" href="' . esc_url( $product_url ) . '">' . "\n";
    echo '<meta property="og:title" content="' . $title . '">' . "\n";
    echo '<meta property="og:type" content="product">' . "\n";
    echo '<meta property="og:url" content="' . esc_url( $product_url ) . '">' . "\n";
    echo '<script type="application/ld+json">' . wp_json_encode( $breadcrumb_jsonld, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . '</script>' . "\n";
    echo '<script type="application/ld+json">' . wp_json_encode( $product_jsonld,    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . '</script>' . "\n";
}, 5 );

// タイトルタグ
add_filter( 'pre_get_document_title', function() use ( $product ) {
    return $product->name . 'レンタル｜みんなの工具レンタル';
} );

get_header();
?>

<div class="container">
  <div class="page-wrap">

    <!-- パンくず -->
    <nav class="kogu-breadcrumb" aria-label="パンくずリスト">
      <ol>
        <li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">ホーム</a></li>
        <li><a href="<?php echo esc_url( $rental_url ); ?>">工具を借りる</a></li>
        <li aria-current="page"><?php echo esc_html( $product->name ); ?></li>
      </ol>
    </nav>

    <div class="kogu-single-product">

      <!-- 左: カルーセル -->
      <div class="kogu-sp-gallery">
        <?php if ( ! empty( $gallery_imgs ) ) : ?>
          <div class="kogu-sp-carousel">
            <div class="kogu-sp-main-img">
              <img id="kogu-sp-img" src="<?php echo esc_url( $gallery_imgs[0] ); ?>"
                   alt="<?php echo esc_attr( $product->name ); ?>" />
              <?php if ( count( $gallery_imgs ) > 1 ) : ?>
                <button class="kogu-sp-arrow kogu-sp-prev" aria-label="前の画像">&#8249;</button>
                <button class="kogu-sp-arrow kogu-sp-next" aria-label="次の画像">&#8250;</button>
              <?php endif; ?>
            </div>
            <?php if ( count( $gallery_imgs ) > 1 ) : ?>
              <div class="kogu-sp-thumbs">
                <?php foreach ( $gallery_imgs as $i => $gimg ) : ?>
                  <button class="kogu-sp-thumb <?php echo $i === 0 ? 'active' : ''; ?>"
                          data-index="<?php echo $i; ?>"
                          aria-label="<?php echo ($i+1); ?>枚目">
                    <img src="<?php echo esc_url( $gimg ); ?>" alt="" />
                  </button>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- 右: 詳細情報 -->
      <div class="kogu-sp-info">
        <h1 class="kogu-sp-title"><?php echo esc_html( $product->name ); ?></h1>
        <p class="kogu-sp-desc"><?php echo esc_html( $product->description ); ?></p>

        <div class="kogu-sp-price-box">
          <div class="kogu-sp-price">
            <span class="kogu-sp-price-label">1週間</span>
            <span class="kogu-sp-price-amount">¥<?php echo number_format( $week_price ); ?></span>
          </div>
          <div class="kogu-sp-price-discount">
            2週目以降 ¥<?php echo number_format( $discount_price ); ?>/週
            <span class="kogu-badge-discount">30%OFF</span>
          </div>
          <p class="kogu-sp-shipping">3,000円以上のご注文で送料無料（北海道・沖縄・離島除く）</p>
        </div>

        <?php if ( ! empty( $contents_list ) ) : ?>
          <div class="kogu-sp-contents">
            <h2>レンタルに含まれるもの</h2>
            <ul>
              <?php foreach ( $contents_list as $item ) : ?>
                <li><?php echo esc_html( $item ); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <a href="<?php echo esc_url( $rental_url ); ?>" class="kogu-btn kogu-btn-primary kogu-sp-cta">
          このアイテムをレンタルする →
        </a>
      </div>

    </div><!-- /.kogu-single-product -->

    <!-- スペック表（全幅） -->
    <?php if ( ! empty( $specs_rows ) ) : ?>
    <div class="kogu-sp-specs-section">
      <h2 class="kogu-sp-section-title">製品スペック</h2>
      <table class="kogu-sp-specs-table">
        <tbody>
          <?php foreach ( $specs_rows as $row ) : ?>
            <tr>
              <th><?php echo esc_html( $row[0] ); ?></th>
              <td><?php echo esc_html( $row[1] ); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- 使用シーン -->
    <div class="kogu-sp-usecase-section">
      <h2 class="kogu-sp-section-title">こんな作業に使えます</h2>
      <div class="kogu-sp-usecase-grid">
        <div class="kogu-sp-usecase-item">
          <span class="kogu-sp-usecase-icon">🪵</span>
          <p>ウッドデッキ・<br>木材の組み立て</p>
        </div>
        <div class="kogu-sp-usecase-item">
          <span class="kogu-sp-usecase-icon">🚗</span>
          <p>カーポート・<br>フェンスの設置</p>
        </div>
        <div class="kogu-sp-usecase-item">
          <span class="kogu-sp-usecase-icon">🏠</span>
          <p>棚・収納の<br>DIY製作</p>
        </div>
        <div class="kogu-sp-usecase-item">
          <span class="kogu-sp-usecase-icon">🔩</span>
          <p>家具の組み立て・<br>解体</p>
        </div>
      </div>
    </div>

    <!-- FAQ -->
    <div class="kogu-sp-faq-section">
      <h2 class="kogu-sp-section-title">よくある質問</h2>
      <div class="kogu-sp-faq-list">
        <div class="kogu-sp-faq-item">
          <p class="kogu-sp-faq-q">ビット（先端工具）は別途必要ですか？</p>
          <p class="kogu-sp-faq-a">レンタルセットに No.2 プラスビットが1本付属しています。他のビットが必要な場合は、オプションでご購入いただくか、ホームセンター等でご購入ください。</p>
        </div>
        <div class="kogu-sp-faq-item">
          <p class="kogu-sp-faq-q">初めてでも使いこなせますか？</p>
          <p class="kogu-sp-faq-a">4段階のモード切替で、初心者でも扱いやすいソフトモードから始められます。小ネジの締めすぎを防ぎ、作業に慣れてからパワーモードへ移行できます。</p>
        </div>
        <div class="kogu-sp-faq-item">
          <p class="kogu-sp-faq-q">返却方法を教えてください。</p>
          <p class="kogu-sp-faq-a">同梱の返却用伝票を使い、最寄りの郵便局またはコンビニ（ローソン・ミニストップ）からゆうパック着払いで発送してください。返却期限日までの発送で返却完了となります。</p>
        </div>
      </div>
    </div>

    <!-- 下部CTA -->
    <div class="kogu-sp-bottom-cta">
      <p class="kogu-sp-bottom-cta-text">1週間¥<?php echo number_format( $week_price ); ?>〜。3,000円以上のご注文で送料無料。</p>
      <a href="<?php echo esc_url( $rental_url ); ?>" class="kogu-btn kogu-btn-primary kogu-sp-cta">
        このアイテムをレンタルする →
      </a>
    </div>

  </div>
</div>

<script>
(function() {
  var imgs   = <?php echo wp_json_encode( $gallery_imgs ); ?>;
  var cur    = 0;
  var imgEl  = document.getElementById('kogu-sp-img');
  var thumbs = document.querySelectorAll('.kogu-sp-thumb');

  function go(n) {
    cur = (n + imgs.length) % imgs.length;
    imgEl.src = imgs[cur];
    thumbs.forEach(function(t, i) { t.classList.toggle('active', i === cur); });
  }

  var prev = document.querySelector('.kogu-sp-prev');
  var next = document.querySelector('.kogu-sp-next');
  if (prev) prev.addEventListener('click', function() { go(cur - 1); });
  if (next) next.addEventListener('click', function() { go(cur + 1); });
  thumbs.forEach(function(t) {
    t.addEventListener('click', function() { go(parseInt(t.dataset.index)); });
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'ArrowLeft') go(cur - 1);
    if (e.key === 'ArrowRight') go(cur + 1);
  });
})();
</script>

<?php get_footer(); ?>
