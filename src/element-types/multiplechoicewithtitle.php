<?php
/**
 * Multiple with Title choice element type class
 *
 * @package TorroFormsaXcelerate
 * @since 1.0.0
 */


namespace TFaXcelerate\TorroFormsaXcelerate\Elements\Element_Types;

use awsmug\Torro_Forms\DB_Objects\Elements\Element;
use awsmug\Torro_Forms\DB_Objects\Elements\Element_Types\Base\Multiplechoice;
use awsmug\Torro_Forms\DB_Objects\Elements\Element_Types\Element_Type;
use awsmug\Torro_Forms\DB_Objects\Submissions\Submission;
use TFaXcelerate\TorroFormsaXcelerate\Title_Choice_Element_Type_Trait;

/**
 * Class representing a multiple choice with title element type.
 *
 * @since 1.0.0
 */
class MultiplechoiceWithTitle extends Multiplechoice
{

	use Title_Choice_Element_Type_Trait;

	/**
	 * Bootstraps the element type by setting properties.
	 *
	 * @since 1.0.0
	 */
	protected function bootstrap()
	{
		parent::bootstrap();
		$this->slug = 'multiplechoicewithtitle';
		$this->title = __('Multiple Choice With Different Title', 'torro-forms');
		$this->description = __('A checkbox group element to select multiple values and choices has different titles.', 'torro-forms');
	}

	/**
	 * Validates a field value for an element.
	 *
	 * @param mixed $value The value to validate. It is already unslashed when it arrives here.
	 * @param Element $element Element to validate the field value for.
	 * @param Submission $submission Submission the value belongs to.
	 * @return mixed|WP_Error Validated value, or error object on failure.
	 * @since 1.0.0
	 *
	 */
	public function validate_field($value, $element, $submission)
	{
		$settings = $this->get_settings($element);

		$value = (array)$value;

		if (!empty($settings['required']) && 'no' !== $settings['required'] && empty($value)) {
			return $this->create_error(Element_Type::ERROR_CODE_REQUIRED, __('You must select at least a single value here.', 'torro-forms'), $value);
		}

		if ((int)$settings['min_choices'] > 0 && count($value) < (int)$settings['min_choices']) {
			/* translators: %s: number of minimum choices */
			return $this->create_error('value_too_few_choices', sprintf(_n('You must select at least %s value.', 'You must select at least %s values.', $settings['min_choices'], 'torro-forms'), $settings['min_choices']), $value);
		}

		if ((int)$settings['max_choices'] > 0 && count($value) > (int)$settings['max_choices']) {
			/* translators: %s: number of maximum choices */
			return $this->create_error('value_too_much_choices', sprintf(_n('You may select a maximum of %s value.', 'You may select a maximum of %s values.', $settings['max_choices'], 'torro-forms'), $settings['max_choices']), $value);
		}
		if (!empty($value)) {
			$choices = $this->get_choices_for_field($element);
			$choices = array_keys($this->split_value_title($choices));
			foreach ($value as $single_value) {
				if (!in_array($single_value, $choices)) {
					return $this->create_error('value_invalid_choice', __('You must select valid values from the list.', 'torro-forms'), $value);
				}
			}
		}

		return $value;
	}

}
