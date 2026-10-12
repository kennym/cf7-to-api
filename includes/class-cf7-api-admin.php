<?php
/**
 * Contact Form 7 API admin panel and request sender.
 *
 * @package qs_cf7_api
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings UI and the wpcf7_before_send_mail handler.
 */
class QS_CF7_api_admin {

	/**
	 * Plugin slug assigned by the loader.
	 *
	 * @var string
	 */
	public $plugin_name = '';

	/**
	 * Text domain.
	 *
	 * @var string
	 */
	private $textdomain = 'qs-cf7-api';

	/**
	 * Admin notices helper. Present on admin requests only.
	 *
	 * @var QS_Admin_notices|null
	 */
	private $admin_notices = null;

	/**
	 * Contact form currently being submitted.
	 *
	 * @var object|null
	 */
	private $contact_form = null;

	/**
	 * Visitor message applied after Contact Form 7 sets the success text.
	 *
	 * @var string
	 */
	private $success_response = '';

	/**
	 * Transport details for the debug log of the current request.
	 *
	 * @var array<string,mixed>
	 */
	private $transport_debug = array();

	/**
	 * Register hooks.
	 */
	public function __construct() {
		$this->textdomain = 'qs-cf7-api';

		if ( is_admin() ) {
			$this->admin_notices = new QS_Admin_notices();
		}

		$this->register_hooks();
	}

	/**
	 * Warn when Contact Form 7 is not active.
	 *
	 * @return void
	 */
	public function verify_dependencies() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) || ! $this->admin_notices ) {
			return;
		}

		$this->admin_notices->wp_add_notice(
			array(
				'id'                  => 'cf7-not-active',
				'type'                => 'warning',
				'notice'              => __( 'Contact Form 7 to API requires Contact Form 7 to be installed and active.', 'qs-cf7-api' ),
				'dismissable_forever' => false,
			)
		);
	}

	/**
	 * Register Contact Form 7 and admin hooks.
	 *
	 * Property registration runs on every request. Contact Form 7 drops
	 * unknown properties unless they are added while the form object is built,
	 * including on the front end during submit.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_filter( 'wpcf7_contact_form_properties', array( $this, 'add_sf_properties' ), 10, 2 );
		add_filter( 'wpcf7_pre_construct_contact_form_properties', array( $this, 'add_sf_properties' ), 10, 2 );
		add_action( 'wpcf7_before_send_mail', array( $this, 'qs_cf7_send_data_to_api' ), 10, 3 );
		add_action( 'wpcf7_mail_sent', array( $this, 'apply_success_response' ) );

		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_init', array( $this, 'verify_dependencies' ) );
		add_filter( 'wpcf7_editor_panels', array( $this, 'add_integrations_tab' ), 1, 1 );
		add_action( 'wpcf7_save_contact_form', array( $this, 'qs_save_contact_form_details' ), 10, 1 );
	}

	/**
	 * Register the extra form properties Contact Form 7 should keep.
	 *
	 * @param array  $properties   Form properties.
	 * @param object $contact_form Contact form.
	 * @return array
	 */
	public function add_sf_properties( $properties, $contact_form ) {
		unset( $contact_form );

		if ( ! is_array( $properties ) ) {
			$properties = array();
		}

		if ( ! isset( $properties['wpcf7_api_data'] ) || ! is_array( $properties['wpcf7_api_data'] ) ) {
			$properties['wpcf7_api_data'] = array();
		}

		if ( ! isset( $properties['wpcf7_api_data_map'] ) || ! is_array( $properties['wpcf7_api_data_map'] ) ) {
			$properties['wpcf7_api_data_map'] = array();
		}

		$properties['template']      = qs_cf7_api_flatten_template( isset( $properties['template'] ) ? $properties['template'] : '' );
		$properties['json_template'] = qs_cf7_api_flatten_template( isset( $properties['json_template'] ) ? $properties['json_template'] : '' );

		return $properties;
	}

	/**
	 * Add the API Integration tab.
	 *
	 * @param array $panels Editor panels.
	 * @return array
	 */
	public function add_integrations_tab( $panels ) {
		if ( ! is_array( $panels ) ) {
			$panels = array();
		}

		$panels['qs-cf7-api-integration'] = array(
			'title'    => __( 'API Integration', 'qs-cf7-api' ),
			'callback' => array( $this, 'wpcf7_integrations' ),
		);

		return $panels;
	}

	/**
	 * Form-tags that have a name.
	 *
	 * @param object $post Contact form.
	 * @return array
	 */
	public function get_mail_tags( $post ) {
		$mailtags = array();

		if ( ! is_object( $post ) || ! method_exists( $post, 'scan_form_tags' ) ) {
			return $mailtags;
		}

		$tags = apply_filters( 'qs_cf7_collect_mail_tags', $post->scan_form_tags() );

		if ( ! is_array( $tags ) ) {
			return $mailtags;
		}

		foreach ( $tags as $tag ) {
			$parts = $this->tag_parts( $tag );

			if ( '' === $parts['name'] || '' === $parts['basetype'] ) {
				continue;
			}

			$mailtags[] = $tag;
		}

		return $mailtags;
	}

	/**
	 * Render the API Integration tab.
	 *
	 * Contact Form 7 calls this with the contact form object.
	 *
	 * @param object $post Contact form.
	 * @return void
	 */
	public function wpcf7_integrations( $post ) {
		$settings     = $this->normalized_settings( $post->prop( 'wpcf7_api_data' ) );
		$map          = $post->prop( 'wpcf7_api_data_map' );
		$map          = is_array( $map ) ? $map : array();
		$xml          = qs_cf7_api_flatten_template( $post->prop( 'template' ) );
		$json         = qs_cf7_api_flatten_template( $post->prop( 'json_template' ) );
		$mail_tags    = $this->get_mail_tags( $post );
		$debug        = get_post_meta( $post->id(), '_qs_cf7_api_debug', true );
		$debug        = is_array( $debug ) ? $debug : array();
		$debug_url    = isset( $debug['url'] ) ? (string) $debug['url'] : '';
		$debug_body   = isset( $debug['params'] ) ? (string) $debug['params'] : '';
		$debug_code   = isset( $debug['code'] ) ? (string) $debug['code'] : '';
		$debug_resp   = isset( $debug['body'] ) ? (string) $debug['body'] : '';
		$debug_err    = isset( $debug['error'] ) ? (string) $debug['error'] : '';
		$header_names = isset( $debug['header_names'] ) && is_array( $debug['header_names'] ) ? $debug['header_names'] : array();

		$xml_placeholder  = "*** THIS IS AN EXAMPLE ** USE YOUR XML ACCORDING TO YOUR API DOCUMENTATION **\n<update>\n<user clientid=\"\" username=\"user_name\" password=\"mypassword\" />\n<reports>\n<report tag=\"NEW\">\n<fields>\n<field id=\"1\" name=\"REFERENCE_ID\" value=\"[your-name]\" />\n<field id=\"2\" name=\"DESCRIPTION\" value=\"[your-email]\" />\n</fields>\n</report>\n</reports>\n</update>";
		$json_placeholder = "*** THIS IS AN EXAMPLE ** USE YOUR JSON ACCORDING TO YOUR API DOCUMENTATION **\n{ \"name\":\"[your-name]\", \"age\":30, \"accepted\":[acceptance] }";
		?>
		<h2><?php echo esc_html__( 'API Integration', 'qs-cf7-api' ); ?></h2>
		<noscript><style>[data-qsindex]{display:block !important;}</style></noscript>
		<fieldset>
			<?php do_action( 'before_base_fields', $post ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook. ?>
			<div class="cf7_row">
				<label for="wpcf7-sf-send_to_api">
					<input type="checkbox" id="wpcf7-sf-send_to_api" name="wpcf7-sf[send_to_api]" <?php checked( $settings['send_to_api'], 'on' ); ?> />
					<?php echo esc_html__( 'Send to API', 'qs-cf7-api' ); ?>
				</label>
			</div>
			<div class="cf7_row">
				<label for="wpcf7-sf-override_message">
					<input type="checkbox" id="wpcf7-sf-override_message" name="wpcf7-sf[override_message]" <?php checked( $settings['override_message'], 'on' ); ?> />
					<?php echo esc_html__( 'Show the API response body as the form message', 'qs-cf7-api' ); ?>
				</label>
			</div>
			<div class="cf7_row">
				<label for="wpcf7-sf-base_url">
					<?php echo esc_html__( 'Base URL', 'qs-cf7-api' ); ?>
					<input type="text" id="wpcf7-sf-base_url" name="wpcf7-sf[base_url]" class="large-text" value="<?php echo esc_attr( $settings['base_url'] ); ?>" />
				</label>
			</div>
			<hr>
			<div class="cf7_row">
				<label for="wpcf7-sf-basic_auth">
					<?php echo esc_html__( 'Basic auth', 'qs-cf7-api' ); ?>
					<input type="text" id="wpcf7-sf-basic_auth" name="wpcf7-sf[basic_auth]" class="large-text" autocomplete="off" value="<?php echo esc_attr( $settings['basic_auth'] ); ?>" placeholder="user:secret" />
				</label>
			</div>
			<p><?php echo esc_html__( '- OR -', 'qs-cf7-api' ); ?></p>
			<div class="cf7_row">
				<label for="wpcf7-sf-bearer_auth">
					<?php echo esc_html__( 'Bearer auth key', 'qs-cf7-api' ); ?>
					<input type="text" id="wpcf7-sf-bearer_auth" name="wpcf7-sf[bearer_auth]" class="large-text" autocomplete="off" value="<?php echo esc_attr( $settings['bearer_auth'] ); ?>" />
				</label>
			</div>
			<hr>
			<div class="cf7_row">
				<label for="wpcf7-sf-input_type">
					<span class="cf7-label-in"><?php echo esc_html__( 'Input type', 'qs-cf7-api' ); ?></span>
					<select id="wpcf7-sf-input_type" name="wpcf7-sf[input_type]">
						<option value="params" <?php selected( $settings['input_type'], 'params' ); ?>><?php echo esc_html__( 'Parameters - GET/POST', 'qs-cf7-api' ); ?></option>
						<option value="xml" <?php selected( $settings['input_type'], 'xml' ); ?>><?php echo esc_html__( 'XML', 'qs-cf7-api' ); ?></option>
						<option value="json" <?php selected( $settings['input_type'], 'json' ); ?>><?php echo esc_html__( 'JSON', 'qs-cf7-api' ); ?></option>
					</select>
				</label>
			</div>
			<div class="cf7_row" data-qsindex="params,json">
				<label for="wpcf7-sf-method">
					<span class="cf7-label-in"><?php echo esc_html__( 'Method', 'qs-cf7-api' ); ?></span>
					<select id="wpcf7-sf-method" name="wpcf7-sf[method]">
						<option value="GET" <?php selected( $settings['method'], 'GET' ); ?>>GET</option>
						<option value="POST" <?php selected( $settings['method'], 'POST' ); ?>>POST</option>
					</select>
				</label>
			</div>
			<div class="cf7_row">
				<label for="wpcf7-sf-timeout">
					<?php echo esc_html__( 'Timeout (seconds)', 'qs-cf7-api' ); ?>
					<input type="number" min="1" max="60" id="wpcf7-sf-timeout" name="wpcf7-sf[timeout]" value="<?php echo esc_attr( (string) $settings['timeout'] ); ?>" />
				</label>
			</div>
			<div class="cf7_row">
				<label for="wpcf7-sf-headers">
					<?php echo esc_html__( 'Custom headers', 'qs-cf7-api' ); ?>
					<textarea id="wpcf7-sf-headers" name="wpcf7-sf[headers]" rows="4" class="large-text" placeholder="X-Api-Key: abc123"><?php echo esc_textarea( $settings['headers'] ); ?></textarea>
				</label>
				<p class="description"><?php echo esc_html__( 'One header per line, in Name: value form. Basic or Bearer auth replaces an Authorization line.', 'qs-cf7-api' ); ?></p>
			</div>
			<?php do_action( 'after_base_fields', $post ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook. ?>
		</fieldset>

		<fieldset data-qsindex="params">
			<div class="cf7_row">
				<h2><?php echo esc_html__( 'Form fields', 'qs-cf7-api' ); ?></h2>
				<table>
					<tr>
						<th><?php echo esc_html__( 'Form field', 'qs-cf7-api' ); ?></th>
						<th><?php echo esc_html__( 'API key', 'qs-cf7-api' ); ?></th>
					</tr>
					<?php foreach ( $mail_tags as $mail_tag ) : ?>
						<?php
						$parts = $this->tag_parts( $mail_tag );
						if ( 'checkbox' === $parts['basetype'] && $parts['values'] ) :
							foreach ( $parts['values'] as $checkbox_row ) :
								$saved = '';
								if ( isset( $map[ $parts['name'] ] ) && is_array( $map[ $parts['name'] ] ) && isset( $map[ $parts['name'] ][ $checkbox_row ] ) ) {
									$saved = (string) $map[ $parts['name'] ][ $checkbox_row ];
								}
								$input_id = 'sf-' . $parts['name'] . '-' . $checkbox_row;
								?>
								<tr>
									<th style="text-align:left;"><?php echo esc_html( $parts['name'] . ' (' . $checkbox_row . ')' ); ?></th>
									<td><input type="text" id="<?php echo esc_attr( $input_id ); ?>" name="qs_wpcf7_api_map[<?php echo esc_attr( $parts['name'] ); ?>][<?php echo esc_attr( $checkbox_row ); ?>]" class="large-text" value="<?php echo esc_attr( $saved ); ?>" /></td>
								</tr>
							<?php endforeach; ?>
						<?php else : ?>
							<?php
							$saved = '';
							if ( isset( $map[ $parts['name'] ] ) && is_string( $map[ $parts['name'] ] ) ) {
								$saved = $map[ $parts['name'] ];
							}
							?>
							<tr>
								<th style="text-align:left;"><?php echo esc_html( $parts['name'] ); ?></th>
								<td><input type="text" id="<?php echo esc_attr( 'sf-' . $parts['name'] ); ?>" name="qs_wpcf7_api_map[<?php echo esc_attr( $parts['name'] ); ?>]" class="large-text" value="<?php echo esc_attr( $saved ); ?>" /></td>
							</tr>
						<?php endif; ?>
					<?php endforeach; ?>
				</table>
			</div>
		</fieldset>

		<fieldset data-qsindex="xml">
			<div class="cf7_row">
				<h2><?php echo esc_html__( 'XML template', 'qs-cf7-api' ); ?></h2>
				<legend>
					<?php foreach ( $mail_tags as $mail_tag ) : ?>
						<?php $parts = $this->tag_parts( $mail_tag ); ?>
						<span class="xml_mailtag mailtag code">[<?php echo esc_html( $parts['name'] ); ?>]</span>
					<?php endforeach; ?>
				</legend>
				<textarea name="template" rows="12" dir="ltr" placeholder="<?php echo esc_attr( $xml_placeholder ); ?>"><?php echo esc_textarea( $xml ); ?></textarea>
			</div>
		</fieldset>

		<fieldset data-qsindex="json">
			<div class="cf7_row">
				<h2><?php echo esc_html__( 'JSON template', 'qs-cf7-api' ); ?></h2>
				<legend>
					<?php foreach ( $mail_tags as $mail_tag ) : ?>
						<?php
						$parts = $this->tag_parts( $mail_tag );
						if ( 'checkbox' === $parts['basetype'] && $parts['values'] ) :
							foreach ( $parts['values'] as $checkbox_row ) :
								?>
								<span class="xml_mailtag mailtag code">[<?php echo esc_html( $parts['name'] . '-' . $checkbox_row ); ?>]</span>
							<?php endforeach; ?>
						<?php else : ?>
							<span class="xml_mailtag mailtag code">[<?php echo esc_html( $parts['name'] ); ?>]</span>
						<?php endif; ?>
					<?php endforeach; ?>
				</legend>
				<textarea name="json_template" rows="12" dir="ltr" placeholder="<?php echo esc_attr( $json_placeholder ); ?>"><?php echo esc_textarea( $json ); ?></textarea>
			</div>
		</fieldset>

		<fieldset>
			<div class="cf7_row">
				<label class="debug-log-trigger">
					<?php echo esc_html__( '+ DEBUG LOG (last transmission)', 'qs-cf7-api' ); ?>
				</label>
				<div class="debug-log-wrap">
					<h3 class="debug_log_title"><?php echo esc_html__( 'Last API call', 'qs-cf7-api' ); ?></h3>
					<div class="debug_log">
						<h4><?php echo esc_html__( 'Called URL', 'qs-cf7-api' ); ?></h4>
						<textarea rows="2" readonly="readonly"><?php echo esc_textarea( $debug_url ); ?></textarea>
						<h4><?php echo esc_html__( 'Request body', 'qs-cf7-api' ); ?></h4>
						<textarea rows="8" readonly="readonly"><?php echo esc_textarea( $debug_body ); ?></textarea>
						<h4><?php echo esc_html__( 'Header names', 'qs-cf7-api' ); ?></h4>
						<textarea rows="3" readonly="readonly"><?php echo esc_textarea( implode( "\n", array_map( 'strval', $header_names ) ) ); ?></textarea>
						<h4><?php echo esc_html__( 'Response code', 'qs-cf7-api' ); ?></h4>
						<textarea rows="1" readonly="readonly"><?php echo esc_textarea( $debug_code ); ?></textarea>
						<h4><?php echo esc_html__( 'Response body', 'qs-cf7-api' ); ?></h4>
						<textarea rows="8" readonly="readonly"><?php echo esc_textarea( $debug_resp ); ?></textarea>
						<h4><?php echo esc_html__( 'Error', 'qs-cf7-api' ); ?></h4>
						<textarea rows="4" readonly="readonly"><?php echo esc_textarea( $debug_err ); ?></textarea>
					</div>
				</div>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Save API settings onto the contact form.
	 *
	 * Contact Form 7 checks the editor nonce and capability before this fires.
	 *
	 * @param object $contact_form Contact form.
	 * @return void
	 */
	public function qs_save_contact_form_details( $contact_form ) {
		if ( ! is_object( $contact_form ) || ! method_exists( $contact_form, 'get_properties' ) ) {
			return;
		}

		$properties = $contact_form->get_properties();

		if ( ! is_array( $properties ) ) {
			$properties = array();
		}

		$settings = $this->sanitize_settings( $this->read_post_array( 'wpcf7-sf' ) );
		$map      = $this->sanitize_map( $this->read_post_array( 'qs_wpcf7_api_map' ) );
		$xml      = $this->sanitize_template( $this->read_post_value( 'template' ) );
		$json     = $this->sanitize_template( $this->read_post_value( 'json_template' ) );

		if ( 'json' === $settings['input_type'] || 'xml' === $settings['input_type'] ) {
			$template = ( 'json' === $settings['input_type'] ) ? $json : $xml;
			$map      = qs_cf7_api_merge_template_map( $map, $template );
		}

		$properties['wpcf7_api_data']     = $settings;
		$properties['wpcf7_api_data_map'] = $map;
		$properties['template']           = $xml;
		$properties['json_template']      = $json;

		$contact_form->set_properties( $properties );
	}

	/**
	 * Send the submission to the configured API before Contact Form 7 mails it.
	 *
	 * On Contact Form 7 5.2 and newer, setting $abort and a status other than
	 * "init" skips mail() and keeps the status and response we set. A 2xx
	 * response lets mail proceed.
	 *
	 * @param object    $contact_form Contact form.
	 * @param bool|null $abort        Set true to stop mail.
	 * @param object    $submission   Submission, when CF7 passes it.
	 * @return void
	 */
	public function qs_cf7_send_data_to_api( $contact_form, &$abort = null, $submission = null ) {
		if ( ! is_object( $contact_form ) || ! method_exists( $contact_form, 'prop' ) ) {
			return;
		}

		$settings = $this->normalized_settings( $contact_form->prop( 'wpcf7_api_data' ) );

		if ( 'on' !== $settings['send_to_api'] ) {
			return;
		}

		if ( ! is_object( $submission ) || ! method_exists( $submission, 'get_posted_data' ) ) {
			$submission = WPCF7_Submission::get_instance();
		}

		if ( ! $submission ) {
			return;
		}

		$this->contact_form = $contact_form;

		$record        = $this->get_record( $submission, $contact_form, $settings );
		$record['url'] = $settings['base_url'];
		$template      = $this->raw_template( $contact_form, $settings['input_type'] );
		$record        = apply_filters(
			'cf7api_create_record', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook.
			$record,
			$submission->get_posted_data(),
			$contact_form->prop( 'wpcf7_api_data_map' ),
			$settings['input_type'],
			$template
		);

		if ( ! is_array( $record ) ) {
			$record = array(
				'url'    => $settings['base_url'],
				'fields' => array(),
			);
		}

		do_action( 'qs_cf7_api_before_sent_to_api', $record );

		$url       = isset( $record['url'] ) ? (string) $record['url'] : '';
		$validated = wp_http_validate_url( $url );
		$validated = apply_filters( 'qs_cf7_api_validated_url', $validated, $url, $record );

		if ( ! is_string( $validated ) || '' === $validated ) {
			$this->store_debug(
				$contact_form,
				array(
					'url'          => $url,
					'params'       => $this->preview_value( isset( $record['fields'] ) ? $record['fields'] : '' ),
					'code'         => '',
					'body'         => '',
					'error'        => __( 'The API URL is not valid.', 'qs-cf7-api' ),
					'header_names' => array(),
				)
			);
			$this->fail_submission( $submission, __( 'The API URL is not valid.', 'qs-cf7-api' ), $abort );
			return;
		}

		$record['url'] = $validated;
		$result        = $this->send_lead( $record, $settings );
		$interpreted   = $this->interpret_http_result( $result );

		$this->store_debug( $contact_form, $this->last_debug( $interpreted ) );

		if ( ! is_wp_error( $result ) ) {
			do_action( 'qs_cf7_api_after_sent_to_api', $record, $result );
		}

		if ( $interpreted['ok'] ) {
			if ( 'on' === $settings['override_message'] ) {
				$message = $this->message_from_body( $interpreted['body'] );

				if ( '' !== $message ) {
					$this->success_response = $message;
				}
			}
			return;
		}

		$visitor_message = $this->failure_message( $contact_form, $settings, $interpreted );
		$this->fail_submission( $submission, $visitor_message, $abort );
	}

	/**
	 * Replace the success message after Contact Form 7 writes mail_sent_ok.
	 *
	 * @return void
	 */
	public function apply_success_response() {
		if ( '' === $this->success_response ) {
			return;
		}

		$submission = WPCF7_Submission::get_instance();

		if ( $submission && method_exists( $submission, 'set_response' ) ) {
			$submission->set_response( $this->success_response );
		}

		$this->success_response = '';
	}

	/**
	 * Mark the submission failed and skip the Contact Form 7 email.
	 *
	 * @param object    $submission Submission.
	 * @param string    $message    Visitor-facing message.
	 * @param bool|null $abort      Abort flag passed by reference.
	 * @return void
	 */
	private function fail_submission( $submission, $message, &$abort ) {
		$abort = true;

		if ( method_exists( $submission, 'set_status' ) ) {
			$submission->set_status( 'mail_failed' );
		}

		if ( method_exists( $submission, 'set_response' ) ) {
			$submission->set_response( $message );
		}
	}

	/**
	 * Build the record passed to the request and to cf7api_create_record.
	 *
	 * @param object $submission   Submission.
	 * @param object $contact_form Contact form.
	 * @param array  $settings     Normalized settings.
	 * @return array{fields:mixed}
	 */
	private function get_record( $submission, $contact_form, $settings ) {
		$map = $contact_form->prop( 'wpcf7_api_data_map' );

		if ( ! is_array( $map ) ) {
			$map = array();
		}

		$type = $settings['input_type'];

		if ( 'params' === $type ) {
			return array(
				'fields' => $this->param_fields( $map, $submission, $contact_form ),
			);
		}

		$template = $this->raw_template( $contact_form, $type );
		$tokens   = qs_cf7_api_extract_template_tokens( $template );

		foreach ( $map as $form_key => $unused ) {
			unset( $unused );

			if ( is_string( $form_key ) && '' !== $form_key ) {
				$tokens[] = $form_key;
			}
		}

		$tokens = array_values( array_unique( $tokens ) );
		$values = array();

		foreach ( $tokens as $token ) {
			$value            = $this->resolve_value( $token, $submission, $contact_form );
			$filter_key       = isset( $map[ $token ] ) ? $map[ $token ] : '';
			$values[ $token ] = apply_filters( 'set_record_value', $value, $filter_key ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook.
		}

		return array(
			'fields' => qs_cf7_api_fill_template( $template, $values, $type ),
		);
	}

	/**
	 * Mapped parameter fields.
	 *
	 * @param array  $map          Field map.
	 * @param object $submission   Submission.
	 * @param object $contact_form Contact form.
	 * @return array<string,mixed>
	 */
	private function param_fields( $map, $submission, $contact_form ) {
		$fields = array();

		foreach ( $map as $form_key => $api_key ) {
			if ( ! is_string( $form_key ) || '' === $form_key ) {
				continue;
			}

			if ( is_array( $api_key ) ) {
				foreach ( $this->posted_selection( $submission, $form_key ) as $option ) {
					if ( ! is_scalar( $option ) ) {
						continue;
					}

					$option = (string) $option;

					if ( '' === $option || ! isset( $api_key[ $option ] ) || '' === (string) $api_key[ $option ] ) {
						continue;
					}

					$fields[ (string) $api_key[ $option ] ] = apply_filters( 'set_record_value', $option, $api_key ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook.
				}
				continue;
			}

			if ( ! is_string( $api_key ) || '' === $api_key ) {
				continue;
			}

			$value = $this->resolve_value( $form_key, $submission, $contact_form );

			if ( is_bool( $value ) ) {
				$value = $value ? 'true' : 'false';
			}

			$fields[ $api_key ] = apply_filters( 'set_record_value', $value, $api_key ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook.
		}

		return $fields;
	}

	/**
	 * Resolve one mail-tag or special tag to a PHP value.
	 *
	 * @param string $token        Tag name without brackets.
	 * @param object $submission   Submission.
	 * @param object $contact_form Contact form.
	 * @return mixed
	 */
	private function resolve_value( $token, $submission, $contact_form ) {
		$uploaded = $submission->uploaded_files();

		if ( is_array( $uploaded ) && isset( $uploaded[ $token ] ) ) {
			return $this->encode_files( $uploaded[ $token ] );
		}

		if ( $this->is_acceptance_field( $contact_form, $token ) ) {
			$posted = $submission->get_posted_data( $token );

			if ( is_array( $posted ) ) {
				$posted = reset( $posted );
			}

			return ( null === $posted || '' === $posted ) ? false : true;
		}

		$all = $submission->get_posted_data();

		if ( is_array( $all ) && array_key_exists( $token, $all ) ) {
			return $this->normalize_posted_value( $all[ $token ] );
		}

		$option = $this->checkbox_option_value( $token, $submission, $contact_form );

		if ( null !== $option ) {
			return $option;
		}

		return $this->special_mail_tag( $token );
	}

	/**
	 * Selected values for a field, kept as a list.
	 *
	 * @param object $submission Submission.
	 * @param string $form_key   Field name.
	 * @return array
	 */
	private function posted_selection( $submission, $form_key ) {
		$posted = $submission->get_posted_data( $form_key );

		if ( null === $posted || '' === $posted ) {
			return array();
		}

		return array_values( (array) $posted );
	}

	/**
	 * Collapse a one-item array to a scalar and keep real multi-values.
	 *
	 * @param mixed $value Posted value.
	 * @return mixed
	 */
	private function normalize_posted_value( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		$items = array();

		foreach ( $value as $item ) {
			if ( null === $item || '' === $item ) {
				continue;
			}
			$items[] = $item;
		}

		if ( 1 === count( $items ) ) {
			return $items[0];
		}

		return $items;
	}

	/**
	 * Value of a "[checkbox-Option]" token, or null when the token is not one.
	 *
	 * @param string $token        Template token.
	 * @param object $submission   Submission.
	 * @param object $contact_form Contact form.
	 * @return string|null Selected option text, empty string when not selected, null when not a checkbox token.
	 */
	private function checkbox_option_value( $token, $submission, $contact_form ) {
		if ( ! is_object( $contact_form ) || ! method_exists( $contact_form, 'scan_form_tags' ) ) {
			return null;
		}

		$tags = $contact_form->scan_form_tags();

		if ( ! is_array( $tags ) ) {
			return null;
		}

		foreach ( $tags as $tag ) {
			$parts = $this->tag_parts( $tag );

			if ( 'checkbox' !== $parts['basetype'] || '' === $parts['name'] ) {
				continue;
			}

			$prefix = $parts['name'] . '-';

			if ( 0 !== strpos( $token, $prefix ) ) {
				continue;
			}

			$option   = substr( $token, strlen( $prefix ) );
			$selected = array_map( 'strval', $this->posted_selection( $submission, $parts['name'] ) );

			return in_array( $option, $selected, true ) ? $option : '';
		}

		return null;
	}

	/**
	 * Resolve a Contact Form 7 special mail-tag such as [_remote_ip].
	 *
	 * @param string $token Tag name without brackets.
	 * @return string
	 */
	private function special_mail_tag( $token ) {
		if ( ! function_exists( 'wpcf7_mail_replace_tags' ) ) {
			return '';
		}

		$tag      = '[' . $token . ']';
		$replaced = wpcf7_mail_replace_tags(
			$tag,
			array(
				'html' => false,
			)
		);

		if ( ! is_string( $replaced ) || $replaced === $tag ) {
			return '';
		}

		return $replaced;
	}

	/**
	 * Base64-encode files Contact Form 7 already accepted.
	 *
	 * @param mixed $paths Paths from uploaded_files().
	 * @return string[]
	 */
	private function encode_files( $paths ) {
		$encoded = array();

		foreach ( (array) $paths as $path ) {
			if ( ! is_string( $path ) || '' === $path ) {
				continue;
			}

			$real = realpath( $path );

			if ( false === $real || ! is_file( $real ) || ! is_readable( $real ) ) {
				continue;
			}

			$size = filesize( $real );

			if ( false === $size || $size > 8 * 1024 * 1024 ) {
				continue;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local temp upload already validated by Contact Form 7. WP_Filesystem is not available on the front-end submit.
			$contents = file_get_contents( $real );

			if ( false === $contents ) {
				continue;
			}

			$encoded[] = base64_encode( $contents );
		}

		return $encoded;
	}

	/**
	 * Whether a field is an acceptance field, including required acceptance*.
	 *
	 * @param object $contact_form Contact form.
	 * @param string $field_name   Field name.
	 * @return bool
	 */
	private function is_acceptance_field( $contact_form, $field_name ) {
		if ( ! is_object( $contact_form ) || ! method_exists( $contact_form, 'scan_form_tags' ) ) {
			return false;
		}

		$fields = $contact_form->scan_form_tags(
			array(
				'basetype' => 'acceptance',
				'name'     => $field_name,
			)
		);

		return is_array( $fields ) && 1 === count( $fields );
	}

	/**
	 * Perform the HTTP request.
	 *
	 * @param array $record   Record with url and fields.
	 * @param array $settings Normalized settings.
	 * @return array|WP_Error
	 */
	private function send_lead( $record, $settings ) {
		global $wp_version;

		$lead    = isset( $record['fields'] ) ? $record['fields'] : '';
		$url     = (string) $record['url'];
		$method  = $settings['method'];
		$type    = $settings['input_type'];
		$headers = qs_cf7_api_parse_headers( $settings['headers'] );

		$this->transport_debug = array(
			'url'          => $url,
			'params'       => $this->preview_value( $lead ),
			'header_names' => array(),
			'result'       => null,
		);
		$auth                  = qs_cf7_api_auth_header( $settings['basic_auth'], $settings['bearer_auth'] );

		if ( '' !== $auth ) {
			$headers = qs_cf7_api_set_header( $headers, 'Authorization', $auth );
		}

		$body = null;

		if ( 'GET' === $method && 'params' === $type && is_array( $lead ) ) {
			$url  = qs_cf7_api_append_query( $url, $lead );
			$body = null;
		} elseif ( 'json' === $type ) {
			$json = $this->parse_json( $lead );

			if ( is_wp_error( $json ) ) {
				return $json;
			}

			$body    = $json;
			$headers = qs_cf7_api_set_header( $headers, 'Content-Type', 'application/json; charset=utf-8' );
		} elseif ( 'xml' === $type ) {
			$xml = $this->get_xml( $lead );

			if ( is_wp_error( $xml ) ) {
				return $xml;
			}

			$body    = $xml->asXML();
			$headers = qs_cf7_api_set_header( $headers, 'Content-Type', 'text/xml; charset=utf-8' );
		} else {
			$body = $lead;
		}

		$timeout = (int) apply_filters( 'qs_cf7_api_timeout', (int) $settings['timeout'], $record );

		if ( $timeout < 1 ) {
			$timeout = 15;
		}

		$args = array(
			'timeout'     => $timeout,
			'redirection' => 5,
			'httpversion' => '1.1',
			'user-agent'  => 'WordPress/' . $wp_version . '; ' . home_url(),
			'blocking'    => true,
			'headers'     => $headers,
			'body'        => $body,
		);

		$args = apply_filters( 'qs_cf7_api_get_args', $args );

		if ( 'GET' === $method ) {
			$url    = apply_filters( 'qs_cf7_api_get_url', $url, $record );
			$result = wp_remote_get( $url, $args );
		} else {
			$url    = apply_filters( 'qs_cf7_api_post_url', $url );
			$result = wp_remote_post( $url, $args );
		}

		$this->remember_transport( $url, $body, $args, $result );

		do_action( 'after_qs_cf7_api_send_lead', $result, $record ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook.

		return $result;
	}

	/**
	 * Stash the last transport attempt for the debug meta written by the caller.
	 *
	 * @param string         $url    Final URL.
	 * @param mixed          $body   Request body.
	 * @param array          $args   wp_remote args.
	 * @param array|WP_Error $result Remote result.
	 * @return void
	 */
	private function remember_transport( $url, $body, $args, $result ) {
		$headers = array();

		if ( isset( $args['headers'] ) && is_array( $args['headers'] ) ) {
			$headers = array_keys( $args['headers'] );
		}

		$this->transport_debug = array(
			'url'          => (string) $url,
			'params'       => $this->preview_value( $body ),
			'header_names' => $headers,
			'result'       => $result,
		);
	}

	/**
	 * Validate a JSON template and return the original string.
	 *
	 * @param mixed $lead Template or payload.
	 * @return string|WP_Error
	 */
	private function parse_json( $lead ) {
		if ( is_array( $lead ) ) {
			$encoded = qs_cf7_api_json_encode( $lead );

			if ( 'null' === $encoded && array() !== $lead ) {
				return new WP_Error( 'json-error', __( 'The JSON payload could not be encoded.', 'qs-cf7-api' ) );
			}

			return $encoded;
		}

		$lead = qs_cf7_api_flatten_template( $lead );
		json_decode( $lead );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'json-error', json_last_error_msg() );
		}

		return $lead;
	}

	/**
	 * Parse an XML template.
	 *
	 * @param mixed $lead Template.
	 * @return SimpleXMLElement|WP_Error
	 */
	private function get_xml( $lead ) {
		$lead = qs_cf7_api_flatten_template( $lead );

		if ( ! function_exists( 'simplexml_load_string' ) ) {
			return new WP_Error( 'xml', __( 'SimpleXML is required to send XML.', 'qs-cf7-api' ) );
		}

		if ( PHP_VERSION_ID < 80000 && function_exists( 'libxml_disable_entity_loader' ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, PHPCompatibility.FunctionUse.RemovedFunctions.libxml_disable_entity_loaderDeprecated -- External entities stay off on PHP 7.4.
			@libxml_disable_entity_loader( true );
		}

		libxml_use_internal_errors( true );

		$xml = simplexml_load_string( $lead, 'SimpleXMLElement', LIBXML_NONET );

		if ( false === $xml ) {
			return new WP_Error( 'xml', __( 'XML structure is incorrect.', 'qs-cf7-api' ) );
		}

		return $xml;
	}

	/**
	 * Normalize a stored settings array for the editor and the sender.
	 *
	 * @param mixed $stored Stored wpcf7_api_data.
	 * @return array<string,mixed>
	 */
	private function normalized_settings( $stored ) {
		$defaults = array(
			'base_url'         => '',
			'basic_auth'       => '',
			'bearer_auth'      => '',
			'send_to_api'      => '',
			'override_message' => '',
			'input_type'       => 'params',
			'method'           => 'GET',
			'timeout'          => 15,
			'headers'          => '',
		);

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$settings                     = array_merge( $defaults, $stored );
		$settings['input_type']       = $this->allow( $settings['input_type'], array( 'params', 'xml', 'json' ), 'params' );
		$settings['method']           = $this->allow( $settings['method'], array( 'GET', 'POST' ), 'GET' );
		$settings['timeout']          = qs_cf7_api_sanitize_timeout( $settings['timeout'] );
		$settings['base_url']         = is_string( $settings['base_url'] ) ? $settings['base_url'] : '';
		$settings['basic_auth']       = is_string( $settings['basic_auth'] ) ? $settings['basic_auth'] : '';
		$settings['bearer_auth']      = is_string( $settings['bearer_auth'] ) ? $settings['bearer_auth'] : '';
		$settings['headers']          = is_string( $settings['headers'] ) ? $settings['headers'] : '';
		$settings['send_to_api']      = ( 'on' === $settings['send_to_api'] ) ? 'on' : '';
		$settings['override_message'] = ( 'on' === $settings['override_message'] ) ? 'on' : '';

		if ( 'xml' === $settings['input_type'] ) {
			$settings['method'] = 'POST';
		}

		return $settings;
	}

	/**
	 * Sanitize the posted settings block.
	 *
	 * @param array $raw Posted wpcf7-sf values, already unslashed.
	 * @return array<string,mixed>
	 */
	private function sanitize_settings( $raw ) {
		$input_type = isset( $raw['input_type'] ) ? (string) $raw['input_type'] : 'params';
		$method     = isset( $raw['method'] ) ? (string) $raw['method'] : 'GET';

		return array(
			'send_to_api'      => ( isset( $raw['send_to_api'] ) && 'on' === $raw['send_to_api'] ) ? 'on' : '',
			'override_message' => ( isset( $raw['override_message'] ) && 'on' === $raw['override_message'] ) ? 'on' : '',
			'base_url'         => isset( $raw['base_url'] ) ? esc_url_raw( (string) $raw['base_url'] ) : '',
			'basic_auth'       => isset( $raw['basic_auth'] ) ? sanitize_text_field( (string) $raw['basic_auth'] ) : '',
			'bearer_auth'      => isset( $raw['bearer_auth'] ) ? sanitize_text_field( (string) $raw['bearer_auth'] ) : '',
			'input_type'       => $this->allow( $input_type, array( 'params', 'xml', 'json' ), 'params' ),
			'method'           => $this->allow( $method, array( 'GET', 'POST' ), 'GET' ),
			'timeout'          => qs_cf7_api_sanitize_timeout( isset( $raw['timeout'] ) ? $raw['timeout'] : 15 ),
			'headers'          => $this->sanitize_header_block( isset( $raw['headers'] ) ? $raw['headers'] : '' ),
		);
	}

	/**
	 * Sanitize a field map one scalar at a time.
	 *
	 * @param array $map Posted map.
	 * @return array
	 */
	private function sanitize_map( $map ) {
		$clean = array();

		foreach ( $map as $key => $value ) {
			$key = sanitize_text_field( (string) $key );

			if ( '' === $key ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$child = array();

				foreach ( $value as $option => $api_key ) {
					$option = sanitize_text_field( (string) $option );
					$api    = sanitize_text_field( (string) $api_key );

					if ( '' === $option ) {
						continue;
					}

					$child[ $option ] = $api;
				}

				$clean[ $key ] = $child;
				continue;
			}

			$clean[ $key ] = sanitize_text_field( (string) $value );
		}

		return $clean;
	}

	/**
	 * Keep JSON and XML punctuation. Strip NULs and invalid UTF-8 only.
	 *
	 * @param mixed $value Posted template.
	 * @return string
	 */
	private function sanitize_template( $value ) {
		$value = qs_cf7_api_flatten_template( $value );
		$value = wp_check_invalid_utf8( $value );
		$value = wp_kses_no_null( $value );

		return $value;
	}

	/**
	 * Rebuild a header block from the lines that parse cleanly.
	 *
	 * @param mixed $text Posted header text.
	 * @return string
	 */
	private function sanitize_header_block( $text ) {
		$headers = qs_cf7_api_parse_headers( wp_check_invalid_utf8( (string) $text ) );
		$lines   = array();

		foreach ( $headers as $name => $value ) {
			$lines[] = sanitize_text_field( $name ) . ': ' . sanitize_text_field( $value );
		}

		return implode( "\n", $lines );
	}

	/**
	 * Read one posted array. Contact Form 7 has already verified the save nonce.
	 *
	 * @param string $key Post key.
	 * @return array
	 */
	private function read_post_array( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- wpcf7_save_contact_form runs after Contact Form 7 verifies the editor nonce and capability.
		if ( ! isset( $_POST[ $key ] ) ) {
			return array();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Unslash here; callers sanitize each key.
		$value = wp_unslash( $_POST[ $key ] );

		return is_array( $value ) ? $value : array();
	}

	/**
	 * Read one posted scalar. Contact Form 7 has already verified the save nonce.
	 *
	 * @param string $key Post key.
	 * @return string
	 */
	private function read_post_value( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- wpcf7_save_contact_form runs after Contact Form 7 verifies the editor nonce and capability.
		if ( ! isset( $_POST[ $key ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Unslash here; sanitize_template() cleans the value.
		$value = wp_unslash( $_POST[ $key ] );

		if ( is_array( $value ) ) {
			return qs_cf7_api_flatten_template( $value );
		}

		return (string) $value;
	}

	/**
	 * Template string for the active input type.
	 *
	 * @param object $contact_form Contact form.
	 * @param string $type         Input type.
	 * @return string
	 */
	private function raw_template( $contact_form, $type ) {
		$key = ( 'json' === $type ) ? 'json_template' : 'template';

		return qs_cf7_api_flatten_template( $contact_form->prop( $key ) );
	}

	/**
	 * Summarize a transport result.
	 *
	 * @param array|WP_Error $result Remote result.
	 * @return array{ok:bool,code:int,body:string,error:string}
	 */
	private function interpret_http_result( $result ) {
		if ( is_wp_error( $result ) ) {
			return array(
				'ok'    => false,
				'code'  => 0,
				'body'  => '',
				'error' => $result->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $result );
		$body = wp_remote_retrieve_body( $result );

		if ( ! is_string( $body ) ) {
			$body = '';
		}

		$failed = qs_cf7_api_response_failed( $code, false );

		return array(
			'ok'    => ! $failed,
			'code'  => $code,
			'body'  => $body,
			'error' => $failed ? 'HTTP ' . $code : '',
		);
	}

	/**
	 * Visitor message for a failed call.
	 *
	 * @param object $contact_form Contact form.
	 * @param array  $settings     Settings.
	 * @param array  $interpreted  Interpreted result.
	 * @return string
	 */
	private function failure_message( $contact_form, $settings, $interpreted ) {
		if ( 'on' === $settings['override_message'] ) {
			if ( '' !== $interpreted['body'] ) {
				$from_body = $this->message_from_body( $interpreted['body'] );

				if ( '' !== $from_body ) {
					return $from_body;
				}
			}

			if ( '' !== $interpreted['error'] ) {
				return $this->trim_visitor_text( $interpreted['error'] );
			}
		}

		if ( is_object( $contact_form ) && method_exists( $contact_form, 'message' ) ) {
			return (string) $contact_form->message( 'mail_sent_ng' );
		}

		return __( 'The API request failed.', 'qs-cf7-api' );
	}

	/**
	 * Pull a message string out of a response body.
	 *
	 * @param string $body Response body.
	 * @return string
	 */
	private function message_from_body( $body ) {
		$decoded = json_decode( (string) $body );

		if ( JSON_ERROR_NONE === json_last_error() && is_object( $decoded ) && isset( $decoded->message ) && is_string( $decoded->message ) ) {
			return $this->trim_visitor_text( $decoded->message );
		}

		return $this->trim_visitor_text( $body );
	}

	/**
	 * Limit text shown to the visitor.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	private function trim_visitor_text( $text ) {
		$text = wp_strip_all_tags( (string) $text );

		if ( strlen( $text ) > 500 ) {
			$text = substr( $text, 0, 500 );
		}

		return trim( $text );
	}

	/**
	 * Debug row stored for the editor.
	 *
	 * @param array $interpreted Interpreted result.
	 * @return array<string,mixed>
	 */
	private function last_debug( $interpreted ) {
		$transport = $this->transport_debug;
		$code      = (string) $interpreted['code'];

		if ( '0' === $code ) {
			$code = '';
		}

		return array(
			'url'          => isset( $transport['url'] ) ? (string) $transport['url'] : '',
			'params'       => isset( $transport['params'] ) ? (string) $transport['params'] : '',
			'code'         => $code,
			'body'         => $this->truncate( $interpreted['body'] ),
			'error'        => $this->truncate( $interpreted['error'] ),
			'header_names' => isset( $transport['header_names'] ) && is_array( $transport['header_names'] ) ? $transport['header_names'] : array(),
		);
	}

	/**
	 * Persist the last call. Header values are not stored.
	 *
	 * @param object $contact_form Contact form.
	 * @param array  $debug        Debug row.
	 * @return void
	 */
	private function store_debug( $contact_form, $debug ) {
		if ( ! is_object( $contact_form ) || ! method_exists( $contact_form, 'id' ) ) {
			return;
		}

		$debug['params'] = $this->truncate( isset( $debug['params'] ) ? $debug['params'] : '' );
		$debug['body']   = $this->truncate( isset( $debug['body'] ) ? $debug['body'] : '' );
		$debug['error']  = $this->truncate( isset( $debug['error'] ) ? $debug['error'] : '' );

		update_post_meta( $contact_form->id(), '_qs_cf7_api_debug', $debug );
		delete_post_meta( $contact_form->id(), 'api_errors' );
	}

	/**
	 * Short preview of a request body for the debug log.
	 *
	 * @param mixed $value Body.
	 * @return string
	 */
	private function preview_value( $value ) {
		if ( is_array( $value ) ) {
			$encoded = qs_cf7_api_json_encode( $value );
			return $this->truncate( $encoded );
		}

		return $this->truncate( qs_cf7_api_flatten_template( $value ) );
	}

	/**
	 * Cap stored debug text.
	 *
	 * @param mixed $text Text.
	 * @return string
	 */
	private function truncate( $text ) {
		$text = (string) $text;

		if ( strlen( $text ) > 8192 ) {
			return substr( $text, 0, 8192 );
		}

		return $text;
	}

	/**
	 * Allow-list helper.
	 *
	 * @param mixed  $value   Candidate.
	 * @param array  $allowed Allowed values.
	 * @param string $default Fallback.
	 * @return string
	 */
	private function allow( $value, $allowed, $fallback ) {
		$value = (string) $value;

		if ( in_array( $value, $allowed, true ) ) {
			return $value;
		}

		return $fallback;
	}

	/**
	 * Name, basetype, and values for a form-tag object or array.
	 *
	 * @param mixed $tag Form-tag.
	 * @return array{name:string,basetype:string,values:array}
	 */
	private function tag_parts( $tag ) {
		$name     = '';
		$basetype = '';
		$values   = array();

		if ( is_object( $tag ) ) {
			$name     = isset( $tag->name ) ? (string) $tag->name : '';
			$basetype = isset( $tag->basetype ) ? (string) $tag->basetype : '';
			$values   = ( isset( $tag->values ) && is_array( $tag->values ) ) ? $tag->values : array();
		} elseif ( is_array( $tag ) ) {
			$name     = isset( $tag['name'] ) ? (string) $tag['name'] : '';
			$basetype = isset( $tag['basetype'] ) ? (string) $tag['basetype'] : '';

			if ( '' === $basetype && isset( $tag['type'] ) ) {
				$basetype = rtrim( (string) $tag['type'], '*' );
			}

			$values = ( isset( $tag['values'] ) && is_array( $tag['values'] ) ) ? $tag['values'] : array();
		}

		return array(
			'name'     => trim( $name ),
			'basetype' => trim( $basetype ),
			'values'   => $values,
		);
	}
}
