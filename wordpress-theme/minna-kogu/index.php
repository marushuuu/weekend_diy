<?php defined( 'ABSPATH' ) || exit; get_header(); ?>

<div class="container">
	<div class="page-wrap">
		<?php if ( have_posts() ) : ?>
			<?php while ( have_posts() ) : the_post(); ?>
				<h1 class="page-title"><?php the_title(); ?></h1>
				<div class="page-card">
					<?php the_content(); ?>
				</div>
			<?php endwhile; ?>
		<?php else : ?>
			<div class="empty-note">
				<p>表示できるコンテンツがありません。</p>
				<a class="empty-cta" href="<?php echo esc_url( home_url( '/' ) ); ?>">トップへ戻る</a>
			</div>
		<?php endif; ?>
	</div>
</div>

<?php get_footer(); ?>
