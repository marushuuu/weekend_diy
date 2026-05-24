<?php defined( 'ABSPATH' ) || exit; get_header(); ?>

<main class="article-main">
	<div class="container">
		<?php while ( have_posts() ) : the_post(); ?>

			<!-- パンくず -->
			<nav class="breadcrumb" aria-label="パンくずリスト">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>">ホーム</a>
				<span aria-hidden="true"> › </span>
				<a href="<?php echo esc_url( home_url( '/blog' ) ); ?>">DIY・工具のヒント</a>
				<span aria-hidden="true"> › </span>
				<span><?php the_title(); ?></span>
			</nav>

			<article class="article">
				<header class="article__header">
					<div class="article__meta">
						<time datetime="<?php echo get_the_date( 'Y-m-d' ); ?>"><?php echo get_the_date( 'Y年n月j日' ); ?></time>
						<?php
						$cats = get_the_category();
						if ( $cats ) {
							echo '<span class="article__cat">' . esc_html( $cats[0]->name ) . '</span>';
						}
						?>
					</div>
					<h1 class="article__title"><?php the_title(); ?></h1>
				</header>

				<?php if ( has_post_thumbnail() ) : ?>
					<div class="article__thumb">
						<?php the_post_thumbnail( 'large', [ 'loading' => 'eager' ] ); ?>
					</div>
				<?php endif; ?>

				<div class="article__body">
					<?php the_content(); ?>
				</div>

				<!-- 記事下CTA -->
				<aside class="article-cta">
					<div class="article-cta__inner">
						<p class="article-cta__lead">工具はレンタルがおトクです</p>
						<p class="article-cta__sub">1週間¥4,900〜・3,000円以上送料無料・2週目以降30%OFF</p>
						<a href="<?php echo esc_url( home_url( '/rental' ) ); ?>" class="article-cta__btn">🔧 工具をレンタルする</a>
					</div>
				</aside>

			</article>

			<!-- 前後の記事ナビ -->
			<nav class="post-nav">
				<div class="post-nav__prev">
					<?php previous_post_link( '%link', '← %title' ); ?>
				</div>
				<div class="post-nav__next">
					<?php next_post_link( '%link', '%title →' ); ?>
				</div>
			</nav>

		<?php endwhile; ?>
	</div>
</main>

<?php get_footer(); ?>
