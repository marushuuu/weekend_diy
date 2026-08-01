<?php defined( 'ABSPATH' ) || exit; ?>
<?php get_header(); ?>

<div class="container">
  <div class="page-wrap">

    <div class="blog-archive">

      <header class="blog-archive-header">
        <?php if ( is_category() ) : ?>
          <h1 class="blog-archive-title"><?php single_cat_title(); ?></h1>
          <?php if ( category_description() ) : ?>
            <p class="blog-archive-desc"><?php echo wp_kses_post( category_description() ); ?></p>
          <?php endif; ?>
        <?php elseif ( is_tag() ) : ?>
          <h1 class="blog-archive-title">タグ: <?php single_tag_title(); ?></h1>
        <?php else : ?>
          <h1 class="blog-archive-title">DIY・工具レンタル お役立ちコラム</h1>
          <p class="blog-archive-desc">工具の使い方からDIYの手順まで、初心者にやさしい情報をお届けします。</p>
        <?php endif; ?>
      </header>

      <?php if ( have_posts() ) : ?>

        <div class="blog-card-grid">
          <?php while ( have_posts() ) : the_post(); ?>
            <article class="blog-card" id="post-<?php the_ID(); ?>">
              <?php if ( has_post_thumbnail() ) : ?>
                <a href="<?php the_permalink(); ?>" class="blog-card-thumb" tabindex="-1" aria-hidden="true">
                  <?php the_post_thumbnail( 'medium', [ 'alt' => get_the_title() ] ); ?>
                </a>
              <?php else : ?>
                <a href="<?php the_permalink(); ?>" class="blog-card-thumb blog-card-thumb-none" tabindex="-1" aria-hidden="true">
                  <span>🔧</span>
                </a>
              <?php endif; ?>

              <div class="blog-card-body">
                <div class="blog-card-meta">
                  <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>" class="blog-card-date">
                    <?php echo esc_html( get_the_date( 'Y年n月j日' ) ); ?>
                  </time>
                  <?php
                  $cats = get_the_category();
                  if ( $cats ) :
                  ?>
                    <a href="<?php echo esc_url( get_category_link( $cats[0]->term_id ) ); ?>" class="blog-card-cat">
                      <?php echo esc_html( $cats[0]->name ); ?>
                    </a>
                  <?php endif; ?>
                </div>

                <h2 class="blog-card-title">
                  <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                </h2>

                <p class="blog-card-excerpt">
                  <?php echo wp_trim_words( get_the_excerpt(), 60, '…' ); ?>
                </p>

                <a href="<?php the_permalink(); ?>" class="blog-card-link">続きを読む →</a>
              </div>
            </article>
          <?php endwhile; ?>
        </div>

        <div class="blog-pagination">
          <?php
          the_posts_pagination( [
            'mid_size'  => 2,
            'prev_text' => '← 前のページ',
            'next_text' => '次のページ →',
          ] );
          ?>
        </div>

      <?php else : ?>
        <p class="blog-no-posts">記事がまだありません。</p>
      <?php endif; ?>

    </div><!-- /.blog-archive -->

  </div>
</div>

<?php get_footer(); ?>
