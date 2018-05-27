<?php

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class QS_CF7_atp_integration{

    /**
	 * The plugin identifier.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name unique plugin id.
	 */
	protected $plugin_name;

    /**
	 * save the instance of the plugin for static actions.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $instance    an instance of the class.
	 */
    public static $instance;

    /**
     * a reference to the admin class.
     *
     * @since    1.0.0
     * @access   protected
     * @var      object
     */
    public $admin;

	/**
     * a reference to the plugin status .
     *
     * @since    1.0.0
     * @access   protected
     * @var      object    $admin    an instance of the admin class.
     */
    private $woocommerce_is_active;
    /**
	 * Define the plugin functionality.
	 *
	 * set plugin name and version , and load dependencies
	 *
	 * @since    1.0.0
	 */
     public function __construct() {
         $this->plugin_name = 'wc-qs-wishlist';
         

         $this->load_dependencies();

         /**
          * Create an instance of the admin class
          * @var QS_CF7_api_admin
          */
         $this->admin = new QS_CF7_api_admin();
         $this->admin->plugin_name = $this->plugin_name;

         /**
          * save the instance for static actions
          *
          */
         self::$instance = $this;

     }
     public function init(){
         
     }
     /**
      * Loads the required plugin files
      * @return [type] [description]
      */
     public function load_dependencies(){
         /**
 		 * General global plugin functions
 	    */
         require_once QS_CF7_API_INCLUDES_PATH . 'class.cf7-helpers.php';
         /**
        * admin notices class
        */
         require_once QS_CF7_API_INCLUDES_PATH . 'class.qs-admin-notices.php';
         /**
        * admin notices clclass
        */
         require_once QS_CF7_API_INCLUDES_PATH . 'class-cf7-api-admin.php';
     }
     /**
      * Get the current plugin instance
      * @return [type] [description]
      */
     public static function get_instance() {
         if (self::$instance === null) {
             self::$instance = new self();
         }
         return self::$instance;
     }

}
