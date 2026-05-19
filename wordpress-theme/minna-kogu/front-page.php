<?php defined( 'ABSPATH' ) || exit; get_header(); ?>

<?php $banner = get_template_directory_uri() . '/assets/images/top_banner.jpeg'; ?>

<!-- ── Hero ── -->
<section class="hero">
	<img class="hero-img" src="<?php echo esc_url( $banner ); ?>" alt="工具レンタルのイメージ" width="1400" height="480" />
	<div class="hero-overlay"></div>
	<div class="hero-copy">
		<h1>はじめての工具、<br />借りるからはじめよう。</h1>
		<p>
			必要な日だけ、お手頃に。<br />
			送料無料・最短翌日お届け。返却もかんたん。
		</p>
		<a href="#tools" class="hero-cta">▶ 工具を探す</a>
	</div>
</section>

<!-- ── Features ── -->
<section class="features">
	<div class="container">
		<div class="features-grid">
			<div class="feature-item">
				<div class="icon">📦</div>
				<h3>発送送料無料</h3>
				<p>お届けの送料は当店負担。<br />ゆうパックで全国へお届けします。</p>
			</div>
			<div class="feature-item">
				<div class="icon">💳</div>
				<h3>週単位の料金</h3>
				<p>1週間単位で借りられて、<br />2週目以降は30%OFF。</p>
			</div>
			<div class="feature-item">
				<div class="icon">🔄</div>
				<h3>かんたん返却</h3>
				<p>ゆうパックで送り返すだけ。<br />デポジット（保証金）も不要です。</p>
			</div>
		</div>
	</div>
</section>

<!-- ── Tools ── -->
<section class="section alt" id="tools">
	<div class="container">
		<div class="section-head">
			<h2><span class="star">★</span>レンタルできる工具</h2>
			<a href="<?php echo esc_url( home_url( '/rental' ) ); ?>" class="see-all">申込ページへ →</a>
		</div>

		<?php $products = minna_kogu_get_products(); ?>

		<?php if ( ! empty( $products ) ) : ?>
			<div class="tool-grid">
				<?php foreach ( $products as $product ) : ?>
					<?php minna_kogu_render_card( $product ); ?>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="empty-note">
				<p>ただいま商品を準備中です。</p>
				<a class="empty-cta" href="<?php echo esc_url( home_url( '/rental' ) ); ?>">レンタル申込ページへ</a>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php get_footer(); ?>
