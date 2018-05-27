<?php


// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class QS_CF7_api_admin{


    /**
     * Holds the plugin options
     * @var [type]
     */
    private $options;

    /**
     * Holds athe admin notices class
     * @var [QS_Admin_notices]
     */
    private $admin_notices;

    /**
     * PLugn is active or not
     */
    private $plugin_active;
	/**
	 * API errors array
	 * @var [type]
	 */
	private $api_errors;

    public function __construct(){

		$this->textdomain = 'qs-cf7-api';

        $this->admin_notices = new QS_Admin_notices();

		$this->api_errors = array();

        $this->register_hooks();

    }
    /**
     * Check if contact form 7 is active
     * @return [type] [description]
     */
    public function verify_dependencies(){
        if( ! is_plugin_active('contact-form-7/wp-contact-form-7.php') ){
            $notice = array(
                'id'                  => 'cf7-not-active',
                'type'                => 'warning',
                'notice'              => __( 'Contact form 7 api integrations requires CONTACT FORM 7 Plugin to be installed and active' ,$this->textdomain ),
                'dismissable_forever' => false
            );

            $this->admin_notices->wp_add_notice( $notice );
        }
    }
    /**
     * Registers the required admin hooks
     * @return [type] [description]
     */
    public function register_hooks(){
        /**
         * Check if required plugins are active
         * @var [type]
         */
        add_action( 'admin_init', array( $this, 'verify_dependencies' ) );

        /*before sending email to user actions */
        add_action( 'wpcf7_before_send_mail', array( $this , 'qs_cf7_send_data_to_api' ) );

        /* adds another tab to contact form 7 screen */
        add_filter( "wpcf7_editor_panels" ,array( $this , "add_integrations_tab" ) , 1 , 1 );

        /* actions to handle while saving the form */
        add_action( "wpcf7_save_contact_form" ,array( $this , "qs_save_contact_form_details") , 10 , 1 );

        add_filter( "wpcf7_contact_form_properties" ,array( $this , "add_sf_properties" ) , 10 , 2 );
    }

    /**
     * Sets the form additional properties
     * @param [type] $properties   [description]
     * @param [type] $contact_form [description]
     */
    function add_sf_properties( $properties , $contact_form ){

        //add mail tags to allowed properties
        $properties["wpcf7_api_data"]     = isset($properties["wpcf7_api_data"]) ? $properties["wpcf7_api_data"]         : array();
        $properties["wpcf7_api_data_map"] = isset($properties["wpcf7_api_data_map"]) ? $properties["wpcf7_api_data_map"] : array();
		$properties["template"]           = isset($properties["template"]) ? $properties["template"]                     : '';
		$properties["json_template"]      = isset($properties["json_template"]) ? $properties["json_template"]                     : '';

        return $properties;
    }

    /**
     * Adds a new tab on conract form 7 screen
     * @param [type] $panels [description]
     */
    function add_integrations_tab($panels){

        $integration_panel = array(
            'title'    => __( 'API Integration' , $this->textdomain ),
            'callback' => array( $this, 'wpcf7_integrations' )
        );

        $panels["qs-cf7-api-integration"] = $integration_panel;

        return $panels;

    }
	/**
	 * Collect the mail tags from the form
	 * @return [type] [description]
	 */
	function get_mail_tags( $post ){
		$tags = apply_filters( 'qs_cf7_collect_mail_tags' , $post->scan_form_tags() );

		foreach ( (array) $tags as $tag ) {
			$type = trim( $tag['type'], ' *' );
			if ( empty( $type ) || empty( $tag['name'] ) ) {
				continue;
			} elseif ( ! empty( $args['include'] ) ) {
				if ( ! in_array( $type, $args['include'] ) ) {
					continue;
				}
			} elseif ( ! empty( $args['exclude'] ) ) {
				if ( in_array( $type, $args['exclude'] ) ) {
					continue;
				}
			}
			$mailtags[] = $tag;
		}

		return $mailtags;
	}
    /**
     * The admin tab display, settings and instructions to the admin user
     * @param  [type] $post [description]
     * @return [type]       [description]
     */
    function wpcf7_integrations( $post ) {

        $wpcf7_api_data                = $post->prop( 'wpcf7_api_data' );
        $wpcf7_api_data_map            = $post->prop( 'wpcf7_api_data_map' );
		$wpcf7_api_data_template 	   = $post->prop( 'template' );
		$wpcf7_api_json_data_template  = $post->prop( 'json_template' );
        $mail_tags                     = $this->get_mail_tags( $post );

        $wpcf7_api_data["base_url"]     = isset( $wpcf7_api_data["base_url"] ) ? $wpcf7_api_data["base_url"]         : '';
        $wpcf7_api_data["send_to_api"]  = isset( $wpcf7_api_data["send_to_api"] ) ? $wpcf7_api_data["send_to_api"]   : '';
		$wpcf7_api_data["input_type"]   = isset( $wpcf7_api_data["input_type"] ) ? $wpcf7_api_data["input_type"] 	 : 'params';
        $wpcf7_api_data["method"]       = isset( $wpcf7_api_data["method"] ) ? $wpcf7_api_data["method"]             : 'GET';
        $wpcf7_api_data["debug_log"]    = true;

        $debug_url                     = get_post_meta( $post->id() , 'qs_cf7_api_debug_url' , true );
        $debug_result                  = get_post_meta( $post->id() , 'qs_cf7_api_debug_result' , true );
        $debug_params                  = get_post_meta( $post->id() , 'qs_cf7_api_debug_params' , true );

		$error_logs 				   = get_post_meta( $post->id() , 'api_errors' , true );
		$xml_placeholder = __('*** THIS IS AN EXAMPLE ** USE YOUR XML ACCORDING TO YOUR API DOCUMENTATION **
			<update>
				<user clientid="" username="user_name" password="mypassword" />
				<reports>
					<report tag="NEW">
					<fields>
					   <field id="1" name="REFERENCE_ID" value="[your-name]" />
				       <field id="2" name="DESCRIPTION" value="[your-email]" />
					</field>
				</reports>
			</update>
			' , '');

		$json_placeholder = __('*** THIS IS AN EXAMPLE ** USE YOUR JSON ACCORDING TO YOUR API DOCUMENTATION **
			{ "name":"[fullname]", "age":30, "car":null }
			' , '');
        ?>


        <h2><?php echo esc_html( __( 'API Integration', $this->textdomain ) ); ?></h2>

        <fieldset>
            <?php do_action( 'before_base_fields' , $post ); ?>

            <div class="cf7_row">

                <label for="wpcf7-sf-send_to_api">
                    <input type="checkbox" id="wpcf7-sf-send_to_api" name="wpcf7-sf[send_to_api]" <?php checked( $wpcf7_api_data["send_to_api"] , "on" );?>/>
                    <?php _e( 'Send to api ?' , $this->textdomain );?>
                </label>

            </div>

            <div class="cf7_row">
                <label for="wpcf7-sf-base_url">
                    <?php _e( 'Base url' , $this->textdomain );?>
                    <input type="text" id="wpcf7-sf-base_url" name="wpcf7-sf[base_url]" class="large-text" value="<?php echo $wpcf7_api_data["base_url"];?>" />
                </label>
            </div>

			<hr>

			<div class="cf7_row">
				<label for="wpcf7-sf-input_type">
					<span class="cf7-label-in"><?php _e( 'Input type' , $this->textdomain ); ?></span>
					<select id="wpcf7-sf-input_type" name="wpcf7-sf[input_type]">
						<option value="params" <?php isset( $wpcf7_api_data["input_type"] ) ? selected( $wpcf7_api_data["input_type"] , 'params') : ''; ?>>
							<?php _e( 'Parameters - GET/POST' , $this->textdomain ); ?>
						</option>
						<option value="xml" <?php isset( $wpcf7_api_data["input_type"] ) ? selected( $wpcf7_api_data["input_type"] , 'xml') : ''; ?>>
							<?php _e( 'XML' , $this->textdomain ); ?>
						</option>
						<option value="json" <?php isset( $wpcf7_api_data["input_type"] ) ? selected( $wpcf7_api_data["input_type"] , 'json') : ''; ?>>
							<?php _e( 'json' , $this->textdomain ); ?>
						</option>
					</select>
				</label>
			</div>

            <div class="cf7_row" data-qsindex="params,json">
                <label for="wpcf7-sf-method">
                    <span class="cf7-label-in"><?php _e( 'Method' , $this->textdomain ); ?></span>
                    <select id="wpcf7-sf-base_url" name="wpcf7-sf[method]">
                        <option value="GET" <?php selected( $wpcf7_api_data["method"] , 'GET');?>>GET</option>
                        <option value="POST" <?php selected( $wpcf7_api_data["method"] , 'POST');?>>POST</option>
                    </select>
                </label>
            </div>

            <?php do_action( 'after_base_fields' , $post ); ?>

        </fieldset>


        <fieldset data-qsindex="params">
			<div class="cf7_row">
				<h2><?php echo esc_html( __( 'Form fields', $this->textdomain ) ); ?></h2>

	            <table>
	                <tr>
	                    <th><?php _e( 'Form fields' , $this->textdomain );?></th>
	                    <th><?php _e( 'API Key' , $this->textdomain );?></th>
						<th></th>
	                </tr>
		            <?php foreach( $mail_tags as $mail_tag) :?>

							<?php if( $mail_tag->type == 'checkbox' ):?>
								<?php foreach( $mail_tag->values as $checkbox_row ):?>
									<tr>
										<th style="text-align:left;"><?php echo $mail_tag->name;?> (<?php echo $checkbox_row;?>)</th>
					                    <td><input type="text" id="sf-<?php echo $name;?>" name="qs_wpcf7_api_map[<?php echo $mail_tag->name;?>][<?php echo $checkbox_row;?>]" class="large-text" value="<?php echo isset($wpcf7_api_data_map[$mail_tag->name][$checkbox_row]) ? $wpcf7_api_data_map[$mail_tag->name][$checkbox_row] : "";?>" /></td>
									</tr>
								<?php endforeach;?>
							<?php else:?>
								<tr>
				                    <th style="text-align:left;"><?php echo $mail_tag->name;?></th>
				                    <td><input type="text" id="sf-<?php echo $mail_tag->name;?>" name="qs_wpcf7_api_map[<?php echo $mail_tag->name;?>]" class="large-text" value="<?php echo isset($wpcf7_api_data_map[$mail_tag->name]) ? $wpcf7_api_data_map[$mail_tag->name] : "";?>" /></td>
								</tr>
							<?php endif;?>

		            <?php endforeach;?>

	            </table>

			</div>
        </fieldset>

		<fieldset data-qsindex="xml">
			<div class="cf7_row">
				<h2><?php echo esc_html( __( 'XML Template', $this->textdomain ) ); ?></h2>

				<legend>
					<?php foreach( $mail_tags as $mail_tag) : ?>
						<span class="xml_mailtag mailtag code">[<?php echo $mail_tag->name;?>]</span>
					<?php endforeach; ?>
				</legend>

				<textarea name="template" rows="12" dir="ltr" placeholder="<?php echo esc_attr( $xml_placeholder );?>"><?php echo isset( $wpcf7_api_data_template ) ? $wpcf7_api_data_template : ""; ?></textarea>
			</div>
		</fieldset>

		<fieldset data-qsindex="json">
			<div class="cf7_row">
				<h2><?php echo esc_html( __( 'JSON Template', $this->textdomain ) ); ?></h2>

				<legend>
					<?php foreach( $mail_tags as $mail_tag) : ?>
						<?php if( $mail_tag->type == 'checkbox'):?>
							<?php foreach( $mail_tag->values as $checkbox_row ):?>
								<span class="xml_mailtag mailtag code">[<?php echo $mail_tag->name;?>-<?php echo $checkbox_row;?>]</span>
							<?php endforeach;?>
						<?php else:?>
							<span class="xml_mailtag mailtag code">[<?php echo $mail_tag->name;?>]</span>
						<?php endif;?>
					<?php endforeach; ?>
				</legend>

				<textarea name="json_template" rows="12" dir="ltr" placeholder="<?php echo esc_attr( $json_placeholder );?>"><?php echo isset( $wpcf7_api_json_data_template ) ? $wpcf7_api_json_data_template : ""; ?></textarea>
			</div>
		</fieldset>

		<?php if( $wpcf7_api_data['debug_log'] ):?>
		<fieldset>
			<div class="cf7_row">
				<label class="debug-log-trigger">
					+ <?php _e( 'DEBUG LOG ( View last transmission attempt )' , $this->textdomain); ?>
				</label>
				<div class="debug-log-wrap">
					<h3 class="debug_log_title"><?php _e( 'LAST API CALL' , $this->textdomain );?></h3>
					<div class="debug_log">
						<h4><?php _e( 'Called url' , $this->textdomain );?>:</h4>
						<textarea rows="1"><?php echo trim(esc_attr( $debug_url ));?></textarea>
						<h4><?php _e( 'Params' , $this->textdomain );?>:</h4>
						<textarea rows="10"><?php print_r( $debug_params );?></textarea>
						<h4><?php _e( 'Remote server result' , $this->textdomain );?>:</h4>
						<textarea rows="10"><?php print_r( $debug_result );?></textarea>
						<h4><?php _e( 'Error logs' , $this->textdomain );?>:</h4>
						<textarea rows="10"><?php print_r( $error_logs );?></textarea>
					</div>
				</div>
			</div>
		</fieldset>
        <?php
		endif;
    }

   /**
     * Saves the API settings
     * @param  [type] $contact_form [description]
     * @return [type]               [description]
     */
    public function qs_save_contact_form_details( $contact_form ){

		$properties = $contact_form->get_properties();

		$properties['wpcf7_api_data']        = isset( $_POST["wpcf7-sf"] ) ? $_POST["wpcf7-sf"] : '';
		$properties['wpcf7_api_data_map']    = isset( $_POST["qs_wpcf7_api_map"] ) ? $_POST["qs_wpcf7_api_map"] : '';
		$properties['template'] 		 	 = isset( $_POST["template"] ) ? $_POST["template"] : '';
		$properties['json_template'] 		 = isset( $_POST["json_template"] ) ? $_POST["json_template"] : '';

		$contact_form->set_properties( $properties );

    }

    /**
     * The handler that will send the data to the api
     * @param  [type] $WPCF7_ContactForm [description]
     * @return [type]                    [description]
     */
    public function qs_cf7_send_data_to_api( $WPCF7_ContactForm ) {

		$this->clear_error_log( $WPCF7_ContactForm->id() );

        $submission = WPCF7_Submission::get_instance();

        $url                       = $submission->get_meta( 'url' );
		$this->post 		       = $WPCF7_ContactForm;
        $qs_cf7_data               = $WPCF7_ContactForm->prop( 'wpcf7_api_data' );
        $qs_cf7_data_map           = $WPCF7_ContactForm->prop( 'wpcf7_api_data_map' );
		$qs_cf7_data_template      = $WPCF7_ContactForm->prop( 'template' );
		$qs_cf7_data_json_template = $WPCF7_ContactForm->prop( 'json_template' );
		$qs_cf7_data['debug_log']  = true; //always save last call results for debugging


        /* check if the form is marked to be sent via API */
        if( isset( $qs_cf7_data["send_to_api"] ) && $qs_cf7_data["send_to_api"] == "on" ){

            $record_type = isset( $qs_cf7_data['input_type'] ) ? $qs_cf7_data['input_type'] : 'params';

			if( $record_type == 'json' ){
				$qs_cf7_data_template = $qs_cf7_data_json_template;
			}
            $record = $this->get_record( $submission , $qs_cf7_data_map , $record_type, $template = $qs_cf7_data_template );

            $record["url"] = $qs_cf7_data["base_url"];

            if( isset( $record["url"] ) && $record["url"] ){

                do_action( 'qs_cf7_api_before_sent_to_api' , $record );

                $response = $this->send_lead( $record , $qs_cf7_data['debug_log'] , $qs_cf7_data['method'] , $record_type );

				if( is_wp_error( $response ) ){
					$this->log_error( $response , $WPCF7_ContactForm->id() );
				}else{
					do_action( 'qs_cf7_api_after_sent_to_api' , $record , $response );
				}
            }
        }

    }
	/**
	 * CREATE ERROR LOG FOR RECENT API TRANSMISSION ATTEMPT
	 * @param  [type] $wp_error [description]
	 * @param  [type] $post_id  [description]
	 * @return [type]           [description]
	 */
	function log_error( $wp_error , $post_id ){
		//error log
		$this->api_errors[] = $wp_error;

		update_post_meta( $post_id , 'api_errors' , $this->api_errors );
	}

	function clear_error_log( $post_id ){
		delete_post_meta( $post_id , 'api_errors' );
	}
	/**
     * Convert the form keys to the API keys according to the mapping instructions
     * @param  [type] $submission      [description]
     * @param  [type] $qs_cf7_data_map [description]
     * @return [type]                  [description]
     */
	function get_record( $submission , $qs_cf7_data_map , $type = "params", $template = "" ){

        $submited_data = $submission->get_posted_data();
        $record = array();

        if( $type == "params" ){

            foreach( $qs_cf7_data_map as $form_key => $qs_cf7_form_key){

                if( $qs_cf7_form_key ){

					if( is_array( $qs_cf7_form_key ) ){
						//arrange checkbox arrays
						foreach( $submited_data[$form_key] as $value ){
							if( $value ){
								$record["fields"][$qs_cf7_form_key[$value]] = apply_filters( 'set_record_value' , $value , $qs_cf7_form_key );
							}
						}
					}else{
						$value = isset($submited_data[$form_key]) ? $submited_data[$form_key] : "";

						//flattan radio
						if( is_array( $value ) ){
							$value = reset( $value );
						}
						$record["fields"][$qs_cf7_form_key] = apply_filters( 'set_record_value' , $value , $qs_cf7_form_key );
					}

                }

            }

        } elseif( $type == "xml" || $type == "json" ){

            foreach( $qs_cf7_data_map as $form_key => $qs_cf7_form_key ){

				if( is_array( $qs_cf7_form_key ) ){
					//arrange checkbox arrays
					foreach( $submited_data[$form_key] as $value ){
						if( $value ){
							$value = apply_filters( 'set_record_value' , $value , $qs_cf7_form_key );

							$template = str_replace( "[{$form_key}-{$value}]", $value, $template );
						}
					}
				}else{
					$value = isset($submited_data[$form_key]) ? $submited_data[$form_key] : "";

					//flattan radio
					if( is_array( $value ) ){
						$value = reset( $value );
					}

					$template = str_replace( "[{$form_key}]", $value, $template );
				}
            }

			//clean unchanged tags
			foreach( $qs_cf7_data_map as $form_key => $qs_cf7_form_key ){
				if( is_array( $qs_cf7_form_key ) ){
					foreach( $qs_cf7_form_key as $field_suffix=> $api_name ){
						$template = str_replace( "[{$form_key}-{$field_suffix}]", '', $template );
					}
				}

			}

            $record["fields"] = $template;

        }

		$record = apply_filters( 'cf7api_create_record', $record , $submited_data , $qs_cf7_data_map , $type , $template );

        return $record;
    }


	/**
     * Send the lead using wp_remote
     * @param  [type]  $record [description]
     * @param  boolean $debug  [description]
     * @param  string  $method [description]
     * @return [type]          [description]
     */

	private function send_lead( $record , $debug = false , $method = 'GET' , $record_type = 'params' ){
        global $wp_version;

        $lead = $record["fields"];
        $url  = $record["url"];

		if( $method == 'GET' && ( $record_type == 'params' || $record_type == 'json' ) ){
			$args = array(
				'timeout'     => 5,
				'redirection' => 5,
				'httpversion' => '1.0',
				'user-agent'  => 'WordPress/' . $wp_version . '; ' . home_url(),
				'blocking'    => true,
				'headers'     => array(),
				'cookies'     => array(),
				'body'        => null,
				'compress'    => false,
				'decompress'  => true,
				'sslverify'   => true,
				'stream'      => false,
				'filename'    => null
			);


			if( $record_type == "json" ){

				$args['headers']['Content-Type'] = 'application/json';

				$json = $this->parse_json( $lead );

				if( is_wp_error( $json ) ){
					return $json;
				}else{
					$args['body'] = $json;
				}

			}else{
				$lead_string = http_build_query( $lead );

				$url         = strpos( '?' , $url ) ? $url.'&'.$lead_string : $url.'?'.$lead_string;
			}

			$args   = apply_filters( 'qs_cf7_api_get_args' , $args );

			$url    = apply_filters( 'qs_cf7_api_get_url' , $url, $record );

			$result = wp_remote_get( $url , $args );

		}else{
			$args = array(
				'timeout'     => 5,
				'redirection' => 5,
				'httpversion' => '1.0',
				'user-agent'  => 'WordPress/' . $wp_version . '; ' . home_url(),
				'blocking'    => true,
				'headers'     => array(),
				'cookies'     => array(),
				'body'        => $lead,
				'compress'    => false,
				'decompress'  => true,
				'sslverify'   => true,
				'stream'      => false,
				'filename'    => null
			);

			if( $record_type == "xml" ){

				$args['headers']['Content-Type'] = 'text/xml';

				$xml = $this->get_xml( $lead );

				if( is_wp_error( $xml ) ){
					return $xml;
				}

				$args['body'] = $xml->asXML();

			}elseif( $record_type == "json" ){

				$args['headers']['Content-Type'] = 'application/json';

				$json = $this->parse_json( $lead );

				if( is_wp_error( $json ) ){
					return $json;
				}else{
					$args['body'] = $json;
				}

			}

			$args   = apply_filters( 'qs_cf7_api_get_args' , $args );

			$url    = apply_filters( 'qs_cf7_api_post_url' , $url );

			$result = wp_remote_post( $url , $args );


		}

        if( $debug ){
            update_post_meta( $this->post->id() , 'qs_cf7_api_debug_url' , $record["url"] );
            update_post_meta( $this->post->id() , 'qs_cf7_api_debug_params' , $lead );
            update_post_meta( $this->post->id() , 'qs_cf7_api_debug_result' , $result );
        }

        return do_action('after_qs_cf7_api_send_lead' , $result , $record );

    }

	private function parse_json( $string ){

		$json = json_decode( $string );

		if ( json_last_error() === JSON_ERROR_NONE) {
			return json_encode( $json );
		}

		if ( json_last_error() === 0) {
			return json_encode( $json );
		}

		return new WP_Error( 'json-error' , json_last_error() );

	}

	private function get_xml( $lead ){
		$xml = "";
		if( function_exists( 'simplexml_load_string' ) ){
			libxml_use_internal_errors(true);

			$xml = simplexml_load_string( $lead );

			if( $xml == false){
				$xml = new WP_Error(
					'xml',
					__( "XML Structure is incorrect" , $this->textdomain )
				);
			}

		}

		return $xml;
	}
}
