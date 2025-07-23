<?php
/**
 * Template for displaying project archives
 * 
 * This template will be used for project category archives and main project archive
 */

get_header(); ?>

<div class="container">
    <div class="content-area">
        <main class="site-main">
            
            <header class="page-header">
                <?php if (is_tax('proje_kategorisi')): ?>
                    <h1 class="page-title">
                        <?php single_term_title(); ?> Projeleri
                    </h1>
                    <?php 
                    $term_description = term_description();
                    if ($term_description):
                    ?>
                        <div class="archive-description">
                            <?php echo $term_description; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <h1 class="page-title">Tüm Projeler</h1>
                <?php endif; ?>
            </header>
            
            <?php if (have_posts()): ?>
                
                <div class="project-gallery" data-columns="3">
                    <?php while (have_posts()): the_post(); ?>
                        <div class="project-item">
                            <a href="<?php the_permalink(); ?>" class="project-link">
                                <?php if (has_post_thumbnail()): ?>
                                    <div class="project-thumbnail">
                                        <?php the_post_thumbnail('proje-thumbnail'); ?>
                                        <div class="project-overlay">
                                            <h3 class="project-title"><?php the_title(); ?></h3>
                                            <?php $categories = get_the_terms(get_the_ID(), 'proje_kategorisi'); ?>
                                            <?php if ($categories && !is_wp_error($categories)): ?>
                                                <div class="project-categories">
                                                    <?php foreach ($categories as $category): ?>
                                                        <span class="project-category"><?php echo esc_html($category->name); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="project-thumbnail project-no-image">
                                        <div class="project-overlay">
                                            <h3 class="project-title"><?php the_title(); ?></h3>
                                            <?php if (get_the_excerpt()): ?>
                                                <p class="project-excerpt"><?php echo wp_trim_words(get_the_excerpt(), 15); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </a>
                        </div>
                    <?php endwhile; ?>
                </div>
                
                <?php
                // Pagination
                $pagination = paginate_links(array(
                    'type' => 'array',
                    'prev_text' => '&laquo; Önceki',
                    'next_text' => 'Sonraki &raquo;'
                ));
                
                if ($pagination):
                ?>
                    <nav class="pagination-nav" role="navigation" aria-label="Sayfa navigasyonu">
                        <ul class="pagination">
                            <?php foreach ($pagination as $link): ?>
                                <li><?php echo $link; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
                
            <?php else: ?>
                
                <div class="no-projects-found">
                    <h2>Proje Bulunamadı</h2>
                    <p>Bu kategoride henüz proje bulunmamaktadır.</p>
                    <a href="<?php echo home_url(); ?>" class="btn btn-primary">Ana Sayfaya Dön</a>
                </div>
                
            <?php endif; ?>
            
        </main>
    </div>
</div>

<?php get_footer(); ?>