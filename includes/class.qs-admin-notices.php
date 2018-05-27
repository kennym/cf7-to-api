<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class QS_Admin_notices{

    /**
     * Holds an array of notices to be displayed
     * @var [type]
     */
    private $notices;

    /**
     * The main class construcator
     */
    public function __construct(){

        $this->register_hooks();

        $this->get_plugin_options();

    }

    /**
     * Registers class filters and actions
     * @return [null]
     */
    public function register_hooks(){
        /**
         * display notices hook
         */
        add_action( 'admin_notices' , array( $this , 'qs_admin_notices' ) );
        /**
         * catch dismiss notice action and add it to the dismissed notices array
         */
        add_action( 'wp_ajax_qs_cf7_api_admin_dismiss_notices' , array( $this , 'qs_admin_dismiss_notices' ) );
        /**
         * enqueue admin scripts and styles
         */
        add_action( 'admin_enqueue_scripts', array( $this , 'load_admin_scripts' ) );
    }
    /**
     * load admin scripts and styles
     * @return [type] [description]
     */
    public function load_admin_scripts(){

        wp_register_style( 'qs-cf7-api-admin-notices-css', QS_CF7_API_ADMIN_CSS_URL . 'admin-notices-style.css' , false , '1.0.0' );

        wp_enqueue_style( 'qs-cf7-api-admin-notices-css' );

        wp_register_script( 'qs-cf7-api-admin-notices-script', QS_CF7_API_ADMIN_JS_URL . 'admin-notices-script.js' , array( 'jquery' ) , '1.0.0' , true );

        wp_enqueue_script( 'qs-cf7-api-admin-notices-script' );

    }
    /**
     * dismiss notice and save it to the plugin options
     * @return [type] [description]
     */
    public function qs_admin_dismiss_notices(){
        $id = isset( $_POST['id'] ) ? sanitize_text_field( $_POST['id'] ) : '';

        if( $id ){
            $this->notices_options['dismiss_notices'][$id] = true;

            $this->update_plugin_options();
        }

        die('updated');
    }
    /**
     * get the plugin admin options
     * @return [type] [description]
     */
    private function get_plugin_options(){

        $this->notices_options = apply_filters( 'get_plugin_options' , get_option( 'qs_cf7_api_notices_options' ) );

    }

    /**
     * save the plugin admin options
     * @return [type] [description]
     */
    private function update_plugin_options(){

        update_option( 'qs_cf7_api_notices_options' , $this->notices_options );

    }
    /**
     * display the notices that resides in the notices collection
     * @return [type] [description]
     */
    public function qs_admin_notices(){

        if( $this->notices ){
            foreach( $this->notices as $admin_notice ){
                /**
                 * only disply the notice if it wasnt dismiised in the past
                 */
                $classes = array(
                    "notice notice-{$admin_notice['type']}",
                    "is-dismissible"
                );

                $id = $admin_notice['id'];
                if( ! $admin_notice['dismissable_forever'] || (! isset( $this->notices_options['dismiss_notices'][$id] ) || ! $this->notices_options['dismiss_notices'][$id]) ){
                    if( $admin_notice['dismissable_forever'] ){
                        $classes[] = 'qs-cf7-api-dismiss-notice-forever';
                    }
                    echo "<div id='{$admin_notice['id']}' class='".implode( ' ' , $classes )."'>
                         <p>{$admin_notice['notice']}</p>
                     </div>";
                }

            }
        }
    }

    /**
     * adds notices to the class notices collection
     * @param array $notice an array of notice message and notice type
     * Types available are "error" "warning" "success" "info"
     */
    public function wp_add_notice( $notice = "" ){

        if( $notice ){
            $this->notices[] = array(
                'id'     => $notice['id'],
                'notice' => $notice['notice'],
                'type'   => isset( $notice['type'] ) ? $notice['type'] : 'warning',
                'dismissable_forever' => isset( $notice['dismissable_forever'] ) ? $notice['dismissable_forever'] : false
            );
        }

    }
}
