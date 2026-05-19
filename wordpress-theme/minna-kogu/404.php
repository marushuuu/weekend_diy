<?php defined( 'ABSPATH' ) || exit; get_header(); ?>

<div class="container">
	<div class="page-wrap">
		<div class="empty-note">
			<p>お探しのページは見つかりませんでした。</p>
			<a class="empty-cta" href="<?php echo esc_url( home_url( '/' ) ); ?>">トップへ戻る</a>
		</div>
	</div>
</div>

<?php get_footer(); ?>
