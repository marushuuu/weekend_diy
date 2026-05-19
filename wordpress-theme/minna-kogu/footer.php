<?php defined( 'ABSPATH' ) || exit; ?>

<footer class="site-footer">
	<div class="container">
		<div class="footer-inner">
			<div class="footer-brand">
				<div class="nav-logo">
					<span class="mark">🔧</span>
					みんなの工具レンタル
				</div>
				<p>必要な日だけ、お手頃に。<br />DIYをもっと気軽に、もっと楽しく。</p>
			</div>

			<div class="footer-links">
				<h4>サービス</h4>
				<ul>
					<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">ホーム</a></li>
					<li><a href="<?php echo esc_url( home_url( '/rental' ) ); ?>">工具を借りる</a></li>
					<li><a href="<?php echo esc_url( home_url( '/my-page' ) ); ?>">マイページ</a></li>
				</ul>
			</div>

			<div class="footer-links">
				<h4>サポート</h4>
				<ul>
					<li><a href="<?php echo esc_url( home_url( '/terms' ) ); ?>">利用規約</a></li>
					<li><a href="<?php echo esc_url( home_url( '/tokushoho' ) ); ?>">特定商取引法に基づく表記</a></li>
					<li><a href="<?php echo esc_url( home_url( '/privacy' ) ); ?>">プライバシーポリシー</a></li>
				</ul>
			</div>
		</div>

		<p class="footer-bottom">
			&copy; <?php echo esc_html( date( 'Y' ) ); ?> みんなの工具レンタル. All rights reserved.
		</p>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
