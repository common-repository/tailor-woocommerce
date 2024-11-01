<?php

/**
 * Plugin Name: Tailor - WooCommerce extension
 * Plugin URI: http://www.gettailor.com
 * Description: Adds WooCommerce support and shop functionality to the Tailor plugin.
 * Version: 1.2.1
 * Author: The Tailor Team
 * Author URI:  http://www.gettailor.com
 * Text Domain: tailor-woocommerce
 *
 * @package Tailor WooCommerce
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'Tailor_WooCommerce' ) ) {

    /**
     * Tailor WooCommerce class.
     *
     * @since 1.0.0
     */
    class Tailor_WooCommerce {

        /**
         * Tailor WooCommerce instance.
         *
         * @since 1.0.0
         *
         * @access private
         * @var Tailor_WooCommerce
         */
        private static $instance;

        /**
         * The plugin version number.
         *
         * @since 1.0.0
         *
         * @access private
         * @var string
         */
        private static $version;

	    /**
	     * The plugin basename.
	     *
	     * @since 1.0.0
	     *
	     * @access private
	     * @var string
	     */
	    private static $plugin_basename;

        /**
         * The plugin name.
         *
         * @since 1.0.0
         *
         * @access private
         * @var string
         */
        private static $plugin_name;

        /**
         * The plugin directory.
         *
         * @since 1.0.0
         *
         * @access private
         * @var string
         */
        private static $plugin_dir;

        /**
         * The plugin URL.
         *
         * @since 1.0.0
         *
         * @access private
         * @var string
         */
        private static $plugin_url;

	    /**
	     * The minimum required version of Tailor.
	     *
	     * @since 1.2.1
	     * @access private
	     * @var string
	     */
	    private static $required_tailor_version = '1.7.2';

        /**
         * Returns the Tailor instance.
         *
         * @since 1.0.0
         *
         * @return Tailor_WooCommerce
         */
        public static function instance() {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Constructor.
         *
         * @since 1.0.0
         */
	    public function __construct() {

            $plugin_data = get_file_data( __FILE__, array( 'Plugin Name', 'Version' ) );

            self::$plugin_basename = plugin_basename( __FILE__ );
            self::$plugin_name = array_shift( $plugin_data );
            self::$version = array_shift( $plugin_data );
	        self::$plugin_dir = trailingslashit( plugin_dir_path( __FILE__ ) );
	        self::$plugin_url = trailingslashit( plugin_dir_url( __FILE__ ) );

		    add_action( 'plugins_loaded', array( $this, 'init' ) );
        }

	    /**
	     * Initializes the plugin.
	     *
	     * @since 1.0.0
	     */
	    public function init() {
		    if (
			    ! class_exists( 'Tailor' ) ||                                                       // Tailor is not active, or
			    ! version_compare( tailor()->version(), self::$required_tailor_version, '>=' )      // An unsupported version is being used
		    ) {
			    add_action( 'admin_notices', array( $this, 'display_version_notice' ) );
			    return;
		    }
		    
		    load_plugin_textdomain( 'tailor-woocommerce', false, $this->plugin_dir() . 'languages/' );

		    $this->load_directory( 'shortcodes' );
		    $this->add_actions();
	    }

	    /**
	     * Displays an admin notice if an unsupported version of Tailor is being used.
	     *
	     * @since 1.2.0
	     */
	    public function display_version_notice() {
		    printf(
			    '<div class="notice notice-warning is-dismissible">' .
			        '<p>%s</p>' .
			    '</div>',
			    sprintf(
				    __( 'Please ensure that Tailor %s (or newer) is active to use the WooCommerce extension.', 'tailor-woocommerce' ),
				    self::$required_tailor_version
			    )
		    );
	    }

	    /**
	     * Adds required action hooks.
	     *
	     * @since 1.0.0
	     *
	     * @access protected
	     */
	    protected function add_actions() {
		    add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		    add_action( 'tailor_canvas_enqueue_scripts', array( $this, 'enqueue_scripts' ), 99 );
		    add_action( 'tailor_enqueue_sidebar_styles', array( $this, 'enqueue_sidebar_styles' ) );
		    
	        add_action( 'tailor_register_elements', array( $this, 'register_elements' ) );
		    add_filter( 'tailor_plugin_partial_paths', array( $this, 'register_partial_path' ) );
		    
		    add_filter( 'tailor_check_post', array( $this, 'prevent_woo_page_tailoring' ), 10, 2 );
        }

	    /**
	     * Disabled Tailoring of dynamic WooCommerce pages.
	     * 
	     * @since 1.0.1
	     * 
	     * @param $allowable
	     * @param $post
	     * 
	     * @return bool $allowable
	     */
	    public function prevent_woo_page_tailoring( $allowable, $post ) {

		    if ( function_exists( 'wc_get_page_id' ) && $allowable ) {
			    $allowable = ! in_array( $post->ID, array(
				    wc_get_page_id( 'shop' ),
				    wc_get_page_id( 'checkout' ),
				    wc_get_page_id( 'myaccount' ),
				    wc_get_page_id( 'cart' ),
			        )
			    );
		    }

		    return $allowable;
	    }

	    /**
	     * Enqueues frontend styles.
	     *
	     * @since 1.0.0
	     */
	    public function enqueue_styles() {

		    if ( apply_filters( 'tailor_enqueue_stylesheets', true ) ) {

			    $extension = SCRIPT_DEBUG ? '.css' : '.min.css';
			    
			    wp_enqueue_style(
				    'tailor-woocommerce-frontend-styles',
				    $this->plugin_url() . 'assets/css/frontend' . $extension,
				    array(),
				    $this->version()
			    );
		    }
	    }

	    /**
	     * Enqueues sidebar styles.
	     *
	     * @since 1.0.1
	     */
	    public function enqueue_sidebar_styles() {

		    $extension = SCRIPT_DEBUG ? '.css' : '.min.css';

		    wp_enqueue_style(
			    'tailor-woocommerce-sidebar-styles',
			    $this->plugin_url() . 'assets/css/sidebar' . $extension,
			    array(),
			    $this->version()
		    );
	    }

	    /**
	     * Enqueues Canvas scripts.
	     *
	     * @since 1.0.0
	     */
	    public function enqueue_scripts() {

		    $extension = SCRIPT_DEBUG ? '.js' : '.min.js';

		    wp_enqueue_script(
			    'tailor-woocommerce-canvas',
			    tailor_woocommerce()->plugin_url() . 'assets/js/dist/canvas' . $extension,
			    array( 'tailor-canvas' ),
			    tailor_woocommerce()->version(),
			    true
		    );
	    }

	    /**
	     * Registers the partial directory for this extension plugin.
	     *
	     * @since 1.0.0
	     *
	     * @param $paths
	     *
	     * @return array
	     */
	    public function register_partial_path( $paths ) {
		    $paths[] = $this->plugin_dir() . 'partials/';
		    return $paths;
	    }

	    /**
	     * Loads and registers the new Tailor elements and shortcodes.
	     *
	     * @since 1.0.0
	     *
	     * @param $element_manager Tailor_Elements
	     */
	    public function register_elements( $element_manager ) {

		    $this->load_directory( 'elements' );

		    $element_manager->add_element( 'tailor_products', array(
			    'label'             =>  __( 'Products', 'tailor-woocommerce' ),
			    'description'       =>  __( 'Your site\'s products.', 'tailor-woocommerce' ),
			    'badge'             =>  __( 'WooCommerce', 'tailor-woocommerce' ),
			    'active_callback'   =>  array( $this, 'is_woocommerce_active' ),
			    'dynamic'           =>  true,
		    ) );

		    $element_manager->add_element( 'tailor_pricing', array(
		        'label'             =>  __( 'Pricing', 'tailor-woocommerce' ),
		        'description'       =>  __( 'A pricing table column.', 'tailor-woocommerce' ),
		        'type'              =>  'wrapper',
		        'child_container'   =>  '.pricing__content',
		        'badge'             =>  __( 'WooCommerce', 'tailor-woocommerce' ),
		        'active_callback'   =>  array( $this, 'is_woocommerce_active' ),
		        'dynamic'           =>  true,
		    ) );

		    $element_manager->add_element( 'tailor_testimonial', array(
			    'label'             =>  __( 'Testimonial', 'tailor-woocommerce' ),
			    'description'       =>  __( 'A testimonial block.', 'tailor-woocommerce' ),
			    'type'              =>  'wrapper',
			    'child_container'   =>  '.testimonial__content',
			    'badge'             =>  __( 'WooCommerce', 'tailor-woocommerce' ),
			    'active_callback'   =>  array( $this, 'is_woocommerce_active' ),
		    ) );
	    }

        /**
         * Returns the version number of the plugin.
         *
         * @since 1.0.0
         *
         * @return string
         */
        public function version() {
            return self::$version;
        }

	    /**
	     * Returns the plugin basename.
	     *
	     * @since 1.0.0
	     *
	     * @return string
	     */
	    public function plugin_basename() {
		    return self::$plugin_basename;
	    }

        /**
         * Returns the plugin name.
         *
         * @since 1.0.0
         *
         * @return string
         */
        public function plugin_name() {
            return self::$plugin_name;
        }

        /**
         * Returns the plugin directory.
         *
         * @since 1.0.0
         *
         * @return string
         */
        public function plugin_dir() {
            return self::$plugin_dir;
        }

        /**
         * Returns the plugin URL.
         *
         * @since 1.0.0
         *
         * @return string
         */
        public function plugin_url() {
            return self::$plugin_url;
        }

        /**
         * Returns true if the WooCommerce plugin is active.
         *
         * @since 1.0.0
         *
         * @return bool
         */
        public function is_woocommerce_active() {
	        return class_exists( 'woocommerce' ) ? true : false;
        }

	    /**
	     * Loads all PHP files in a given directory.
	     *
	     * @since 1.0.0
	     */
	    public function load_directory( $directory_name ) {
		    $path = trailingslashit( $this->plugin_dir() . 'includes/' . $directory_name );
		    $file_names = glob( $path . '*.php' );
		    foreach ( $file_names as $filename ) {
			    if ( file_exists( $filename ) ) {
				    require_once $filename;
			    }
		    }
	    }
    }
}

if ( ! function_exists( 'tailor_woocommerce' ) ) {

	/**
	 * Returns the Tailor WooCommerce instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Tailor_WooCommerce
	 */
	function tailor_woocommerce() {
		return Tailor_WooCommerce::instance();
	}
}

tailor_woocommerce();