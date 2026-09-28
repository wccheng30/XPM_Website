<?php

class Aravalli_Customizer_Notify {

	private $recommended_actions;

	
	private $recommended_plugins;

	
	private static $instance;

	
	private $recommended_actions_title;

	
	private $recommended_plugins_title;

	
	private $dismiss_button;

	
	private $install_button_label;

	
	private $activate_button_label;

	
	private $aravalli_deactivate_button_label;
	
	
	private $config;

	
	public static function init( $config ) {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Aravalli_Customizer_Notify ) ) {
			self::$instance = new Aravalli_Customizer_Notify;
			if ( ! empty( $config ) && is_array( $config ) ) {
				self::$instance->config = $config;
				self::$instance->setup_config();
				self::$instance->setup_actions();
			}
		}

	}

	
	public function setup_config() {

		global $aravalli_customizer_notify_recommended_plugins;
		global $aravalli_customizer_notify_recommended_actions;

		global $install_button_label;
		global $activate_button_label;
		global $aravalli_deactivate_button_label;

		$this->recommended_actions = isset( $this->config['recommended_actions'] ) ? $this->config['recommended_actions'] : array();
		$this->recommended_plugins = isset( $this->config['recommended_plugins'] ) ? $this->config['recommended_plugins'] : array();

		$this->recommended_actions_title = isset( $this->config['recommended_actions_title'] ) ? $this->config['recommended_actions_title'] : '';
		$this->recommended_plugins_title = isset( $this->config['recommended_plugins_title'] ) ? $this->config['recommended_plugins_title'] : '';
		$this->dismiss_button            = isset( $this->config['dismiss_button'] ) ? $this->config['dismiss_button'] : '';

		$aravalli_customizer_notify_recommended_plugins = array();
		$aravalli_customizer_notify_recommended_actions = array();

		if ( isset( $this->recommended_plugins ) ) {
			$aravalli_customizer_notify_recommended_plugins = $this->recommended_plugins;
		}

		if ( isset( $this->recommended_actions ) ) {
			$aravalli_customizer_notify_recommended_actions = $this->recommended_actions;
		}

		$install_button_label    = isset( $this->config['install_button_label'] ) ? $this->config['install_button_label'] : '';
		$activate_button_label   = isset( $this->config['activate_button_label'] ) ? $this->config['activate_button_label'] : '';
		$aravalli_deactivate_button_label = isset( $this->config['aravalli_deactivate_button_label'] ) ? $this->config['aravalli_deactivate_button_label'] : '';

	}

	
	public function setup_actions() {

		// Register the section
		add_action( 'customize_register', array( $this, 'aravalli_plugin_notification_customize_register' ) );

		// Enqueue scripts and styles
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'aravalli_customizer_notify_scripts_for_customizer' ), 0 );

		/* ajax callback for dismissable recommended actions */
		add_action( 'wp_ajax_quality_customizer_notify_dismiss_action', array( $this, 'aravalli_customizer_notify_dismiss_recommended_action_callback' ) );

		add_action( 'wp_ajax_ti_customizer_notify_dismiss_recommended_plugins', array( $this, 'aravalli_customizer_notify_dismiss_recommended_plugins_callback' ) );

	}

	
	public function aravalli_customizer_notify_scripts_for_customizer() {

		wp_enqueue_style( 'aravalli-customizer-notify-css', get_template_directory_uri() . '/inc/customizer-notify/css/aravalli-customizer-notify.css', array());

		wp_enqueue_style( 'aravalli-plugin-install' );
		wp_enqueue_script( 'aravalli-plugin-install' );
		wp_add_inline_script( 'aravalli-plugin-install', 'var pagenow = "customizer";' );

		wp_enqueue_script( 'aravalli-updates' );

		wp_enqueue_script( 'aravalli-customizer-notify-js', get_template_directory_uri() . '/inc/customizer-notify/js/aravalli-customizer-notify.js', array( 'customize-controls' ));
		wp_localize_script(
			'aravalli-customizer-notify-js', 'AravalliCustomizercompanionObject', array(
				'aravalli_ajaxurl'            => esc_url(admin_url( 'admin-ajax.php' )),
				'aravalli_template_directory' => esc_url(get_template_directory_uri()),
				'aravalli_base_path'          => esc_url(admin_url()),
				'aravalli_activating_string'  => __( 'Activating', 'aravalli' ),
			)
		);

	}

	
	public function aravalli_plugin_notification_customize_register( $wp_customize ) {

		
		require_once get_template_directory() . '/inc/customizer-notify/aravalli-customizer-notify-section.php';

		$wp_customize->register_section_type( 'Aravalli_Customizer_Notify_Section' );

		$wp_customize->add_section(
			new Aravalli_Customizer_Notify_Section(
				$wp_customize,
				'Aravalli-customizer-notify-section',
				array(
					'title'          => $this->recommended_actions_title,
					'plugin_text'    => $this->recommended_plugins_title,
					'dismiss_button' => $this->dismiss_button,
					'priority'       => 0,
				)
			)
		);

	}

	
	public function aravalli_customizer_notify_dismiss_recommended_action_callback() {

		global $aravalli_customizer_notify_recommended_actions;

		$action_id = ( isset( $_GET['id'] ) ) ? $_GET['id'] : 0;

		echo esc_html($action_id); 

		if ( ! empty( $action_id ) ) {

			
			if ( get_theme_mod( 'aravalli_customizer_notify_show' ) ) {

				$aravalli_customizer_notify_show_recommended_actions = get_theme_mod( 'aravalli_customizer_notify_show' );
				switch ( $_GET['todo'] ) {
					case 'add':
						$aravalli_customizer_notify_show_recommended_actions[ $action_id ] = true;
						break;
					case 'dismiss':
						$aravalli_customizer_notify_show_recommended_actions[ $action_id ] = false;
						break;
				}
				echo esc_html($aravalli_customizer_notify_show_recommended_actions);
				
			} else {
				$aravalli_customizer_notify_show_recommended_actions = array();
				if ( ! empty( $aravalli_customizer_notify_recommended_actions ) ) {
					foreach ( $aravalli_customizer_notify_recommended_actions as $aravalli_lite_customizer_notify_recommended_action ) {
						if ( $aravalli_lite_customizer_notify_recommended_action['id'] == $action_id ) {
							$aravalli_customizer_notify_show_recommended_actions[ $aravalli_lite_customizer_notify_recommended_action['id'] ] = false;
						} else {
							$aravalli_customizer_notify_show_recommended_actions[ $aravalli_lite_customizer_notify_recommended_action['id'] ] = true;
						}
					}
					echo esc_html($aravalli_customizer_notify_show_recommended_actions);
				}
			}
		}
		die(); 
	}

	
	public function aravalli_customizer_notify_dismiss_recommended_plugins_callback() {

		$action_id = ( isset( $_GET['id'] ) ) ? $_GET['id'] : 0;

		echo esc_html($action_id); 

		if ( ! empty( $action_id ) ) {

			$aravalli_lite_customizer_notify_show_recommended_plugins = get_theme_mod( 'aravalli_customizer_notify_show_recommended_plugins' );

			switch ( $_GET['todo'] ) {
				case 'add':
					$aravalli_lite_customizer_notify_show_recommended_plugins[ $action_id ] = false;
					break;
				case 'dismiss':
					$aravalli_lite_customizer_notify_show_recommended_plugins[ $action_id ] = true;
					break;
			}
			echo esc_html($aravalli_customizer_notify_show_recommended_actions);
		}
		die(); 
	}

}
