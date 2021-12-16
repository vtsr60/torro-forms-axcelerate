<?php
/**
 * Dropdown element type class
 *
 * @package TorroForms
 * @since 1.0.0
 */

namespace TFaXcelerate\TorroFormsaXcelerate\Elements\Element_Types;

use awsmug\Torro_Forms\DB_Objects\Elements\Element_Types\Base\Onechoice;
use TFaXcelerate\TorroFormsaXcelerate\Title_Choice_Element_Type_Trait;

/**
 * Class representing a dropdown element type.
 *
 * @since 1.0.0
 */
class OneChoiceWithTitle extends Onechoice {

	use Title_Choice_Element_Type_Trait;

	/**
	 * Bootstraps the element type by setting properties.
	 *
	 * @since 1.0.0
	 */
	protected function bootstrap() {
		parent::bootstrap();
		$this->slug        = 'onechoicewithtitle';
		$this->title       = __( 'One Choice With Different Title', 'torro-forms' );
		$this->description = __( 'A radio group element to select a single value and choices has different titles.', 'torro-forms' );
	}


}
