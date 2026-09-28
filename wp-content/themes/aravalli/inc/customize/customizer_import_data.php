<?php
class aravalli_import_dummy_data {

	private static $instance;

	public static function init( ) {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof aravalli_import_dummy_data ) ) {
			self::$instance = new aravalli_import_dummy_data;
			self::$instance->aravalli_setup_actions();
		}

	}

	/**
	 * Setup the class props based on the config array.
	 */
	

	/**
	 * Setup the actions used for this class.
	 */
	public function aravalli_setup_actions() {

		// Enqueue scripts
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'aravalli_import_customize_scripts' ), 0 );

	}
	
	

	public function aravalli_import_customize_scripts() {

	wp_enqueue_script( 'aravalli-import-customizer-js', get_template_directory_uri() . '/assets/js/aravalli-import-customizer.js', array( 'customize-controls' ) );
	}
}

$aravalli_import_customizers = array(

		'import_data' => array(
			'recommended' => true,
			
		),
);
aravalli_import_dummy_data::init( apply_filters( 'aravalli_import_customizer', $aravalli_import_customizers ) );