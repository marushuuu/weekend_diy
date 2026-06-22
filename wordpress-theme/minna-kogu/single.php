<?php defined( 'ABSPATH' ) || exit; ?>
<?php get_header(); ?>

<div class="container">
  <div class="page-wrap">

    <?php while ( have_posts() ) : the_post(); ?>

      <article id="post-<?php the_ID(); ?>" <?php post_class( 'single-post' ); ?>>

        <!-- パンくず -->
        <nav class="kogu-breadcrumb" aria-label="パンくずリスト">
          <ol>
            <li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">ホーム</a></li>
            <?php
            $cats = get_the_category();
            if ( $cats ) :
            ?>
              <li><a href="<?php echo esc_url( get_category_link( $cats[0]->term_id ) ); ?>"><?php echo esc_html( $cats[0]->name ); ?></a></li>
            <?php endif; ?>
            <li aria-current="page"><?php the_title(); ?></li>
          </ol>
        </nav>

        <!-- 記事ヘッダー -->
        <header class="single-post-header">
          <div class="single-post-meta">
            <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>" class="single-post-date">
              <?php echo esc_html( get_the_date( 'Y年n月j日' ) ); ?>
            </time>
            <?php if ( $cats ) : ?>
              <a href="<?php echo esc_url( get_category_link( $cats[0]->term_id ) ); ?>" class="blog-card-cat">
                <?php echo esc_html( $cats[0]->name ); ?>
              </a>
            <?php endif; ?>
          </div>
          <h1 class="single-post-title"><?php the_title(); ?></h1>
        </header>

        <!-- アイキャッチ -->
        <?php if ( has_post_thumbnail() ) : ?>
          <div class="single-post-thumb">
            <?php the_post_thumbnail( 'large', [ 'alt' => get_the_title() ] ); ?>
          </div>
        <?php endif; ?>

        <!-- 本文 -->
        <div class="single-post-content">
          <?php the_content(); ?>
        </div>

        <!-- 記事内CTA -->
        <div class="single-post-cta">
          <p class="single-post-cta-title">🔧 工具はレンタルで試してみませんか？</p>
          <p class="single-post-cta-desc">
            インパクトドライバーを1週間¥4,900からレンタル。<br>
            バッテリー2個・急速充電器付きで最短翌日お届け。3,500円以上送料無料。
          </p>
          <div class="single-post-cta-buttons">
            <a href="<?php echo esc_url( home_url( '/rental/' ) ); ?>" class="kogu-btn kogu-btn-primary">
              レンタルを申し込む →
            </a>
            <a href="<?php echo esc_url( home_url( '/products/impact-driver/' ) ); ?>" class="kogu-btn kogu-btn-secondary">
              商品詳細を見る
            </a>
          </div>
        </div>

        <!-- タグ -->
        <?php
        $tags = get_the_tags();
        if ( $tags ) :
        ?>
          <div class="single-post-tags">
            <?php foreach ( $tags as $tag ) : ?>
              <a href="<?php echo esc_url( get_tag_link( $tag->term_id ) ); ?>" class="single-post-tag"><?php echo esc_html( $tag->name ); ?></a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

      </article>

      <!-- 関連記事 -->
      <?php
      $cats = get_the_category();
      if ( $cats ) :
        $related = get_posts( [
          'category__in'   => [ $cats[0]->term_id ],
          'post__not_in'   => [ get_the_ID() ],
          'posts_per_page' => 3,
          'orderby'        => 'rand',
        ] );
        if ( $related ) :
      ?>
        <section class="single-related">
          <h2 class="single-related-title">関連記事</h2>
          <div class="blog-card-grid">
            <?php foreach ( $related as $rpost ) : setup_postdata( $rpost ); ?>
              <article class="blog-card">
                <?php if ( has_post_thumbnail( $rpost->ID ) ) : ?>
                  <a href="<?php echo esc_url( get_permalink( $rpost->ID ) ); ?>" class="blog-card-thumb" tabindex="-1">
                    <?php echo get_the_post_thumbnail( $rpost->ID, 'medium', [ 'alt' => esc_attr( $rpost->post_title ) ] ); ?>
                  </a>
                <?php else : ?>
                  <a href="<?php echo esc_url( get_permalink( $rpost->ID ) ); ?>" class="blog-card-thumb blog-card-thumb-none" tabindex="-1">
                    <span>🔧</span>
                  </a>
                <?php endif; ?>
                <div class="blog-card-body">
                  <h3 class="blog-card-title">
                    <a href="<?php echo esc_url( get_permalink( $rpost->ID ) ); ?>"><?php echo esc_html( $rpost->post_title ); ?></a>
                  </h3>
                  <a href="<?php echo esc_url( get_permalink( $rpost->ID ) ); ?>" class="blog-card-link">続きを読む →</a>
                </div>
              </article>
            <?php endforeach; wp_reset_postdata(); ?>
          </div>
        </section>
      <?php endif; endif; ?>

    <?php endwhile; ?>

  </div>
</div>

<?php get_footer(); ?>
