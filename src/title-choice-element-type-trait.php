<?php
/**
 * Choice element type trait
 *
 * @package TorroForms
 * @since 1.0.0
 */

namespace TFaXcelerate\TorroFormsaXcelerate;

/**
 * Trait for element type that support choices.
 *
 * @since 1.0.0
 */
trait Title_Choice_Element_Type_Trait {

	protected $title_delemiter = '|';

	public function filter_json( $data, $element, $submission = null ) {
		$data = parent::filter_json( $data, $element, $submission);
		$data['choices'] = $this->split_value_title($data['choices']);
		return $data;
	}

	public function get_choices_for_field( $element, $field = '' ) {
		$choices = parent::get_choices_for_field($element, $field);
		$choices = $this->split_value_title($choices);
		return array_keys($choices);
	}

	public function get_edit_submission_fields_args( $element ) {
		$fields = parent::get_edit_submission_fields_args( $element );
		$slug = $this->get_edit_submission_field_slug( $element->id );

		$fields[ $slug ]['choices'] = $this->split_value_title($fields[ $slug ]['choices']);
		return $fields;
	}

	public function split_value_title($choices) {
		$split_choices = array();
		foreach ($choices as $choice) {
			list($value, $title) = explode($this->title_delemiter, $choice);
			$value = trim($value);
			$title = trim($title);
			if (!isset($title) || empty($title)) {
				$title = $value;
			}
			if (!isset($value) || empty($value)) {
				$value = $title;
			}
			$split_choices[$value] = $title;
		}
		return $split_choices;
	}

}
