<?php
/**
 * The main template file.
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 * E.g., it puts together the home page when no home.php file exists.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package Aravalli
 */

get_header();  
?>
<div id="blog-grid-r-s" class="blog-section sec-default">
	<div class="container">
		<div class="row">
			<div class="<?php esc_attr(aravalli_post_layout()); ?> mb-5 mb-lg-0">
				<div class="side-grid d-flex flex-wrap">
					<?php 
						$aravalli_paged = ( get_query_var('paged') ) ? get_query_var('paged') : 1;
						$args = array( 'post_type' => 'post','paged'=>$aravalli_paged );	
					?>
					<?php if( have_posts() ): ?>
						<?php while( have_posts() ) : the_post(); ?>
							<?php get_template_part('template-parts/content/content','page'); ?> 
					<?php 
						endwhile;
					else: 
						 get_template_part('template-parts/content/content','none'); 
					 endif; ?>
				</div>
				<!-- Pagination Start -->
				<div class="pagination-nav mx-auto">
				   <?php								
						// Previous/next page navigation.
						the_posts_pagination( array(
						'prev_text'          => '<i class="fa fa-chevron-left"></i>',
						'next_text'          => '<i class="fa fa-chevron-right"></i>',
						) ); 
					?>
				</div>
				<!-- Pagination End -->
			</div>
			<?php get_sidebar(); ?>
		</div>
	</div>
</div>
	
<?php get_footer(); ?>
