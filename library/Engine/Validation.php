<?php
/**
 * Created by PhpStorm.
 * User: patris
 * Date: 26.09.14
 * Time: 12:44
 */

namespace Engine;


class Validation extends \Phalcon\Validation {


	public function getMessages($fields) {
		$messages = array();
		foreach ( parent::getMessages() as $message ) {
			switch ( $message->getType() ) {
				case 'PresenceOf':
					$messages[] = 'Заполнение поля ' . $fields[$message->getField()] . ' обязательно';
					break;

				default:
					$messages[] = $message;
					break;
			}
		}

		return $messages;
	}



	private function _validatePositive() {
		if ( empty( $this->data ) ) {
			return $this->valid;
		}

		if ( ! is_integer( $this->data ) || 0 > $this->data ) {
			$this->errors['positive'] = "В поле %fieldname% вводите положительное целое число";
			$this->valid              = false;
		}

		return $this->valid;
	}


	private function _validateInn() {
		if ( empty( $this->data ) ) {
			return $this->valid;
		}
		switch ( strlen( $this->data ) ) {
			case 0:
				break;
			case 10:
				$weights = array( 2, 4, 10, 3, 5, 9, 4, 6, 8, 0 );
				if ( $this->data[9] != $this->checkSum( $this->data, $weights ) ) {
					$this->errors['inn'] = 'Некорректное значение ИНН в поле %fieldname%';
					$this->valid         = false;
				}
				break;
			case 12:
				$weights1 = array( 7, 2, 4, 10, 3, 5, 9, 4, 6, 8, 0 );
				$weights2 = array( 3, 7, 2, 4, 10, 3, 5, 9, 4, 6, 8, 0 );
				if ( $this->data[10] != $this->checkSum( $this->data, $weights1 ) ||
				     $this->data[11] != $this->checkSum( $this->data, $weights2 )
				) {
					$this->errors['inn'] = 'Некорректное значение ИНН в поле %fieldname%';
					$this->valid         = false;
				}
				break;
			default:
				$this->errors['inn'] = 'Некорректное число символов в поле %fieldname%';
				$this->valid         = false;
				break;
		}

		return $this->valid;
	}

	private function _validateKor() {
		if ( empty( $this->data ) ) {
			return $this->valid;
		}
		if ( strlen( $this->data ) > 0 ) {
			$weights = array( 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1 );
			$data    = '0' . substr( $this->params['bik'], 4, 2 ) . $this->data;
			if ( 0 != $this->checkSum( $data, $weights, 10 ) ) {
				$this->errors['kor'] = 'Некорректный номер кор. счета в поле %fieldname% (или некорректный БИК)';
				$this->valid         = false;
			}
		}

		return $this->valid;
	}

	private function _validateBankAccount() {
		if ( empty( $this->data ) ) {
			return $this->valid;
		}
		if ( strlen( $this->data ) > 0 ) {
			$weights = array( 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1 );
			$data    = substr( $this->params['bik'], - 3 ) . $this->data;
			if ( isset( $this->params['user_account'] ) && $this->params['user_account'] ) {
				$t = substr( $this->data, 0, 3 );
				if ( $t != '405' && $t != '407' && $t != '408' ) {
					$this->errors['account'] = 'Некорректный счет в поле %fieldname%';
					$this->valid             = false;

					return false;
				}
			}
			if ( 0 != $this->checkSum( $data, $weights, 10 ) ) {
				$this->errors['account'] = 'Некорректный номер счета в поле %fieldname% (или некорректный БИК)';
				$this->valid             = false;
			}
		}

		return $this->valid;
	}

	private function checkSum( $string, $weights, $mod1 = 11, $mod2 = 10 ) {
		$sum = 0;
		for ( $i = 0; $i < strlen( $string ); $i ++ ) {
			if ( isset( $weights[ $i ] ) ) {
				$sum += intval( $string[ $i ] ) * $weights[ $i ];
			}
		}
		$sum %= $mod1;
		$sum %= $mod2;

		return $sum;
	}

	private function _validateNullEmpty() {
		if ( empty( $this->data ) && ! is_null( $this->data ) ) {
			$this->errors['nullempty'] = 'Значение должно быть или установлено или отключено';
			$this->valid               = false;
		}

		return $this->valid;
	}
} 