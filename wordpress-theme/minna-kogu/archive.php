<?php defined( 'ABSPATH' ) || exit; get_header(); ?>

<main class="blog-main">
	<div class="container">

		<div class="blog-header">
			<h1 class="blog-header__title">DIY・工具のヒント</h1>
			<p class="blog-header__desc">DIY初心者から経験者まで役立つ、工具の選び方・使い方・レンタル活用術をお届けします。</p>
		</div>

		<?php if ( have_posts() ) : ?>
			<div class="blog-grid">
				<?php while ( have_posts() ) : the_post(); ?>
					<article class="blog-card">
						<?php if ( has_post_thumbnail() ) : ?>
							<a href="<?php the_permalink(); ?>" class="blog-card__img-wrap">
								<?php the_post_thumbnail( 'medium_large', [ 'class' => 'blog-card__img', 'loading' => 'lazy' ] ); ?>
							</a>
						<?php else : ?>
							<a href="<?php the_permalink(); ?>" class="blog-card__img-wrap blog-card__img-wrap--placeholder">
								<span>🔧</span>
							</a>
						<?php endif; ?>
						<div class="blog-card__body">
							<div class="blog-card__meta">
								<time datetime="<?php echo get_the_date( 'Y-m-d' ); ?>"><?php echo get_the_date( 'Y年n月j日' ); ?></time>
								<?php
								$cats = get_the_category();
								if ( $cats ) {
									echo '<span class="blog-card__cat">' . esc_html( $cats[0]->name ) . '</span>';
								}
								?>
							</div>
							<h2 class="blog-card__title">
								<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
							</h2>
							<p class="blog-card__excerpt"><?php echo wp_trim_words( get_the_excerpt(), 60, '…' ); ?></p>
							<a href="<?php the_permalink(); ?>" class="blog-card__more">続きを読む →</a>
						</div>
					</article>
				<?php endwhile; ?>
			</div>

			<div class="blog-pagination">
				<?php
				the_posts_pagination( [
					'mid_size'  => 2,
					'prev_text' => '← 前へ',
					'next_text' => '次へ →',
				] );
				?>
			</div>

		<?php else : ?>
			<p class="blog-empty">記事はまだありません。しばらくお待ちください。</p>
		<?php endif; ?>

	</div>
</main>

<?php get_footer(); ?>
