<?php
/**
 * Created by PhpStorm.
 * User: patris
 * Date: 25.09.14
 * Time: 10:10
 */

namespace Library\Tools;


class Xmls {

	public static function simplexml2array( $xml ) {
		$childs = array();
		if ( is_object( $xml ) && get_class( $xml ) == 'SimpleXMLElement' ) {
			/**
			 * @var $xml \SimpleXMLElement
			 */
			$attributes = $xml->xpath( '@*' );

			$childs = $xml->xpath( '*' );
			foreach ( $attributes as $k => $v ) {
				$k = $v->getName();

				if ( $v ) {
					$a[ $k ] = (string) $v;
				}
			}
		}

		$r = (string) $xml;

		if(count($childs)) {
			$r = array();
			foreach ( $childs as $child ) {
				$key = $child->getName();

				$v = self::simplexml2array( $child );
				if ( isset( $r[ $key ] ) ) {
					if ( ! is_array( $r[ $key ] ) || ! isset( $r[ $key ][0] ) ) {
						$r[ $key ] = array( $r[ $key ] );
					}
					$r[ $key ][] = $v;
				} else {
					$r[ $key ] = $v;
				}
			}
		}
		if ( isset( $a ) ) {
			if ( is_array( $r ) ) {
				$r['@'] = $a;    // Attributes
			}
			if ( isset( $a['type'] ) ) {
				$type = $a['type'];
				$p    = strpos( $type, ':' );
				if ( false !== $p ) {
					$type = substr( $type, $p + 1 );
				}
				switch ( String::strtolower( $type ) ) {
					case 'array':
						$v = array();
						if ( ! is_array( $r ) ) {
							$r    = array( $r );
						}
						foreach ( $r as $key => $val ) {
							if ( '@' == $key ) {
								continue;
							}

							if ( is_array( $val ) && Arrays::isNumericArray( $val ) ) {
								$v = array_merge( $v, $val );
							} else {
								$v[] = $r[ $key ];
							}
						}
						$r = $v;
						break;
					case 'int':
					case 'integer':
						$r = intval( $r );
						break;
					case 'bool':
					case 'boolean':
						$r = ( $r == 'true' );
						break;
					case 'decimal':
					case 'float':
						$r = floatval( $r );
						break;
				}
			}
		}

		return $r;
	}
} 