<?php
namespace Engine\Validator;

use Phalcon\Validation\ValidatorInterface;

class Digits extends \Phalcon\Validation\Validator implements ValidatorInterface {

	public function validate($validator, $attribute) {
		/**
		 * @var $validator \Phalcon\Validation
		 */
		$value = $validator->getValue($attribute);

		if ( empty( $value ) ) {
			return true;
		}

		$isValid = true;

		if (!is_string($value) && !is_int($value) && !is_float($value)) {
        $isValid = false;

    } else {
			$_unicodeEnabled = ( @preg_match( '/\pL/u', 'a' ) ) ? true : false;

			if ( $_unicodeEnabled ) {
				$pattern = '/[^0-9]/';
			} else if ( extension_loaded( 'mbstring' ) ) {
				// Filter for the value with mbstring
				$pattern = '/[^[:digit:]]/';
			} else {
				// Filter for the value without mbstring
				$pattern = '/[\p{^N}]/';
			}

			$test_value = preg_replace( $pattern, '', (string) $value );

			if ( $value !== $test_value ) {
				$isValid = false;
			}

			if ( ! $isValid ) {
				$message = $this->getOption( 'message' );
				if ( ! $message ) {
					$message = "В поле %fieldname% вводите только числа";
				}

				$validator->appendMessage( new \Phalcon\Validation\Message( $message, $attribute, 'Digits' ) );
			}
		}

		return $isValid;

	}
}