<?php
/**
 * Choice with title element type trait
 *
 * @package TorroFormsaXcelerate
 * @since 1.0.0
 */

namespace TFaXcelerate\TorroFormsaXcelerate;

use awsmug\Torro_Forms\DB_Objects\Elements\Element;
use awsmug\Torro_Forms\DB_Objects\Submissions\Submission;

/**
 * Trait for element type that support choices with title.
 *
 * @since 1.0.0
 */
trait Title_Choice_Element_Type_Trait
{

	protected $title_delemiter = '|';

	/**
	 * Filters the array representation of a given element of this type.
	 *
	 * @param array $data Element data to filter.
	 * @param Element $element The element object to get the data for.
	 * @param Submission|null $submission Optional. Submission to get the values from, if available. Default null.
	 * @return array Array including all information for the element type.
	 * @since 1.0.0
	 *
	 */
	public function filter_json($data, $element, $submission = null)
	{
		$data = parent::filter_json($data, $element, $submission);
		$data['choices'] = $this->split_value_title($data['choices']);
		return $data;
	}

	/**
	 * Returns the available choices for a specific field.
	 *
	 * @param Element $element Element to get choices for.
	 * @param string $field Optional. Element field for which to get choices. Default empty string (main field).
	 * @return array Array of choices.
	 * @since 1.0.0
	 *
	 */
	public function get_choices_for_field($element, $field = '')
	{
		$choices = parent::get_choices_for_field($element, $field);
		$choices = $this->split_value_title($choices);
		return array_keys($choices);
	}

	/**
	 * Gets the fields arguments for an element of this type when editing submission values in the admin.
	 *
	 * @param Element $element Element to get fields arguments for.
	 * @return array An associative array of `$field_slug => $field_args` pairs.
	 * @since 1.0.0
	 *
	 */
	public function get_edit_submission_fields_args($element)
	{
		$fields = parent::get_edit_submission_fields_args($element);
		$slug = $this->get_edit_submission_field_slug($element->id);

		$fields[$slug]['choices'] = $this->split_value_title($fields[$slug]['choices']);
		return $fields;
	}

	/**
	 * Returns the splitted choices and its titles for a specific field.
	 *
	 * @param $choices Array of choices.
	 * @return array split Array of choices and title.
	 * @since 1.0.0
	 *
	 */
	public function split_value_title($choices)
	{
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
