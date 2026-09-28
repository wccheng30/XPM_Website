<?php
/**
 * The template for displaying 404 pages (not found).
 *
 * @link https://codex.wordpress.org/Creating_an_Error_404_Page
 *
 * @package Aravalli
 */

get_header();
?>
<section id="page-404" class="page-404 sec-default">
	<div class="container">
		<div class="row">
			<div class="col-12">
				<div class="text-404">
					<h1><?php esc_html_e('4','aravalli'); ?><span><?php esc_html_e('0','aravalli'); ?></span><?php esc_html_e('4','aravalli'); ?></h1>
					
					<h2><?php esc_html_e('Something Went Wrong','aravalli'); ?></h2>
					
					<h3><?php esc_html_e('Oops! That page can’t be found.','aravalli'); ?></h3>
					
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn-shape btn-line-primary"><?php esc_html_e('Go To Home','aravalli'); ?></a>
				</div>
			</div>
		</div>          
	</div>
</section>	
<?php get_footer(); ?>
