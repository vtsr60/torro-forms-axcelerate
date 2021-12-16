<?php
/**
 * Dropdown with Title element type class
 *
 * @package TorroFormsaXcelerate
 * @since 1.0.0
 */

namespace TFaXcelerate\TorroFormsaXcelerate\Elements\Element_Types;

use awsmug\Torro_Forms\DB_Objects\Elements\Element_Types\Base\Dropdown;
use TFaXcelerate\TorroFormsaXcelerate\Title_Choice_Element_Type_Trait;

/**
 * Class representing a dropdown with title element type.
 *
 * @since 1.0.0
 */
class DropdownWithTitle extends Dropdown
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
		$this->slug = 'dropdownwithtitle';
		$this->title = __('Dropdown With Different Title', 'torro-forms');
		$this->description = __('A dropdown element to select a value from and choices has different title.', 'torro-forms');
	}


}
