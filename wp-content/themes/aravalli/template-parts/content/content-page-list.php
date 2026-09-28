<?php
/**
 * Template part for displaying page content in page.php.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package Aravalli
 */

?>
<article class="blog-post blog-horizontal mb-5">
	<?php if ( has_post_thumbnail() ) { ?>
		<div class="post-thumbnail">
		   <a href="javascript:void(0)" class="img-responsive">
				<?php the_post_thumbnail(); ?>
			</a>
		</div>
	<?php } ?>
	<div class="post-content">
		<div class="post-content-inner">
			<ul class="meta-blog">
				<li><a href="javascrip:void(0)"><?php the_category(' '); ?></a></li>
				<?php  $user = wp_get_current_user(); ?>
				<li><span><?php echo esc_html_e('Posted By','aravalli'); ?></span> <img src="<?php echo esc_url( get_avatar_url( $user->ID ) ); ?>" alt=""></li>
			</ul>
			<?php     
				if ( is_single() ) :
				
				the_title('<h5 class="post-title">', '</h5>' );
				
				else:
				
				the_title( sprintf( '<h5 class="post-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h5>' );
				
				endif; 
				
				the_content( 
						sprintf( 
							__( 'Read More', 'aravalli' ), 
							'<span class="screen-reader-text">  '.esc_html(get_the_title()).'</span>' 
						) 
					);
			?> 
		</div><!-- .entry-content -->
	</div>
</article>