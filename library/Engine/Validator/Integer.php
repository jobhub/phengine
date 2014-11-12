<?php
namespace Engine\Validator;

use Phalcon\Validation\ValidatorInterface;

class Integer extends \Phalcon\Validation\Validator implements ValidatorInterface {

	public function validate($validator, $attribute) {
		/**
		 * @var $validator \Phalcon\Validation
		 */
		$value = $validator->getValue($attribute);

		if ( empty( $value ) ) {
			return true;
		}
		if ( ! is_integer( $value ) && ! preg_match( '@^[+-]?\d+$@', $value ) ) {

			$message = $this->getOption('message');
      if (!$message) {
          $message = "В поле %fieldname% вводите только целое число";
      }

			$validator->appendMessage(new \Phalcon\Validation\Message($message, $attribute, 'Integer'));

			return false;
		}

		return true;
	}
}