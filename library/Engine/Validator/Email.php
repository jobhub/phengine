<?php
namespace Engine\Validator;

use Phalcon\Validation\ValidatorInterface;

class Email extends \Phalcon\Validation\Validator\Email implements ValidatorInterface {

	public function validate($validator, $attribute) {
		/**
		 * @var $validator \Phalcon\Validation
		 */
		$value = $validator->getValue($attribute);

		if ( empty( $value ) ) {
			return true;
		}
		return parent::validate($validator, $attribute);
	}
}