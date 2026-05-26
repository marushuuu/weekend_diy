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
			3,000円以上のご注文で送料無料・最短翌日お届け。返却もかんたん。<br />
			<small>※北海道・沖縄・離島へのお届けは承っておりません。</small>
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
				<h3>3,000円以上で送料無料</h3>
				<p>3,000円以上のご注文はお届けの送料が当店負担。<br />ゆうパックでお届け（北海道・沖縄・離島を除く）。</p>
			</div>
			<div class="feature-item">
				<div class="icon">💳</div>
				<h3>週単位の料金</h3>
				<p>1週間単位で借りられて、<br />2週目以降は30%OFF。</p>
			</div>
			<div class="feature-item">
				<div class="icon">🔄</div>
				<h3>かんたん返却</h3>
				<p>ゆうパックで送り返すだけ。</p>
			</div>
		</div>
	</div>
</section>

<!-- ── Blog ── -->
<?php
$recent_posts = get_posts( [ 'numberposts' => 3, 'post_status' => 'publish' ] );
if ( $recent_posts ) : ?>
<section class="section" id="blog">
	<div class="container">
		<div class="section-head">
			<h2><span class="star">★</span>DIYのヒント</h2>
		</div>
		<div class="top-blog-grid">
			<?php foreach ( $recent_posts as $post ) : setup_postdata( $post ); ?>
			<a class="top-blog-card" href="<?php the_permalink(); ?>">
				<div class="top-blog-card-meta"><?php echo get_the_date( 'Y.m.d' ); ?></div>
				<div class="top-blog-card-title"><?php the_title(); ?></div>
				<div class="top-blog-card-more">続きを読む →</div>
			</a>
			<?php endforeach; wp_reset_postdata(); ?>
		</div>
		<div style="text-align:center;margin-top:24px;">
			<a href="<?php echo get_permalink( get_option( 'page_for_posts' ) ) ?: home_url( '/?post_type=post' ); ?>" class="btn-outline">記事一覧を見る</a>
		</div>
	</div>
</section>
<?php endif; ?>

<!-- ── Rental Form ── -->
<section class="section alt" id="tools">
	<div class="container">
		<div class="section-head">
			<h2><span class="star">★</span>工具を借りる</h2>
		</div>
		<?php echo do_shortcode( '[kogu_rental_form show_step_titles="false" mode="top"]' ); ?>
	</div>
</section>

<?php get_footer(); ?>
