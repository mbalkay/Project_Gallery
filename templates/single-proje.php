<?php
/**
 * Template for displaying single project
 * 
 * This template will be used for single project pages
 */

get_header(); ?>

<div class="container">
    <div class="content-area">
        <main class="site-main">
            
            <?php while (have_posts()) : the_post(); ?>
                
                <article id="post-<?php the_ID(); ?>" <?php post_class('single-project'); ?>>
                    
                    <!-- Project Header -->
                    <header class="single-project-header">
                        <hr class="project-title-divider">
                        <h1 class="single-project-title"><?php the_title(); ?></h1>
                        <hr class="project-title-divider">
                        
                        <?php 
                        $categories = get_the_terms(get_the_ID(), 'proje_kategorisi');
                        if ($categories && !is_wp_error($categories)): 
                        ?>
                            <div class="single-project-categories">
                                <?php foreach ($categories as $category): ?>
                                    <a href="<?php echo get_term_link($category); ?>" class="single-project-category">
                                        <?php echo esc_html($category->name); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </header>
                    
                    <!-- Featured Image -->
                    <?php if (has_post_thumbnail()): ?>
                        <div class="single-project-featured">
                            <div class="featured-image-container">
                                <?php the_post_thumbnail('full', array('class' => 'featured-image')); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Project Content -->
                    <div class="single-project-content">
                        <?php the_content(); ?>
                    </div>
                    
                    <!-- Project Gallery -->
                    <?php 
                    $gallery_images = get_post_meta(get_the_ID(), '_project_gallery_images', true);
                    if ($gallery_images): 
                        $image_ids = explode(',', $gallery_images);
                        $image_ids = array_filter($image_ids);
                        
                        if (!empty($image_ids)):
                            // Get gallery settings
                            $options = get_option('project_gallery_options');
                            $image_size = isset($options['image_size']) ? $options['image_size'] : 'proje-medium';
                            $layout_type = isset($options['layout_type']) ? $options['layout_type'] : 'grid';
                    ?>
                        <div class="single-project-gallery" 
                             data-gallery-count="<?php echo count($image_ids); ?>"
                             data-layout="<?php echo esc_attr($layout_type); ?>">
                            <div class="gallery-grid">
                                <?php foreach ($image_ids as $index => $image_id): ?>
                                    <?php if ($image_id): ?>
                                        <div class="gallery-image" 
                                             tabindex="0" 
                                             role="button" 
                                             aria-label="Resmi büyük boyutta görüntüle"
                                             data-index="<?php echo $index; ?>">
                                            <div class="gallery-image-container">
                                                <?php 
                                                echo wp_get_attachment_image(
                                                    $image_id, 
                                                    $image_size, 
                                                    false, 
                                                    array(
                                                        'data-full' => wp_get_attachment_image_url($image_id, 'full'),
                                                        'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true)
                                                    )
                                                ); 
                                                ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php 
                        endif;
                    endif; 
                    ?>
                    
                    <!-- Project Navigation -->
                    <?php 
                    $current_categories = wp_get_post_terms(get_the_ID(), 'proje_kategorisi', array('fields' => 'ids'));
                    
                    if (!empty($current_categories)):
                        // Get previous project in same category
                        $prev_project = get_posts(array(
                            'post_type' => 'proje',
                            'numberposts' => 1,
                            'post_status' => 'publish',
                            'date_query' => array(
                                'before' => get_the_date('Y-m-d H:i:s')
                            ),
                            'orderby' => 'date',
                            'order' => 'DESC',
                            'tax_query' => array(
                                array(
                                    'taxonomy' => 'proje_kategorisi',
                                    'field' => 'term_id',
                                    'terms' => $current_categories,
                                    'operator' => 'IN'
                                )
                            ),
                            'post__not_in' => array(get_the_ID())
                        ));
                        
                        // Get next project in same category
                        $next_project = get_posts(array(
                            'post_type' => 'proje',
                            'numberposts' => 1,
                            'post_status' => 'publish',
                            'date_query' => array(
                                'after' => get_the_date('Y-m-d H:i:s')
                            ),
                            'orderby' => 'date',
                            'order' => 'ASC',
                            'tax_query' => array(
                                array(
                                    'taxonomy' => 'proje_kategorisi',
                                    'field' => 'term_id',
                                    'terms' => $current_categories,
                                    'operator' => 'IN'
                                )
                            ),
                            'post__not_in' => array(get_the_ID())
                        ));
                        
                        if ($prev_project || $next_project):
                    ?>
                        <nav class="project-navigation" role="navigation" aria-label="Proje navigasyonu">
                            <div class="nav-previous">
                                <?php if ($prev_project): ?>
                                    <a href="<?php echo get_permalink($prev_project[0]->ID); ?>" class="nav-link" rel="prev">
                                        <span class="nav-link-text">← Önceki Proje</span>
                                        <span class="nav-link-title"><?php echo get_the_title($prev_project[0]->ID); ?></span>
                                    </a>
                                <?php endif; ?>
                            </div>
                            
                            <div class="nav-center">
                                <a href="<?php echo get_post_type_archive_link('proje'); ?>" class="nav-link">
                                    Tüm Projeler
                                </a>
                            </div>
                            
                            <div class="nav-next">
                                <?php if ($next_project): ?>
                                    <a href="<?php echo get_permalink($next_project[0]->ID); ?>" class="nav-link" rel="next">
                                        <span class="nav-link-text">Sonraki Proje →</span>
                                        <span class="nav-link-title"><?php echo get_the_title($next_project[0]->ID); ?></span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </nav>
                    <?php 
                        endif;
                    endif; 
                    ?>
                    
                </article>
                
            <?php endwhile; ?>
            
        </main>
    </div>
</div>

<!-- Schema.org structured data for SEO -->
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "CreativeWork",
    "name": "<?php echo esc_js(get_the_title()); ?>",
    "description": "<?php echo esc_js(wp_strip_all_tags(get_the_excerpt())); ?>",
    "url": "<?php echo esc_url(get_permalink()); ?>",
    "datePublished": "<?php echo get_the_date('c'); ?>",
    "dateModified": "<?php echo get_the_modified_date('c'); ?>",
    <?php if (has_post_thumbnail()): ?>
    "image": "<?php echo esc_url(get_the_post_thumbnail_url(null, 'large')); ?>",
    <?php endif; ?>
    "author": {
        "@type": "Person",
        "name": "<?php echo esc_js(get_the_author()); ?>"
    },
    <?php 
    $categories = get_the_terms(get_the_ID(), 'proje_kategorisi');
    if ($categories && !is_wp_error($categories)): 
    ?>
    "keywords": [
        <?php 
        $category_names = array();
        foreach ($categories as $category) {
            $category_names[] = '"' . esc_js($category->name) . '"';
        }
        echo implode(', ', $category_names);
        ?>
    ],
    <?php endif; ?>
    "publisher": {
        "@type": "Organization",
        "name": "<?php echo esc_js(get_bloginfo('name')); ?>"
    }
}
</script>

<?php get_footer(); ?>