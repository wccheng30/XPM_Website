<?php 
$aravalli_hs_breadcrumb			= get_theme_mod('hs_breadcrumb','1');
$aravalli_hs_bread_checkin		= get_theme_mod('hs_breadcrumb_checkin','1');
$aravalli_bread_bg_img			= get_theme_mod('breadcrumb_bg_img',esc_url(get_template_directory_uri() .'/assets/images/rooms-bg.jpg'));
$aravalli_bread_back_attach		= get_theme_mod('breadcrumb_back_attach','scroll');
if($aravalli_hs_breadcrumb == '1') {	
?>
<section id="breadcrumbs">
	<div class="breadcrumbs breadcrumb-center" style="background-image:url('<?php echo esc_url($aravalli_bread_bg_img); ?>');background-attachment:<?php echo esc_attr($aravalli_bread_back_attach); ?>">
		 <div class="container">
			<div class="page-title">
					<h2>
						<?php 
							if ( is_home() || is_front_page()):
		
										single_post_title();
										
							elseif ( is_day() ) : 
							
								printf( __( 'Daily Archives: %s', 'aravalli' ), get_the_date() );
							
							elseif ( is_month() ) :
							
								printf( __( 'Monthly Archives: %s', 'aravalli' ), (get_the_date( 'F Y' ) ));
								
							elseif ( is_year() ) :
							
								printf( __( 'Yearly Archives: %s', 'aravalli' ), (get_the_date( 'Y' ) ) );
								
							elseif ( is_category() ) :
							
								printf( __( 'Category Archives: %s', 'aravalli' ), (single_cat_title( '', false ) ));

							elseif ( is_tag() ) :
							
								printf( __( 'Tag Archives: %s', 'aravalli' ), (single_tag_title( '', false ) ));
								
							elseif ( is_404() ) :

								printf( __( 'Error 404', 'aravalli' ));
								
							elseif ( is_author() ) :
							
								printf( __( 'Author: %s', 'aravalli' ), (get_the_author( '', false ) ));		
							
							else :
									the_title();
									
							endif;
							
						?>
					</h2>
			</div>
			<ul class="crumb">
				<?php if (function_exists('aravalli_breadcrumbs')) aravalli_breadcrumbs();?>
			</ul>
		</div>
	</div>
</section>
<?php 
}
if($aravalli_hs_bread_checkin == '1') {	
	do_action('cleverfox_aravalli_lite_checkin');
}else{ ?>
	<section style="margin-bottom:50px;"></section>
<?php } ?>	