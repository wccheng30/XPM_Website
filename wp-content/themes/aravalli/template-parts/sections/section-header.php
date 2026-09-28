<?php if ( get_header_image() ) : ?>
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>" id="custom-header" rel="home">
		<img src="<?php esc_url(header_image()); ?>" width="<?php echo esc_attr( get_custom_header()->width ); ?>" height="<?php echo esc_attr( get_custom_header()->height ); ?>" alt="<?php echo esc_attr(get_bloginfo( 'title' )); ?>">
	</a>	
<?php endif;  ?>	
		
<header id="header-section" class="header header-theme">
	<?php do_action('aravalli_above_header'); ?>
	<!--===// Start: Contact Widget & Logo
	=================================-->
	<div id="header-widget-bar" class="header-widget-info d-none d-lg-block">
		<div class="container">
			<div class="header-wrapper">    
				<?php
					$aravalli_hs_nav_info_left 		= get_theme_mod( 'hs_nav_info_left','1'); 
					$aravalli_nav_info_left_icon 	= get_theme_mod( 'nav_info_left_icon','fa-clock-o');
					$aravalli_nav_info_left_ttl 	= get_theme_mod( 'nav_info_left_ttl');
					$aravalli_nav_info_left_subttl 	= get_theme_mod( 'nav_info_left_subttl');
					if($aravalli_hs_nav_info_left =='1'){
				?>
				<div class="flex-filled">
					<div class="header-info left">
						<div class="header-item widget-left">
							<div class="info-item">
								<?php if(!empty($aravalli_nav_info_left_icon)): ?>
									<div class="info-icon">
										<i class="fa <?php echo esc_attr($aravalli_nav_info_left_icon); ?>"></i>
									</div>
								<?php endif; ?>
								<div class="info-content">
									<?php if(!empty($aravalli_nav_info_left_ttl)): ?>
										<h6 class="info-title"><?php echo esc_html($aravalli_nav_info_left_ttl); ?></h6>
									<?php endif; ?>
									<?php if(!empty($aravalli_nav_info_left_subttl)): ?>
									<div class="info-sub-title"><?php echo esc_html($aravalli_nav_info_left_subttl); ?></div>
									<?php endif; ?>
								</div>
							</div>
						</div>
					</div>
				</div>
				<?php } ?>
				<div class="flex-filled">
					<div class="logo text-center">
						<?php
							if(has_custom_logo())
							{	
								the_custom_logo();
							}
							else { 
							?>
							<a href="<?php echo esc_url( home_url( '/' ) ); ?>">
								<h4 class="site-title">
									<?php 
										echo esc_html(get_bloginfo('name'));
									?>
								</h4>
							</a>	
						<?php 						
							}
						?>
						<?php
							$aravalli_description = get_bloginfo( 'description');
							if ($aravalli_description) : ?>
								<p class="site-description"><?php echo esc_html($aravalli_description); ?></p>
						<?php endif; ?>
					</div>
				</div>
				<?php
					$aravalli_hs_nav_info_right 	= get_theme_mod( 'hs_nav_info_right','1'); 
					$aravalli_nav_info_right_icon 	= get_theme_mod( 'nav_info_right_icon','fa-clock-o');
					$aravalli_nav_info_right_ttl 	= get_theme_mod( 'nav_info_right_ttl');
					$aravalli_nav_info_right_subttl = get_theme_mod( 'nav_info_right_subttl');
					if($aravalli_hs_nav_info_right =='1'){
				?>
				<div class="flex-filled">
					<div class="header-info right">
						<div class="header-item widget-right">
							<div class="info-item">
								<?php if(!empty($aravalli_nav_info_right_icon)): ?>
									<div class="info-icon">
										<i class="fa <?php echo esc_attr($aravalli_nav_info_right_icon); ?>"></i>
									</div>
								<?php endif; ?>
								<div class="info-content">
									<?php if(!empty($aravalli_nav_info_right_ttl)): ?>
										<h6 class="info-title"><?php echo esc_html($aravalli_nav_info_right_ttl); ?></h6>
									<?php endif; ?>
									<?php if(!empty($aravalli_nav_info_right_subttl)): ?>
									<div class="info-sub-title"><?php echo esc_html($aravalli_nav_info_right_subttl); ?></div>
									<?php endif; ?>
								</div>
							</div>
						</div>
					</div>
				</div>
				<?php } ?>
			</div>
		</div>
	</div>
	<!--===// End: Contact Widget & Logo
	=================================-->

	<!--===// Start: Navigation
	=================================-->
	<div class="navigation d-none d-lg-block <?php echo esc_attr(aravalli_sticky_menu()); ?>">
		<div class="container">
			<div class="row">
				<div class="col-md-12">
					<div class="theme-menu">
						<nav class="menubar">
							<?php 
								wp_nav_menu( 
									array(  
										'theme_location' => 'primary_menu',
										'container'  => '',
										'menu_class' => 'menu-wrap',
										'fallback_cb' => 'WP_Bootstrap_Navwalker::fallback',
										'walker' => new WP_Bootstrap_Navwalker()
										 ) 
									);
							?>   
						</nav>
						<div class="menu-right">
							<ul class="wrap-right">
								<?php
									$aravalli_hs_search 	= get_theme_mod( 'hide_show_search','1'); 
									if($aravalli_hs_search =='1'){
								?>
									<li class="search-button">
										<a href="javascript:void(0)" id="view-search-btn" class="view-popup"><i class="fa fa-search"></i></a>
									</li>
									<!-- Quik search -->
										<div class="view-search-btn view-search">
											<form method="get" class="view-search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
												<input name="s" type="search" class="form-control search-field sb-field" placeholder="<?php esc_attr_e( 'Type To Search', 'aravalli' ); ?>">
												<a href="javascript:void(0)" class="view-search-remove search-submit"></a>
											</form>
										</div>
									<!-- // -->
								<?php } 
									$aravalli_hs_nav_btn 	= get_theme_mod( 'hide_show_nav_btn','1'); 
									$aravalli_nav_btn_lbl 	= get_theme_mod( 'nav_btn_lbl');
									$aravalli_nav_btn_link 	= get_theme_mod( 'nav_btn_link');
								?>
								<?php if($aravalli_hs_nav_btn == '1') { ?>
									<li class="menu-item">
										<a href="<?php echo esc_url($aravalli_nav_btn_link);?>" class="bt-primary bt-effect-1"><?php echo esc_html($aravalli_nav_btn_lbl);?></a>
									</li>
								<?php } ?>	
							</ul>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<!--===// End:  Navigation
	=================================-->

	<!--===// Start: Mobile Toggle
	 =================================-->
	<div class="theme-mobile-menu d-lg-none <?php echo esc_attr(aravalli_sticky_menu()); ?>">
		<div class="flex-filled headtop-mobi">
			<a href="javascript:void(0)" class="header-sidebar-toggle"><span></span></a>
		</div>
		<div id="mob-h-top" class="mobi-head-top animated"></div>
		<div class="flex-filled mobile-logo">
			<?php
				if(has_custom_logo())
				{	
					the_custom_logo();
				}
				else { 
				?>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<h4 class="site-title">
						<?php 
							echo esc_html(get_bloginfo('name'));
						?>
					</h4>
				</a>	
			<?php 						
				}
			?>
			<?php
				$aravalli_description = get_bloginfo( 'description');
				if ($aravalli_description) : ?>
					<p class="site-description"><?php echo esc_html($aravalli_description); ?></p>
			<?php endif; ?>
		</div>
		<div class="flex-filled">
			<div class="mobi-rightbar">                    
				<div class="menu-toggle">
					<div class="hamburger-menu">
						<a href="javascript:void(0);" class="menutogglebtn">
							<div class="top-bun"></div>
							<div class="meat"></div>
							<div class="bottom-bun"></div>
						</a>
					</div>
				</div>
			</div>
		</div>
		<div id="mobile-m" class="mobile-menu">
			<div class="mobile-menus">
				<a href="javascript:void(0)" class="close-menu"></a>
				<div class="mobi-head-cart"></div>
			</div>
		</div>
	</div>
	<!--===// End: Mobile Toggle
	=================================-->
</header>
<?php
if ( !is_page_template( 'templates/template-homepage.php' ) ) {
aravalli_breadcrumbs_style();  
}	