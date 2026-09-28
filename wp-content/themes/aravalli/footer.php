<!-- Footer Area Start -->
  <footer id="footer" class="footer-wrapper">
        <div class="container">
			<?php if ( is_active_sidebar( 'aravalli-footer-widget-area' ) ) { ?>
				<div class="row widgets-mb">
					<?php  dynamic_sidebar( 'aravalli-footer-widget-area' ); ?>			
				</div>
			<?php } ?>
        </div>
		<?php 
		$footer_first_custom 	= get_theme_mod('footer_first_custom','Copyright &copy; [current_year] [site_title] | Powered by [theme_author]');	
		if ( ! empty( $footer_first_custom ) ){ ?>		
        <div class="footer-copyright">
            <div class="container">
                <div class="row">
					<div class="col-md-12 my-auto text-center">
						<?php 	
							$aravalli_copyright_allowed_tags = array(
								'[current_year]' => date_i18n('Y'),
								'[site_title]'   => get_bloginfo('name'),
								'[theme_author]' => sprintf(__('<a href="#">Aravalli WordPress Theme</a>', 'aravalli')),
							);
						?>                      
						<div class="copyright-text">
							<?php
								echo apply_filters('aravalli_footer_copyright', wp_kses_post(aravalli_str_replace_assoc($aravalli_copyright_allowed_tags, $footer_first_custom)));
							?>
						</div>
					</div>
                </div>
            </div>
        </div>
		<?php } ?>
    </footer>
	 <!-- ScrollUp -->
	 <?php 
		$hs_scroller 	= get_theme_mod('hs_scroller','1');	
		if($hs_scroller == '1') :
	?>
		 <a href="javascript:void(0)" class="scrollup"><i class="fa fa-arrow-up"></i></a>
	<?php endif; ?>	
  <!-- / -->  
</div>
</div>
<?php 
wp_footer(); ?>
</body>
</html>
