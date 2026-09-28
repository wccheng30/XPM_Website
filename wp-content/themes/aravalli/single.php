<?php
/**
 * The template for displaying archive pages.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package Aravalli
 */

get_header(); 
?>
<div id="blog-single" class="blog-section sec-default">
	<div class="container">
		<div class="row">	
			<div class="<?php esc_attr(aravalli_post_layout()); ?> mb-5 mb-lg-0">
			   <?php if( have_posts() ): ?>
					<?php while( have_posts() ): the_post(); ?>
						<?php get_template_part('template-parts/content/content','page-list'); ?> 
					<?php endwhile; ?>
				<?php endif; ?>
				
				<?php comments_template( '', true ); // show comments  ?>
			</div>
			<?php get_sidebar(); ?>
		</div>
	</div>
</div>
	
	
<?php get_footer(); ?>
