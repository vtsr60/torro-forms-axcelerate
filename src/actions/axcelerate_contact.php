<?php
/**
 * AXcelerate Contact action class
 *
 * @package TorroFormsaXcelerate
 * @since 1.1.0
 */

namespace TFaXcelerate\TorroFormsaXcelerate\Actions;

use awsmug\Torro_Forms\Components\Template_Tag_Handler;
use awsmug\Torro_Forms\DB_Objects\Elements\Element_Types\Base\Textfield;
use awsmug\Torro_Forms\Modules\Actions\Action;
use awsmug\Torro_Forms\DB_Objects\Forms\Form;
use awsmug\Torro_Forms\DB_Objects\Submissions\Submission;
use awsmug\Torro_Forms\Modules\Assets_Submodule_Interface;
use Leaves_And_Love\Plugin_Lib\Fixes;
use WP_Error;

/**
 * Class for an action that creates aXcelerate contact record via the aXcelerate REST API.
 *
 * @since 1.1.0
 */
class AXcelerate_Contact extends Action implements Assets_Submodule_Interface
{

	const CONTACT_PATH = '/contact/';

	/**
	 * Template tag handler for email notifications.
	 *
	 * @since 1.0.0
	 * @var Template_Tag_Handler
	 */
	protected $template_tag_handler;

	/**
	 * Bootstraps the submodule by setting properties.
	 *
	 * @since 1.0.0
	 */
	protected function bootstrap()
	{
		$this->slug = 'axcelerate_contact';
		$this->title = __('aXcelerate Contact Create', 'torro-forms');
		$this->description = __('On submission of this form aXcelerate contact record can be created by sending submission data.', 'torro-forms');
		$this->register_template_tag_handlers();
	}

	/**
	 * Handles the action for a specific form submission.
	 *
	 * @param Submission $submission Submission to handle by the action.
	 * @param Form $form Form the submission applies to.
	 * @return bool|WP_Error True on success, error object on failure.
	 * @since 1.0.0
	 *
	 */
	public function handle($submission, $form)
	{
		$enabled = $this->get_form_option($form->id, 'enabled', array());
		if (!$enabled) {
			return true;
		}
		$fieldsMapping = $this->get_form_option($form->id, 'fieldsmapping', array());
		if (!isset($fieldsMapping) || count($fieldsMapping) <= 0) {
			return true;
		}

		$dynamic_template_tags = $this->get_dynamic_template_tags($form, true);

		foreach ($dynamic_template_tags as $slug => $data) {
			$this->template_tag_handler->add_tag($slug, $data);
		}

		$payload = array();
		foreach ($fieldsMapping as $key => $value) {
			if (isset($value) && !empty($value)) {
				$payload[$key] = $this->template_tag_handler->process_content($value, array($form, $submission));
			}
		}
		$api_base_url = $this->get_option('api_base_url');
		$api_token = $this->get_option('api_token');
		$ws_token = $this->get_option('ws_token');
		$endpoint = $api_base_url . self::CONTACT_PATH;

		$request = array(
			'method' => 'POST',
			'headers' => array(
				'Accept' => 'application/json',
				'apitoken' => $api_token,
				'wstoken' => $ws_token,
			),
			'body' => $payload
		);

		$response = wp_remote_post($endpoint, $request);
		$response_code = (int)wp_remote_retrieve_response_code($response);
		$response_body = json_decode(wp_remote_retrieve_body($response), true);
		$contactCreated = !is_wp_error($response) && 200 == $response_code
			&& isset($response_body) && !isset($response_body['ERROR']);

		$notifications = $this->get_form_option($form->id, 'responsenotifications', array());
		$message = $contactCreated
			? "<h1 style='color: darkgreen;'>SUCCESSFULLY created Contact record in AXcelerate</h1>"
			: "<h1 style='color: darkred;'>FAILED to create Contact record in AXcelerate</h1>";
		$message .= "<hr /><h3>REQUEST</h3><pre>"
			. json_encode($payload, JSON_PRETTY_PRINT) . "</pre>";
		$message .= "<hr /><h3>RESPONSE</h3><pre>"
			. json_encode($response_body, JSON_PRETTY_PRINT) . "</pre><hr />";
		add_filter('wp_mail_content_type', array($this, 'override_content_type'));
		foreach ($notifications as $notification) {
			$message = $this->wrap_message(wpautop($message), $notification['subject']);

			if (empty($notification['to_email']) || empty($notification['subject']) || empty($notification['notification_type'])) {
				continue;
			}
			if (($notification['notification_type'] == 'failed' && $contactCreated)
				|| ($notification['notification_type'] == 'success' && !$contactCreated)) {
				continue;
			}
			$headers = array();
			if (!empty($notification['cc_email'])) {
				$headers[] = "Cc:" . trim($notification['cc_email']);
			}
			@wp_mail($notification['to_email'], $notification['subject'], $message, $headers);
		}
		remove_filter('wp_mail_content_type', array($this, 'override_content_type'));
		return true;
	}

	/**
	 * Gets the email content type.
	 *
	 * @return string Email content type.
	 * @since 1.0.0
	 *
	 */
	public function override_content_type()
	{
		return 'text/html';
	}

	/**
	 * Wraps the message in valid presentational HTML markup.
	 *
	 * @param string $message HTML message to wrap.
	 * @param string $title Optional. String to use in the title tag. Default empty string for no title.
	 * @return string Wrapped HTML message.
	 * @since 1.0.0
	 *
	 */
	protected function wrap_message($message, $title = '')
	{
		$before = '<!DOCTYPE html>';
		$before .= '<html>';
		$before .= '<head>';
		$before .= '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />';
		if (!empty($title)) {
			$before .= '<title>' . esc_html($title) . '</title>';
		}
		$before .= '</head>';
		$before .= '<body>';

		$after = '</body>';
		$after .= '</html>';

		return $before . $message . $after;
	}

	/**
	 * Returns the available meta fields for the submodule.
	 *
	 * @return array Associative array of `$field_slug => $field_args` pairs.
	 * @since 1.0.0
	 *
	 */
	public function get_meta_fields()
	{
		$meta_fields = parent::get_meta_fields();
		$meta_fields['enabled']['visual_label'] = _x('Create contact on submission', 'action', 'torro-forms');

		$contactFields = array();
		foreach ($this->get_contact_fields() as $field => $desc) {
			$contactFields[$field] = array(
				'type' => 'templatetagtext',
				'label' => __(preg_replace('~([a-z])([A-Z])~', '\\1 \\2', ucfirst($field)), 'torro-forms'),
				/* translators: %s: email address */
				'description' => __($desc, 'torro-forms'),
				'input_classes' => array('regular-text'),
				'template_tag_handler' => $this->template_tag_handler,
			);
		}

		$meta_fields['fieldsmapping'] = array(
			'type' => 'group',
			'label' => __('Fields Mapping', 'torro-forms'),
			'fields' => $contactFields
		);

		$meta_fields['responsenotifications'] = array(
			'type' => 'group',
			'label' => __('Response Notifications', 'torro-forms'),
			'repeatable' => 8,
			'fields' => array(
				'notification_type' => array(
					'type' => 'select',
					'label' => __('Notification Type', 'torro-forms'),
					'choices' => array(
						'failed' => __('Only if contact creation failed', 'torro-forms'),
						'success' => __('Only if contact was created', 'torro-forms'),
						'all' => __('All submission', 'torro-forms')
					),
					'default' => 'failed',
				),
				'to_email' => array(
					'type' => 'text',
					'label' => __('To Email', 'torro-forms'),
					'input_classes' => array('regular-text')
				),
				'cc_email' => array(
					'type' => 'text',
					'label' => _x('Cc', 'email', 'torro-forms'),
					'input_classes' => array('regular-text'),
				),
				'subject' => array(
					'type' => 'text',
					'label' => __('Subject', 'torro-forms'),
					'input_classes' => array('regular-text'),
				)
			),
		);

		return $meta_fields;
	}

	/**
	 * Registers the template tag handler for email notifications.
	 *
	 * @since 1.0.0
	 */
	protected function register_template_tag_handlers()
	{
		$tags = array(
			'sitetitle' => array(
				'group' => 'global',
				'label' => __('Site Title', 'torro-forms'),
				'description' => __('Inserts the site title.', 'torro-forms'),
				'callback' => function () {
					return get_bloginfo('name');
				},
			),
			'siteurl' => array(
				'group' => 'global',
				'label' => __('Site URL', 'torro-forms'),
				'description' => __('Inserts the site home URL.', 'torro-forms'),
				'callback' => function () {
					return home_url('/');
				},
			),
			'adminemail' => array(
				'group' => 'global',
				'label' => __('Site Admin Email', 'torro-forms'),
				'description' => __('Inserts the site admin email.', 'torro-forms'),
				'callback' => function () {
					return get_option('admin_email');
				},
			),
			'userip' => array(
				'group' => 'global',
				'label' => __('User IP', 'torro-forms'),
				'description' => __('Inserts the current user IP address.', 'torro-forms'),
				'callback' => function () {
					$validated_ip = Fixes::php_filter_input(INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP);
					if (empty($validated_ip)) {
						return '0.0.0.0';
					}
					return $validated_ip;
				},
			),
			'refererurl' => array(
				'group' => 'global',
				'label' => __('Referer URL', 'torro-forms'),
				'description' => __('Inserts the current referer URL.', 'torro-forms'),
				'callback' => function () {
					return wp_get_referer();
				},
			),
			'formtitle' => array(
				'group' => 'form',
				'label' => __('Form Title', 'torro-forms'),
				'description' => __('Inserts the form title.', 'torro-forms'),
				'callback' => function ($form) {
					return $form->title;
				},
			),
			'formurl' => array(
				'group' => 'form',
				'label' => __('Form URL', 'torro-forms'),
				'description' => __('Inserts the URL to the form.', 'torro-forms'),
				'callback' => function ($form) {
					return get_permalink($form->id);
				},
			),
			'formediturl' => array(
				'group' => 'form',
				'label' => __('Form Edit URL', 'torro-forms'),
				'description' => __('Inserts the edit URL for the form.', 'torro-forms'),
				'callback' => function ($form) {
					return get_edit_post_link($form->id);
				},
			),
			'submissionurl' => array(
				'group' => 'submission',
				'label' => __('Submission URL', 'torro-forms'),
				'description' => __('Inserts the URL to the submission.', 'torro-forms'),
				'callback' => function ($form, $submission) {
					return add_query_arg('torro_submission_id', $submission->id, get_permalink($form->id));
				},
			),
			'submissionediturl' => array(
				'group' => 'submission',
				'label' => __('Submission Edit URL', 'torro-forms'),
				'description' => __('Inserts the edit URL for the submission.', 'torro-forms'),
				'callback' => function ($form, $submission) {
					return add_query_arg(
						array(
							'post_type' => torro()->post_types()->get_prefix() . 'form',
							'page' => torro()->admin_pages()->get_prefix() . 'edit_submission',
							'id' => $submission->id,
						),
						admin_url('edit.php')
					);
				},
			),
			'submissiondatetime' => array(
				'group' => 'submission',
				'label' => __('Submission Date and Time', 'torro-forms'),
				'description' => __('Inserts the submission date and time.', 'torro-forms'),
				'callback' => function ($form, $submission) {
					$date = $submission->format_datetime(get_option('date_format'), false);
					$time = $submission->format_datetime(get_option('time_format'), false);

					/* translators: 1: formatted date, 2: formatted time */
					return sprintf(_x('%1$s at %2$s', 'concatenating date and time', 'torro-forms'), $date, $time);
				},
			),
		);

		$complex_tags = array(
			'allelements' => array(
				'group' => 'submission',
				'label' => __('All Element Values', 'torro-forms'),
				'description' => __('Inserts all element values from the submission.', 'torro-forms'),
				'callback' => function ($form, $submission) {
					$element_columns = array();
					foreach ($form->get_elements() as $element) {
						$element_type = $element->get_element_type();
						if (!$element_type) {
							continue;
						}

						$element_columns[$element->id] = array(
							'columns' => $element_type->get_export_columns($element),
							'callback' => function ($values) use ($element, $element_type) {
								return $element_type->format_values_for_export($values, $element, 'html');
							},
						);
					}

					$element_values = $submission->get_element_values_data();

					$output = '<table style="width:100%;border-spacing:0; font-family: Arial, Helvetica, sans-serif;">';

					$i = 0;

					foreach ($element_columns as $element_id => $data) {
						$bg_color = ($i % 2) === 1 ? '#ffffff' : '#f2f2f2';

						$values = isset($element_values[$element_id]) ? $element_values[$element_id] : array();

						$column_values = call_user_func($data['callback'], $values);

						foreach ($data['columns'] as $slug => $label) {
							$output .= '<tr style="background-color:' . $bg_color . '"">';
							$output .= '<th scope="row" style="text-align:left;vertical-align: top; width:25%; padding: 10px;">' . esc_html($label) . '</th>';
							$output .= '<td style="padding: 10px;">' . wp_kses_post($column_values[$slug]) . '</td>';
							$output .= '</tr>';
						}

						$i++;
					}

					$output .= '</table>';

					return $output;
				},
			),
		);

		$groups = array(
			'submission' => _x('Submission', 'template tag group', 'torro-forms'),
			'form' => _x('Form', 'template tag group', 'torro-forms'),
			'global' => _x('Global', 'template tag group', 'torro-forms'),
		);

		$this->template_tag_handler = new Template_Tag_Handler($this->slug, array_merge($tags, $complex_tags), array(Form::class, Submission::class), $groups);

		$this->module->manager()->template_tag_handlers()->register($this->template_tag_handler);
	}

	/**
	 * Gets all the dynamic template tags for a form, consisting of the form's element value tags.
	 *
	 * @param Form $form Form for which to get the dynamic template tags.
	 * @param bool $back_compat Optional. Whether to also include back-compat keys for Torro Forms before 1.0.0-beta.9. Default false.
	 * @return array Dynamic tags as `$slug => $data` pairs.
	 * @since 1.0.0
	 *
	 */
	protected function get_dynamic_template_tags($form, $back_compat = false)
	{
		$tags = array();

		foreach ($form->get_elements() as $element) {
			$element_type = $element->get_element_type();
			if (!$element_type) {
				continue;
			}

			$tags['value_element_' . $element->id] = array(
				'group' => 'submission',
				/* translators: %s: element label */
				'label' => sprintf(__('Value for &#8220;%s&#8221;', 'torro-forms'), $element->label),
				/* translators: %s: element label */
				'description' => sprintf(__('Inserts the submission value for the element &#8220;%s&#8221;.', 'torro-forms'), $element->label),
				'callback' => function ($form, $submission) use ($element, $element_type) {
					$element_values = $submission->get_element_values_data();
					if (!isset($element_values[$element->id])) {
						return '';
					}

					add_filter("{$this->module->manager()->get_prefix()}use_single_export_column_for_choices", '__return_true');
					$export_values = $element_type->format_values_for_export($element_values[$element->id], $element, 'html');
					remove_filter("{$this->module->manager()->get_prefix()}use_single_export_column_for_choices", '__return_true');

					if (!isset($export_values['element_' . $element->id . '__main'])) {
						if (count($export_values) !== 1) {
							return '';
						}

						return array_pop($export_values);
					}

					return $export_values['element_' . $element->id . '__main'];
				},
			);

			// Add email support to text fields with input_type 'email_address'.
			if (is_a($element_type, Textfield::class)) {
				$settings = $element_type->get_settings($element);
				if (!empty($settings['input_type']) && 'email_address' === $settings['input_type']) {
					$tags['value_element_' . $element->id]['email_support'] = true;
				}
			}

			if ($back_compat) {
				$tags[$element->label . ':' . $element->id] = $tags['value_element_' . $element->id];
			}
		}

		return $tags;
	}

	/**
	 * List of contact fields in the REST API
	 *
	 * @return string[]
	 */
	private function get_contact_fields()
	{
		return [
			"givenName" => "Given (first) name. Maximum 40 characters.",
			"surname" => "Surname (last name). Maximum 40 characters.",
			"title" => "Tile/salutation",
			"emailAddress" => "Email address. Must be a valid email address",
			"ContactActive" => "Retrieve Active Contact Records. Passing false will return inactive records.",
			"dob" => "Date of Birth in the format YYYY-MM-DD. This cannot be dated in the future.",
			"sex" => "Must be only a single letter. Valid values are M, F or X (for Other)",
			"middleName" => "Middle name(s). Maximum 40 characters.",
			"phone" => "Home phone number",
			"mobilephone" => "Mobile phone number",
			"workphone" => "Work phone number",
			"fax" => "Fax number",
			"organisation" => "Organisation name",
			"position" => "Position within the organisation",
			"section" => "Section within the organisation",
			"division" => "Division within the organisation",
			"SourceCodeID" => "The client-specific sourceID from the list set up in aXcelerate and returned from the contact/sources resource.",
			"Password" => "An optional password that can be stored against the contact/student. This password could then be used to login to the student portal. No minimum strength checking is performed by the service. It is up to the API implementors to enforce their own password policies.",
			"HistoricClientID" => "The Historical ID used with the student record. IMPORTANT IF: you have reported a person previously with a different SMS.",
			"USI" => "Unique Student Identifier (USI). A 10-digit code. If passed, this code must conform to the minimum validation: It must be 10 digits in length and consist of only capital letters and numbers, excluding I, 1, 0 and O.",
			"LUI" => "The Queensland Studies Authority Learner Unique Identifier (LUI). A 10-digit, numeric code.",
			"TFN" => "The student's Tax File Number, used for VET Student Loans reporting. The number must pass the ATO's check alorithm.",
			"VSN" => "The Victorian Student Number The VSN is a student identification number that is assigned by the Department of Education and Early Childhood Development to all students in government and non-government schools, and students in Vocational Education and Training institutions.",
			"WorkReadyParticipantNumber" => "This field was formerly knows as 'Skills for All Number'. A South Australian student identifier.",
			"SACEStudentID" => "South Australian Certificate of Education student identifier. It should consist of six numbers and one alpha letter, for e.g., 123456A.",
			"EmergencyContact" => "Name of an emergency contact",
			"EmergencyContactRelation" => "The relationship of the emergency contact (eg sister)",
			"EmergencyContactPhone" => "The phone number of the emergency contact",
			"buildingName" => "AVETMISS 7.0 fields - will take these fields over address1 and address2 if passed. Note, you cannot search contacts by these discrete fields.",
			"unitNo" => "AVETMISS 7.0 fields",
			"streetNo" => "AVETMISS 7.0 fields",
			"streetName" => "AVETMISS 7.0 fields",
			"POBox" => "AVETMISS 7.0 fields",
			"address1" => "First line of POSTAL address - address fields will be used only if AVETMISS 7.0 fields not passed. The return structure will also include these fields.",
			"address2" => "Second line of postal address",
			"city" => "Postal suburb, locality or town",
			"state" => "Postal state / Territory. NOTE: This field is tailored for Australia and accepts only the following values: NSW, VIC, QLD, SA, WA, TAS, NT, ACT or OTH (which means 'Other Australian Territory') or OVS (which means 'Overseas')",
			"postcode" => "Postal postcode",
			"countryID" => "Postal Country - a 4-digit SACC code. This is only used if the full AVETMISS 7.0 address details are passed. Otherwise this field is ignored. Use Country instead.",
			"country" => "Postal Country",
			"sbuildingName" => "AVETMISS 7.0 fields - will take these fields over address1 and address2 if passed. Note, you cannot search contacts by these discrete fields.",
			"sunitNo" => "AVETMISS 7.0 fields",
			"sstreetNo" => "AVETMISS 7.0 fields",
			"sstreetName" => "AVETMISS 7.0 fields",
			"sPOBox" => "AVETMISS 7.0 fields",
			"saddress1" => "First line of STREET / residential address - address fields will be deprecated with the coming of AVETMISS 7.0 which will break the address into further fields (eg Flat/unit number, Street Name)",
			"saddress2" => "Second line of street / residential address - to be deprecated in the future",
			"scity" => "Residential suburb, locality or town",
			"sstate" => "Residential state / Territory. NOTE: This field is tailored for Australia and accepts only the following values: NSW, VIC, QLD, SA, WA, TAS, NT, ACT or OTH (which means 'Other Australian Territory') or OVS (which means 'Overseas')",
			"spostcode" => "Residential postcode",
			"scountryID" => "Residential Country - a 4-digit SACC code. This is only used if the full AVETMISS 7.0 address details are passed. Otherwise this field is ignored. Use SCountry instead.",
			"scountry" => "Residential Country",
			"termAddress1" => "First line of TERM address. Term address is only available for clients who have enabled the VET Student Loan feature.",
			"termAddress2" => "Second line of term address",
			"termCity" => "Term Address suburb, locality or town",
			"termState" => "Term Address state / Territory. NOTE: This field is tailored for Australia and accepts only the following values: NSW, VIC, QLD, SA, WA, TAS, NT, ACT or OTH (which means 'Other Australian Territory') or OVS (which means 'Overseas')",
			"termPostcode" => "Term Address postcode",
			"termCountryID" => "Term Address Country - a 4-digit SACC code. You can use this or termCountry",
			"termCountry" => "Term Address Country",
			"CountryofBirthID" => "Country of Birth as a valid 4-digit SACC code. For a list of codes, please refer to NCVER website www.ncver.edu.au",
			"CityofBirth" => "City of Birth. This data is used if later creating a USI for this contact.",
			"CountryofCitizenID" => "Country of citizenship as a valid 4-digit SACC code. For a list of codes, please refer to NCVER website www.ncver.edu.au",
			"CitizenStatusID" => "Citizenship status. The valid values are numbers 1-11. For a list of the meanings of these numbers, please contact aXcelerate",
			"LabourForceID" => "AVETMISS Labour force status identifier. For valid values see the AVETMISS documentation",
			"MainLanguageID" => "AVETMISS Main language other than English spoken at home identifier as a valid 4-digit SACC code. For a list of codes, please refer to NCVER website www.ncver.edu.au",
			"EnglishProficiencyID" => "AVETMISS Proficiency in spoken English. For valid values see the AVETMISS documentation",
			"EnglishAssistanceFlag" => "A true/false flag to indicate whether English assistance is required",
			"HighestSchoolLevelID" => "AVETMISS highest school level completed identifier. For valid values see the AVETMISS documentation",
			"HighestSchoolLevelYear" => "The year the highest school level was completed. Must be a valid year no later than the current year",
			"AtSchoolFlag" => "A true/false AVETMISS flag to indicate whether the contact is currently at secondary school",
			"AtSchoolName" => "The name of the contact's current secondary school",
			"PriorEducationStatus" => "A true/false AVETMISS flag to indicate whether a contact has successfully completed some post-secondary education. A true value here will be ignored without also passing PriorEducationIDs.",
			"PriorEducationIDs" => "A comma delimited list of AVETMISS prior educational achievement identifiers to indicate prior higher education. For valid values see the AVETMISS documentation. The Victorian AVETMISS field Prior Educational Achievement Recognition Identifier may be appended (e.g. 420A). Also accepts @ if not specified.",
			"DisabilityFlag" => "A true/false AVETMISS flag to indicate whether the contact considers themselves to have a disability, impairment or long-term condition",
			"DisabilityTypeIDs" => "A comma delimited list of AVETMISS disability type identifiers. For valid values see the AVETMISS documentation",
			"IndigenousStatusID" => "AVETMISS indigenous status identifier to indicates a contact who self-identifies as being of Australian Aboriginal or Torres Strait Islander descent. For valid values see the AVETMISS documentation",
			"ANZSCOCode" => "Australian and New Zealand Standard Classification of Occupations (ANZSCO), ABS catalogue no.1220.0, 2009. The major group of this code is used for the Victorian AVETMISS field Client Occupation Identifier (left most digit)",
			"ANZSICCode" => "Australian and New Zealand Standard Industrial Classification (ANZSIC), ABS catalogue no.1292.0, 2006. The division of this code is used for the Victorian AVETMISS field Client Industry of Employment (see ABS catalogue)",
			"SurveyContactStatusCode" => "AVETMISS 8.0 Survey contact status - Survey contact status identifies reasons to exclude clients from the Student Outcomes Survey and other communications. For a list of possible codes, please refer to the data definitions on the NCVER website AVETMISS Data element definitions",
			"EmailAddressAlternative" => "AVETMISS 8.0 Alternate email address",
			"employerContactID" => "The ContactID of the Contact Record who is the Employer of this Contact.",
			"payerContactID" => "The ContactID of the Contact Record who is the Payer of this Contact.",
			"supervisorContactID" => "The ContactID of the Contact Record who is the Supervisor of this Contact.",
			"agentContactID" => "The ContactID of the Contact Record who is the Agent for this Contact.",
			"coachContactID" => "The ContactID of the Contact Record who is the Coach for this Contact.",
			"internationalContactID" => "The ContactID of the Contact Record who is the International Contact for this Contact (CRICOS).",
			"optionalID" => "An optional identifier to use for this Contact record.",
			"categoryIDs" => "A list of valid category IDs. (adds to Contact ONLY)",
			"domainIDs" => "A list of domainIDs to set against the contact. This will overwrite any existing domains against the contact. Requires the Contact Domains feature to be enabled in the account.",
		];
	}

	/**
	 * Registers all assets the submodule provides.
	 *
	 * @param Assets $assets The plugin assets instance.
	 * @since 1.0.0
	 *
	 */
	public function register_assets($assets)
	{
		$template_tag_template = '<li class="template-tag template-tag-%slug%">';
		$template_tag_template .= '<button type="button" class="template-tag-button" data-tag="%slug%">%label%</button>';
		$template_tag_template .= '</li>';

		$template_tag_group_template = '<li class="template-tag-list-group template-tag-list-group-%slug%">';
		$template_tag_group_template .= '<span>%label%</span>';
		$template_tag_group_template .= '<ul></ul>';
		$template_tag_group_template .= '</li>';

		$assets->register_script(
			'admin-axcelerate_contact',
			plugins_url('torro-forms-axcelerate/assets/dist/js/admin-axcelerate-contact.js'),
			array(
				'deps' => array('jquery', 'torro-template-tag-fields', 'torro-admin-form-builder'),
				'in_footer' => true,
				'localize_name' => 'torroAXcelerateContact',
				'localize_data' => array(
					'templateTagGroupTemplate' => $template_tag_group_template,
					'templateTagTemplate' => $template_tag_template,
					'templateTagSlug' => 'value_element_%element_id%',
					'templateTagGroup' => 'submission',
					'templateTagGroupLabel' => _x('Submission', 'template tag group', 'torro-forms'),
					/* translators: %s: element label */
					'templateTagLabel' => sprintf(__('Value for &#8220;%s&#8221;', 'torro-forms'), '%element_label%'),
					/* translators: %s: element label */
					'templateTagDescription' => sprintf(__('Inserts the submission value for the element &#8220;%s&#8221;.', 'torro-forms'), '%element_label%'),
				),
			)
		);
	}

	/**
	 * Enqueues scripts and stylesheets on the form editing screen.
	 *
	 * @param Assets $assets The plugin assets instance.
	 * @since 1.0.0
	 *
	 */
	public function enqueue_form_builder_assets($assets)
	{
		$assets->enqueue_script('admin-axcelerate_contact');
	}

	/**
	 * Returns the available settings sections for the submodule.
	 *
	 * @return array Associative array of `$section_slug => $section_args` pairs.
	 * @since 1.0.0
	 *
	 */
	public function get_settings_sections()
	{
		$settings_sections = parent::get_settings_sections();

		$settings_sections['axcelerate'] = array(
			'title' => _x('aXcelerate Intergation', 'AXcelerate', 'torro-forms'),
		);

		return $settings_sections;
	}


	/**
	 * Returns the available settings fields for the submodule.
	 *
	 * @return array Associative array of `$field_slug => $field_args` pairs.
	 * @since 1.0.0
	 *
	 */
	public function get_settings_fields()
	{
		$settings_fields = parent::get_settings_fields();

		$settings_fields['api_base_url'] = array(
			'section' => 'axcelerate',
			'type' => 'text',
			'label' => _x('API Base URL', 'AXcelerate', 'torro-forms'),
			'description' => sprintf(__('The public site key of your website for Google reCAPTCHA. You can get one <a href="%s" target="_blank">here</a>.', 'torro-forms'), 'https://www.google.com/recaptcha/admin'),
			'input_classes' => array('regular-text'),
		);

		$settings_fields['api_token'] = array(
			'section' => 'axcelerate',
			'type' => 'text',
			'label' => _x('API Token', 'AXcelerate', 'torro-forms'),
			/* translators: %s: URL to Google reCAPTCHA console */
			'description' => sprintf(__('The secret key of your website for Google reCAPTCHA. You can get one <a href="%s" target="_blank">here</a>.', 'torro-forms'), 'https://www.google.com/recaptcha/admin'),
			'input_classes' => array('regular-text'),
		);

		$settings_fields['ws_token'] = array(
			'section' => 'axcelerate',
			'type' => 'text',
			'label' => _x('WS Token', 'AXcelerate', 'torro-forms'),
			/* translators: %s: URL to Google reCAPTCHA console */
			'description' => sprintf(__('The secret key of your website for Google reCAPTCHA. You can get one <a href="%s" target="_blank">here</a>.', 'torro-forms'), 'https://www.google.com/recaptcha/admin'),
			'input_classes' => array('regular-text'),
		);

		return $settings_fields;
	}

}
