<?php
namespace Engine\Validator;

use Phalcon\Validation\ValidatorInterface;

class Numericality extends \Phalcon\Validation\Validator implements ValidatorInterface {

	public function validate($validator, $attribute) {
		/**
		 * @var $validator \Phalcon\Validation
		 */
		$value = $validator->getValue($attribute);

		if ( empty( $value ) ) {
			return true;
		}

		$test_value = \Library\Tools\Number::filterPrice($value);

		if ( $value != $test_value ) {

			$message = $this->getOption('message');
      if (!$message) {
          $message = "Некорректный формат числа с плавающей точкой";
      }

			$validator->appendMessage(new \Phalcon\Validation\Message($message, $attribute, 'Numericality'));

			return false;
		}

		return true;
	}
}