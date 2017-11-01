<?php
/**
 * WCS_Unit_Tests_Bootstrap
 *
 * @since 2.0
 */
class WCS_Unit_Tests_Bootstrap {

	/** @var \WCS_Unit_Tests_Bootstrap instance */
	protected static $instance = null;

	/** @var string directory where wordpress-tests-lib is installed */
	public $wp_tests_dir;

	/** @var string testing directory */
	public $tests_dir;

	/** @var string plugin directory */
	public $plugin_dir;

	// directory storing dependency plugins
	public $modules_dir;

	/**
	 * Setup the unit testing environment
	 *
	 * @since 2.0
	 */
	function __construct() {

		ini_set( 'display_errors','on' );
		error_reporting( E_ALL );

		$this->tests_dir    = dirname( __FILE__ );
		$this->plugin_dir   = dirname( $this->tests_dir );
		$this->modules_dir  = dirname( dirname( $this->tests_dir ) );
		$this->wp_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : $this->plugin_dir . '/tmp/wordpress-tests-lib';

		$_SERVER['REMOTE_ADDR'] = ( isset( $_SERVER['REMOTE_ADDR'] ) ) ? $_SERVER['REMOTE_ADDR'] : '';
		$_SERVER['SERVER_NAME'] = ( isset( $_SERVER['SERVER_NAME'] ) ) ? $_SERVER['SERVER_NAME'] : 'wcsg_test';

		// load test function so tests_add_filter() is available
		require_once( $this->wp_tests_dir  . '/includes/functions.php' );

		// load WC
		tests_add_filter( 'muplugins_loaded', array( $this, 'load_wc' ) );

		// load Subscriptions after $this->load_wc() finishes and calls 'woocommerce_init'
		tests_add_filter( 'woocommerce_init', array( $this, 'load_wcs' ) );

		// install WC
		tests_add_filter( 'setup_theme', array( $this, 'install_wc' ) );

		// install WCS
		tests_add_filter( 'setup_theme', array( $this, 'install_wcs' ) );

		// load the WP testing environment
		require_once( $this->wp_tests_dir . '/includes/bootstrap.php' );

		// load testing framework
		$this->includes();

		// load WooCommerce Subscriptions Gifting
		require_once( $this->plugin_dir . '/woocommerce-subscriptions-gifting.php' );
	}

	/**
	 * Load WooCommerce
	 *
	 * @since 2.0
	 */
	public function load_wc() {
		require_once( $this->modules_dir . '/woocommerce/woocommerce.php' );
	}

	/**
	 * Load Subscriptions
	 *
	 * @since  2.0
	 */
	public function load_wcs() {
		require_once( $this->modules_dir . '/woocommerce-subscriptions/woocommerce-subscriptions.php' );
	}

	/**
	 * Load WooCommerce for testing
	 *
	 * @since 2.0
	 */
	function install_wc() {

		echo "Installing WooCommerce..." . PHP_EOL;

		define( 'WP_UNINSTALL_PLUGIN', true );

		include( $this->modules_dir . '/woocommerce/uninstall.php' );

		WC_Install::install();

		// reload capabilities after install, see https://core.trac.wordpress.org/ticket/28374
		if ( version_compare( $GLOBALS['wp_version'], '4.9', '>=' ) && method_exists( $GLOBALS['wp_roles'], 'for_site' ) ) {
			/** @see: https://core.trac.wordpress.org/ticket/38645 */
			$GLOBALS['wp_roles']->for_site();
		} elseif ( version_compare( $GLOBALS['wp_version'], '4.7', '>=' ) ) {
			// Do the right thing based on https://core.trac.wordpress.org/ticket/23016
			$GLOBALS['wp_roles'] = new WP_Roles();
		} else {
			// Fall back to the old method.
			$GLOBALS['wp_roles']->reinit();
		}

		// Set Subscriptions install data so that Gifting won't exit early
		$active_plugins   = get_option( 'active_plugins', array() );
		$active_plugins[] = 'woocommerce/woocommerce.php';
		update_option( 'active_plugins', $active_plugins );

		WC()->init();

		echo "WooCommerce Finished Installing..." . PHP_EOL;
	}

	/**
	 * Set default values on subscriptions
	 *
	 * @since  2.0
	 */
	public function install_wcs() {

		echo "Installing Subscriptions..." . PHP_EOL;

		WC_Subscriptions::maybe_activate_woocommerce_subscriptions();

		WC_Subscriptions::load_dependant_classes();

		// Set Subscriptions install data so that Gifting won't exit early
		$active_plugins   = get_option( 'active_plugins', array() );
		$active_plugins[] = 'woocommerce-subscriptions/woocommerce-subscriptions.php';
		update_option( 'active_plugins', $active_plugins );
		update_option( WC_Subscriptions_Admin::$option_prefix . '_active_version', WC_Subscriptions::$version );

		WC_Subscriptions::register_order_types();

		// set active and inactive subscriber roles
		update_option( WC_Subscriptions_Admin::$option_prefix . '_subscriber_role', 'subscriber' );
		update_option( WC_Subscriptions_Admin::$option_prefix . '_cancelled_role', 'customer' );

		echo "Subscriptions Finished Installing..." . PHP_EOL;
	}

	/**
	 * Load test cases and factories
	 *
	 * @since 2.0
	 */
	public function includes() {

		// Load WC Helper functions/Frameworks and Factories
		require_once( $this->modules_dir . '/woocommerce/tests/framework/factories/class-wc-unit-test-factory-for-webhook.php' );
		require_once( $this->modules_dir . '/woocommerce/tests/framework/factories/class-wc-unit-test-factory-for-webhook-delivery.php' );

		// Load WC Framework
		require_once( $this->modules_dir . '/woocommerce/tests/framework/class-wc-unit-test-factory.php' );
		require_once( $this->modules_dir . '/woocommerce/tests/framework/class-wc-mock-session-handler.php' );
		require_once( $this->modules_dir . '/woocommerce/tests/framework/class-wc-unit-test-case.php' );
		require_once( $this->modules_dir . '/woocommerce/tests/framework/class-wc-api-unit-test-case.php' );

		// LOAD WC-API Files
		if ( version_compare( WC_VERSION, '2.6', '<' ) ) {
			require_once( $this->modules_dir . '/woocommerce/includes/api/class-wc-api-server.php' );
			require_once( $this->modules_dir . '/woocommerce/includes/api/class-wc-api-resource.php' );
			require_once( $this->modules_dir . '/woocommerce/includes/api/class-wc-api-orders.php' );
		}

		// Load WCS required classes
		require_once( $this->modules_dir . '/woocommerce-subscriptions/includes/class-wc-subscription.php' );
		require_once( $this->modules_dir . '/woocommerce-subscriptions/includes/class-wc-product-subscription.php' );
		require_once( $this->modules_dir . '/woocommerce-subscriptions/includes/class-wcs-api.php' );

		// Load WCS Frameworks
		require_once( $this->modules_dir . '/woocommerce-subscriptions/tests/framework/class-wcs-unit-test-case.php' );
		require_once( $this->modules_dir . '/woocommerce-subscriptions/tests/framework/class-wcs-unit-test-factory.php' );
		require_once( $this->modules_dir . '/woocommerce-subscriptions/tests/framework/class-wcs-api-unit-test-case.php' );

		// Load WC Helper Functions
		require_once( $this->modules_dir . '/woocommerce/tests/framework/helpers/class-wc-helper-product.php' );
		require_once( $this->modules_dir . '/woocommerce/tests/framework/helpers/class-wc-helper-coupon.php' );
		require_once( $this->modules_dir . '/woocommerce/tests/framework/helpers/class-wc-helper-fee.php' );
		require_once( $this->modules_dir . '/woocommerce/tests/framework/helpers/class-wc-helper-shipping.php' );
		require_once( $this->modules_dir . '/woocommerce/tests/framework/helpers/class-wc-helper-customer.php' );

		// Load WCS Helper Functions
		require_once( $this->modules_dir . '/woocommerce-subscriptions/tests/framework/helpers/class-wcs-helper-subscription.php' );
		require_once( $this->modules_dir . '/woocommerce-subscriptions/tests/framework/helpers/class-wcs-helper-product.php' );

		// Load WCSG Frameworks
		// require_once( 'framework/class-wcsg-unit-test-case.php' );
		// require_once( 'framework/class-wcsg-unit-test-factory.php' );

		// Load WCSG Helper Functions
		// require_once( 'framework/helpers/class-wcsg-helper-product.php' );
	}

	/**
	 * Get the single class instance
	 *
	 * @since 2.0
	 * @return WCS_Unit_Tests_Bootstrap
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

}

WCS_Unit_Tests_Bootstrap::instance();
