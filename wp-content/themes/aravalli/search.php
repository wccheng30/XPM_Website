<?php
/**
 * The template for displaying search results pages.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#search-result
 *
 * @package Aravalli
 */

get_header();
?>
<section class="blog-content bs-py-default">
	<div class="container">
		<div class="row g-5">
			<!-- Blog Posts -->
			<div class="<?php esc_attr(aravalli_post_layout()); ?>">
				<div class="row row-cols-1 gy-4">
					<?php if( have_posts() ): ?>
						<?php while( have_posts() ) : the_post(); ?>
							<div class="col-lg-12">
								<?php get_template_part('template-parts/content/content','search'); ?>
							</div>
						<?php 
							endwhile;
							the_posts_navigation();
						else: 
						 get_template_part('template-parts/content/content','none'); 
					 endif; ?>
				</div>                  
			</div>
			<?php  get_sidebar(); ?>
		</div>
	</div>
</section>
<?php get_footer(); ?>
