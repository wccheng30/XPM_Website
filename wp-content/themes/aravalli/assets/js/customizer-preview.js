/**
 * File customizer.js.
 *
 * Theme Customizer enhancements for a better user experience.
 *
 * Contains handlers to make Theme Customizer preview reload changes asynchronously.
 */

( function( $ ) {

	// Site title and description.
	wp.customize( 'blogname', function( value ) {
		value.bind( function( to ) {
			$( '.site-title' ).text( to );
		} );
	} );
	wp.customize( 'blogdescription', function( value ) {
		value.bind( function( to ) {
			$( '.site-description' ).text( to );
		} );
	} );

	// Header text color.
	wp.customize( 'header_textcolor', function( value ) {
		value.bind( function( to ) {
			if ( 'blank' === to ) {
				$( '.site-title, .site-description' ).css( {
					'clip': 'rect(1px, 1px, 1px, 1px)',
					'position': 'absolute'
				} );
			} else {
				$( '.site-title, .site-description' ).css( {
					'clip': 'auto',
					'position': 'relative'
				} );
				$( '.site-title, .site-description' ).css( {
					'color': to
				} );
			}
		} );
	} );
	
	$(document).ready(function ($) {
        $('input[data-input-type]').on('input change', function () {
            var val = $(this).val();
            $(this).prev('.cs-range-value').html(val);
            $(this).val(val);
        });
    })
	
	
	/**
	 * logo_width
	 */
	wp.customize( 'logo_width', function( value ) {
		value.bind( function( width ) {
			jQuery( '.logo img, .mobile-logo img' ).css( 'max-width', width + 'px' );
		} );
	} );
	
	//tlh_contact_title
	wp.customize(
		'tlh_contact_title', function( value ) {
			value.bind(
				function( newval ) {
					$( '.header-top-info .phone span' ).text( newval );
				}
			);
		}
	);
	
	//tlh_email_title
	wp.customize(
		'tlh_email_title', function( value ) {
			value.bind(
				function( newval ) {
					$( '.header-top-info .email span' ).text( newval );
				}
			);
		}
	);
	
	
	//nav_info_left_ttl
	wp.customize(
		'nav_info_left_ttl', function( value ) {
			value.bind(
				function( newval ) {
					$( '.header-widget-info .header-info.left h6' ).text( newval );
				}
			);
		}
	);
	
	//nav_info_left_subttl
	wp.customize(
		'nav_info_left_subttl', function( value ) {
			value.bind(
				function( newval ) {
					$( '.header-widget-info .header-info.left .info-sub-title' ).text( newval );
				}
			);
		}
	);
	
	
	
	//nav_info_right_ttl
	wp.customize(
		'nav_info_right_ttl', function( value ) {
			value.bind(
				function( newval ) {
					$( '.header-widget-info .header-info.right h6' ).text( newval );
				}
			);
		}
	);
	
	//nav_info_right_subttl
	wp.customize(
		'nav_info_right_subttl', function( value ) {
			value.bind(
				function( newval ) {
					$( '.header-widget-info .header-info.right .info-sub-title' ).text( newval );
				}
			);
		}
	);
	
	//checkin_title
	wp.customize(
		'checkin_title', function( value ) {
			value.bind(
				function( newval ) {
					$( '.home-checkin .checkin-text h3' ).text( newval );
				}
			);
		}
	);
	
	
	//checkin_desc
	wp.customize(
		'checkin_desc', function( value ) {
			value.bind(
				function( newval ) {
					$( '.home-checkin .checkin-text p' ).text( newval );
				}
			);
		}
	);
	
	
	
	//room_title
	wp.customize(
		'room_title', function( value ) {
			value.bind(
				function( newval ) {
					$( '.room-home .heading-default h6' ).text( newval );
				}
			);
		}
	);
	
	
	//room_subtitle
	wp.customize(
		'room_subtitle', function( value ) {
			value.bind(
				function( newval ) {
					$( '.room-home .heading-default h3' ).text( newval );
				}
			);
		}
	);
	
	//room_desc
	wp.customize(
		'room_desc', function( value ) {
			value.bind(
				function( newval ) {
					$( '.room-home .heading-default p' ).text( newval );
				}
			);
		}
	);
	
	
	
	
	//features_title
	wp.customize(
		'features_title', function( value ) {
			value.bind(
				function( newval ) {
					$( '.features-home .heading-default h6' ).text( newval );
				}
			);
		}
	);
	
	
	//features_subtitle
	wp.customize(
		'features_subtitle', function( value ) {
			value.bind(
				function( newval ) {
					$( '.features-home .heading-default h3' ).text( newval );
				}
			);
		}
	);
	
	//features_description
	wp.customize(
		'features_description', function( value ) {
			value.bind(
				function( newval ) {
					$( '.features-home .heading-default p' ).text( newval );
				}
			);
		}
	);
	
	
	
	//testimonial_title
	wp.customize(
		'testimonial_title', function( value ) {
			value.bind(
				function( newval ) {
					$( '.home-testimonial .heading-default h6' ).text( newval );
				}
			);
		}
	);
	
	
	//testimonial_subtitle
	wp.customize(
		'testimonial_subtitle', function( value ) {
			value.bind(
				function( newval ) {
					$( '.home-testimonial .heading-default h3' ).text( newval );
				}
			);
		}
	);
	
	//testimonial_description
	wp.customize(
		'testimonial_description', function( value ) {
			value.bind(
				function( newval ) {
					$( '.home-testimonial .heading-default p' ).text( newval );
				}
			);
		}
	);
	
	
	
	//event_title
	wp.customize(
		'event_title', function( value ) {
			value.bind(
				function( newval ) {
					$( '.home-event .heading-default h6' ).text( newval );
				}
			);
		}
	);
	
	
	//event_subtitle
	wp.customize(
		'event_subtitle', function( value ) {
			value.bind(
				function( newval ) {
					$( '.home-event .heading-default h3' ).text( newval );
				}
			);
		}
	);
	
	//event_description
	wp.customize(
		'event_description', function( value ) {
			value.bind(
				function( newval ) {
					$( '.home-event .heading-default p' ).text( newval );
				}
			);
		}
	);
	
	
	
	//newsletter_title
	wp.customize(
		'newsletter_title', function( value ) {
			value.bind(
				function( newval ) {
					$( '.news-home .news-info h4' ).text( newval );
				}
			);
		}
	);
	
	
	//newsletter_desc
	wp.customize(
		'newsletter_desc', function( value ) {
			value.bind(
				function( newval ) {
					$( '.news-home .news-info p' ).text( newval );
				}
			);
		}
	);
	
	//newsletter_app_title
	wp.customize(
		'newsletter_app_title', function( value ) {
			value.bind(
				function( newval ) {
					$( '.news-home .news-info h2' ).text( newval );
				}
			);
		}
	);
	
	
	
	
	
	//pg_about_title
	wp.customize(
		'pg_about_title', function( value ) {
			value.bind(
				function( newval ) {
					$( '.about-section .heading-default h6' ).text( newval );
				}
			);
		}
	);
	
	
	//pg_about_subtitle
	wp.customize(
		'pg_about_subtitle', function( value ) {
			value.bind(
				function( newval ) {
					$( '.about-section .heading-default h3' ).text( newval );
				}
			);
		}
	);
	
	//pg_about_desc
	wp.customize(
		'pg_about_desc', function( value ) {
			value.bind(
				function( newval ) {
					$( '.about-section .about-panel .content' ).text( newval );
				}
			);
		}
	);
	
	
	//pg_about_btn_lbl
	wp.customize(
		'pg_about_btn_lbl', function( value ) {
			value.bind(
				function( newval ) {
					$( '.about-section .about-panel a' ).text( newval );
				}
			);
		}
	);
	
	
	
	//pg_about_why_ttl
	wp.customize(
		'pg_about_why_ttl', function( value ) {
			value.bind(
				function( newval ) {
					$( '.about-faq .heading-default h6' ).text( newval );
				}
			);
		}
	);
	
	
	//pg_about_why_sub
	wp.customize(
		'pg_about_why_sub', function( value ) {
			value.bind(
				function( newval ) {
					$( '.about-faq .heading-default h3' ).text( newval );
				}
			);
		}
	);
	
	//pg_about_why_desc
	wp.customize(
		'pg_about_why_desc', function( value ) {
			value.bind(
				function( newval ) {
					$( '.about-faq .heading-default p' ).text( newval );
				}
			);
		}
	);
	
	
	//pg_about_amenities_ttl
	wp.customize(
		'pg_about_amenities_ttl', function( value ) {
			value.bind(
				function( newval ) {
					$( '.amenities .heading-default h6' ).text( newval );
				}
			);
		}
	);
	
	
	//pg_about_amenities_sub
	wp.customize(
		'pg_about_amenities_sub', function( value ) {
			value.bind(
				function( newval ) {
					$( '.amenities .heading-default h3' ).text( newval );
				}
			);
		}
	);
	
	//pg_about_amenities_desc
	wp.customize(
		'pg_about_amenities_desc', function( value ) {
			value.bind(
				function( newval ) {
					$( '.amenities .heading-default p' ).text( newval );
				}
			);
		}
	);
	
	
	//pg_about_team_ttl
	wp.customize(
		'pg_about_team_ttl', function( value ) {
			value.bind(
				function( newval ) {
					$( '.team-wrapper .heading-default h6' ).text( newval );
				}
			);
		}
	);
	
	
	//pg_about_team_sub
	wp.customize(
		'pg_about_team_sub', function( value ) {
			value.bind(
				function( newval ) {
					$( '.team-wrapper .heading-default h3' ).text( newval );
				}
			);
		}
	);
	
	//pg_about_team_desc
	wp.customize(
		'pg_about_team_desc', function( value ) {
			value.bind(
				function( newval ) {
					$( '.team-wrapper .heading-default p' ).text( newval );
				}
			);
		}
	);
	
	
	//pg_about_certificates_ttl
	wp.customize(
		'pg_about_certificates_ttl', function( value ) {
			value.bind(
				function( newval ) {
					$( '.certificates .heading-default h6' ).text( newval );
				}
			);
		}
	);
	
	
	//pg_about_certificates_sub
	wp.customize(
		'pg_about_certificates_sub', function( value ) {
			value.bind(
				function( newval ) {
					$( '.certificates .heading-default h3' ).text( newval );
				}
			);
		}
	);
	
	//pg_about_certificates_desc
	wp.customize(
		'pg_about_certificates_desc', function( value ) {
			value.bind(
				function( newval ) {
					$( '.certificates .heading-default p' ).text( newval );
				}
			);
		}
	);
	
	
	//cta_ttl
	wp.customize(
		'cta_ttl', function( value ) {
			value.bind(
				function( newval ) {
					$( '.call_action .ttl' ).text( newval );
				}
			);
		}
	);
	
	
	//cta_email
	wp.customize(
		'cta_email', function( value ) {
			value.bind(
				function( newval ) {
					$( '.call_action .text' ).text( newval );
				}
			);
		}
	);
	
	//cta_btn_lbl
	wp.customize(
		'cta_btn_lbl', function( value ) {
			value.bind(
				function( newval ) {
					$( '.call_action .button a' ).text( newval );
				}
			);
		}
	);
	
	
	
	
	//custom_title
	wp.customize(
		'custom_title', function( value ) {
			value.bind(
				function( newval ) {
					$( '.custom-wrapper .heading-default h6' ).text( newval );
				}
			);
		}
	);
	
	
	//custom_subtitle
	wp.customize(
		'custom_subtitle', function( value ) {
			value.bind(
				function( newval ) {
					$( '.custom-wrapper .heading-default h3' ).text( newval );
				}
			);
		}
	);
	
	//custom_description
	wp.customize(
		'custom_description', function( value ) {
			value.bind(
				function( newval ) {
					$( '.custom-wrapper .heading-default p' ).text( newval );
				}
			);
		}
	);
	
	
	
	//blog_title
	wp.customize(
		'blog_title', function( value ) {
			value.bind(
				function( newval ) {
					$( '.home-blog .heading-default h6' ).text( newval );
				}
			);
		}
	);
	
	
	//blog_subtitle
	wp.customize(
		'blog_subtitle', function( value ) {
			value.bind(
				function( newval ) {
					$( '.home-blog .heading-default h3' ).text( newval );
				}
			);
		}
	);
	
	//blog_description
	wp.customize(
		'blog_description', function( value ) {
			value.bind(
				function( newval ) {
					$( '.home-blog .heading-default p' ).text( newval );
				}
			);
		}
	);
	
	
	
	//pg_packages_ttl
	wp.customize(
		'pg_packages_ttl', function( value ) {
			value.bind(
				function( newval ) {
					$( '.pg-packages-offers .heading-default h6' ).text( newval );
				}
			);
		}
	);
	
	
	//pg_packages_sub
	wp.customize(
		'pg_packages_sub', function( value ) {
			value.bind(
				function( newval ) {
					$( '.pg-packages-offers .heading-default h3' ).text( newval );
				}
			);
		}
	);
	
	//pg_packages_desc
	wp.customize(
		'pg_packages_desc', function( value ) {
			value.bind(
				function( newval ) {
					$( '.pg-packages-offers .heading-default p' ).text( newval );
				}
			);
		}
	);
	
	
	//pg_packages_offer_ttl
	wp.customize(
		'pg_packages_offer_ttl', function( value ) {
			value.bind(
				function( newval ) {
					$( '.pg-packages-soffers .heading-default h6' ).text( newval );
				}
			);
		}
	);
	
	
	//pg_packages_offer_sub
	wp.customize(
		'pg_packages_offer_sub', function( value ) {
			value.bind(
				function( newval ) {
					$( '.pg-packages-soffers .heading-default h3' ).text( newval );
				}
			);
		}
	);
	
	//pg_packages_offer_desc
	wp.customize(
		'pg_packages_offer_desc', function( value ) {
			value.bind(
				function( newval ) {
					$( '.pg-packages-soffers .heading-default p' ).text( newval );
				}
			);
		}
	);
	
	
	
	
	//pg_gallery_ttl
	wp.customize(
		'pg_gallery_ttl', function( value ) {
			value.bind(
				function( newval ) {
					$( '.gallery-page .heading-default h6' ).text( newval );
				}
			);
		}
	);
	
	
	//pg_gallery_sub
	wp.customize(
		'pg_gallery_sub', function( value ) {
			value.bind(
				function( newval ) {
					$( '.gallery-page .heading-default h3' ).text( newval );
				}
			);
		}
	);
	
	//pg_gallery_desc
	wp.customize(
		'pg_gallery_desc', function( value ) {
			value.bind(
				function( newval ) {
					$( '.gallery-page .heading-default p' ).text( newval );
				}
			);
		}
	);
	
	
	//pg_room_ttl
	wp.customize(
		'pg_room_ttl', function( value ) {
			value.bind(
				function( newval ) {
					$( '.room-page .heading-default h6' ).text( newval );
				}
			);
		}
	);
	
	
	//pg_room_sub
	wp.customize(
		'pg_room_sub', function( value ) {
			value.bind(
				function( newval ) {
					$( '.room-page .heading-default h3' ).text( newval );
				}
			);
		}
	);
	
	//pg_room_desc
	wp.customize(
		'pg_room_desc', function( value ) {
			value.bind(
				function( newval ) {
					$( '.room-page .heading-default p' ).text( newval );
				}
			);
		}
	);
	
	
	
	//contact_pg_ct_ttl
	wp.customize(
		'contact_pg_ct_ttl', function( value ) {
			value.bind(
				function( newval ) {
					$( '.contact-section .heading-default h6' ).text( newval );
				}
			);
		}
	);
	
	
	//contact_pg_ct_subttl
	wp.customize(
		'contact_pg_ct_subttl', function( value ) {
			value.bind(
				function( newval ) {
					$( '.contact-section .heading-default h3' ).text( newval );
				}
			);
		}
	);
	
	//contact_pg_ct_desc
	wp.customize(
		'contact_pg_ct_desc', function( value ) {
			value.bind(
				function( newval ) {
					$( '.contact-section .heading-default p' ).text( newval );
				}
			);
		}
	);
	
	//contact_pg_form_ttl
	wp.customize(
		'contact_pg_form_ttl', function( value ) {
			value.bind(
				function( newval ) {
					$( '.contact-form .heading-form h3' ).text( newval );
				}
			);
		}
	);
	
	/**
	 * Breadcrumb Typography
	 */
	wp.customize( 'breadcrumb_min_height', function( value ) {
		value.bind( function( size ) {
			jQuery( '.breadcrumbs' ).css( 'min-height', size + 'px' );
		} );
	} );
	
	/**
	 * Body font size
	 */
	wp.customize( 'aravalli_body_font_size', function( value ) {
		value.bind( function( size ) {
			jQuery( 'body' ).css( 'font-size', size + 'px' );
		} );
	} );
	
	
	/**
	 * Body font style
	 */
	wp.customize( 'aravalli_body_font_style', function( value ) {
		value.bind( function( font_style ) {
			jQuery( 'body' ).css( 'font-style', font_style );
		} );
	} );
	
	/**
	 * Body text tranform
	 */
	wp.customize( 'aravalli_body_text_transform', function( value ) {
		value.bind( function( text_tranform ) {
			jQuery( 'body' ).css( 'text-transform', text_tranform );
		} );
	} );
	
	/**
	 * aravalli_body_line_height
	 */
	
	wp.customize( 'aravalli_body_line_height', function( value ) {
		value.bind( function( line_height ) {
			jQuery( 'body' ).css( 'line-height', line_height );
		} );
	} );
	
	/**
	 * H1 font family
	 */
	wp.customize( 'aravalli_h1_font_family', function( value ) {
		value.bind( function( font_family ) {
			jQuery( 'h1' ).css( 'font-family', font_family );
		} );
	} );
	
	/**
	 * H1 font size
	 */
	wp.customize( 'aravalli_h1_font_size', function( value ) {
		value.bind( function( size ) {
			jQuery( 'h1' ).css( 'font-size', size + 'px' );
		} );
	} );
	
	
	/**
	 * H1 font style
	 */
	wp.customize( 'aravalli_h1_font_style', function( value ) {
		value.bind( function( font_style ) {
			jQuery( 'h1' ).css( 'font-style', font_style );
		} );
	} );
	
	/**
	 * H1 text tranform
	 */
	wp.customize( 'aravalli_h1_text_transform', function( value ) {
		value.bind( function( text_tranform ) {
			jQuery( 'h1' ).css( 'text-transform', text_tranform );
		} );
	} );
	
	/**
	 * H1 line height
	 */
	wp.customize( 'aravalli_h1_line_height', function( value ) {
		value.bind( function( line_height ) {
			jQuery( 'h1' ).css( 'line-height', line_height );
		} );
	} );
	
	/**
	 * H2 font family
	 */
	wp.customize( 'aravalli_h2_font_family', function( value ) {
		value.bind( function( font_family ) {
			jQuery( 'h2' ).css( 'font-family', font_family );
		} );
	} );
	
	/**
	 * H2 font size
	 */
	wp.customize( 'aravalli_h2_font_size', function( value ) {
		value.bind( function( size ) {
			jQuery( 'h2' ).css( 'font-size', size + 'px' );
		} );
	} );
	
	/**
	 * H2 font style
	 */
	wp.customize( 'aravalli_h2_font_style', function( value ) {
		value.bind( function( font_style ) {
			jQuery( 'h2' ).css( 'font-style', font_style );
		} );
	} );
	
	/**
	 * H2 text tranform
	 */
	wp.customize( 'aravalli_h2_text_transform', function( value ) {
		value.bind( function( text_tranform ) {
			jQuery( 'h2' ).css( 'text-transform', text_tranform );
		} );
	} );
	
	/**
	 * H2 line height
	 */
	wp.customize( 'aravalli_h2_line_height', function( value ) {
		value.bind( function( line_height ) {
			jQuery( 'h2' ).css( 'line-height', line_height );
		} );
	} );
	
	/**
	 * H3 font family
	 */
	wp.customize( 'aravalli_h3_font_family', function( value ) {
		value.bind( function( font_family ) {
			jQuery( 'h3' ).css( 'font-family', font_family );
		} );
	} );
	
	/**
	 * H3 font size
	 */
	wp.customize( 'aravalli_h3_font_size', function( value ) {
		value.bind( function( size ) {
			jQuery( 'h3' ).css( 'font-size', size + 'px' );
		} );
	} );
	
	/**
	 * H3 font style
	 */
	wp.customize( 'aravalli_h3_font_style', function( value ) {
		value.bind( function( font_style ) {
			jQuery( 'h3' ).css( 'font-style', font_style );
		} );
	} );
	
	/**
	 * H3 text tranform
	 */
	wp.customize( 'aravalli_h3_text_transform', function( value ) {
		value.bind( function( text_tranform ) {
			jQuery( 'h3' ).css( 'text-transform', text_tranform );
		} );
	} );
	
	/**
	 * H3 line height
	 */
	wp.customize( 'aravalli_h3_line_height', function( value ) {
		value.bind( function( line_height ) {
			jQuery( 'h3' ).css( 'line-height', line_height );
		} );
	} );
	
	/**
	 * H4 font family
	 */
	wp.customize( 'aravalli_h4_font_family', function( value ) {
		value.bind( function( font_family ) {
			jQuery( 'h4' ).css( 'font-family', font_family );
		} );
	} );
	
	/**
	 * H4 font size
	 */
	wp.customize( 'aravalli_h4_font_size', function( value ) {
		value.bind( function( size ) {
			jQuery( 'h4' ).css( 'font-size', size + 'px' );
		} );
	} );
	
	/**
	 * H4 font style
	 */
	wp.customize( 'aravalli_h4_font_style', function( value ) {
		value.bind( function( font_style ) {
			jQuery( 'h4' ).css( 'font-style', font_style );
		} );
	} );
	
	/**
	 * H4 text tranform
	 */
	wp.customize( 'aravalli_h4_text_transform', function( value ) {
		value.bind( function( text_tranform ) {
			jQuery( 'h4' ).css( 'text-transform', text_tranform );
		} );
	} );
	
	/**
	 * H4 line height
	 */
		wp.customize( 'aravalli_h4_line_height', function( value ) {
		value.bind( function( line_height ) {
			jQuery( 'h4' ).css( 'line-height', line_height );
		} );
	} );
	
	/**
	 * H5 font family
	 */
	wp.customize( 'aravalli_h5_font_family', function( value ) {
		value.bind( function( font_family ) {
			jQuery( 'h5' ).css( 'font-family', font_family );
		} );
	} );
	
	/**
	 * H5 font size
	 */
	wp.customize( 'aravalli_h5_font_size', function( value ) {
		value.bind( function( size ) {
			jQuery( 'h5' ).css( 'font-size', size + 'px' );
		} );
	} );
	
	/**
	 * H5 font style
	 */
	wp.customize( 'aravalli_h5_font_style', function( value ) {
		value.bind( function( font_style ) {
			jQuery( 'h5' ).css( 'font-style', font_style );
		} );
	} );
	
	/**
	 * H5 text tranform
	 */
	wp.customize( 'aravalli_h5_text_transform', function( value ) {
		value.bind( function( text_tranform ) {
			jQuery( 'h5' ).css( 'text-transform', text_tranform );
		} );
	} );
	
	/**
	 * H5 line height
	 */
		wp.customize( 'aravalli_h5_line_height', function( value ) {
		value.bind( function( line_height ) {
			jQuery( 'h5' ).css( 'line-height', line_height );
		} );
	} );
	
	/**
	 * H6 font family
	 */
	wp.customize( 'aravalli_h6_font_family', function( value ) {
		value.bind( function( font_family ) {
			jQuery( 'h6' ).css( 'font-family', font_family );
		} );
	} );
	
	/**
	 * H6 font size
	 */
	wp.customize( 'aravalli_h6_font_size', function( value ) {
		value.bind( function( size ) {
			jQuery( 'h6' ).css( 'font-size', size + 'px' );
		} );
	} );
	
	/**
	 * H6 font style
	 */
	wp.customize( 'aravalli_h6_font_style', function( value ) {
		value.bind( function( font_style ) {
			jQuery( 'h6' ).css( 'font-style', font_style );
		} );
	} );
	
	/**
	 * H6 text tranform
	 */
	wp.customize( 'aravalli_h6_text_transform', function( value ) {
		value.bind( function( text_tranform ) {
			jQuery( 'h6' ).css( 'text-transform', text_tranform );
		} );
	} );
	
	/**
	 * H6 line height
	 */
	wp.customize( 'aravalli_h6_line_height', function( value ) {
		value.bind( function( line_height ) {
			jQuery( 'h6' ).css( 'line-height', line_height );
		} );
	} );
	
} )( jQuery );