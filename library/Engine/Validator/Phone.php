<?php
namespace Engine\Validator;

use Phalcon\Validation\ValidatorInterface;

class Phone extends \Phalcon\Validation\Validator implements ValidatorInterface {

	public function validate($validator, $attribute) {
		/**
		 * @var $validator \Phalcon\Validation
		 */
		$value = $validator->getValue($attribute);

		if ( empty( $value ) ) {
			return true;
		}

		$message = array();
		$isValid = true;

		$data_for_validation = explode( '-', $value );
		if ( empty( $data_for_validation[0] ) ) {
			$message[]= 'не заполнен код страны';
			$isValid = false;
		} elseif ( empty( $data_for_validation[1] ) ) {
			$message[]= 'не заполнен код города';
			$isValid          = false;
		} elseif ( empty( $data_for_validation[2] ) ) {
			$message[]= 'не заполнен номер телефона';
			$isValid           = false;
		} elseif ( \Library\Tools\String::strlen( $data_for_validation[2] ) < 5 ) {
			$message[] = 'значение поля номер должно быть не менее 5 знаков';
			$isValid           = false;
		}

		if(!$isValid) {
			$message = 'В поле "%fieldname%" '.join("\n\t", $message);

			$validator->appendMessage( new \Phalcon\Validation\Message( $message, $attribute, 'Phone' ) );
		}
		return $isValid;

	}
}