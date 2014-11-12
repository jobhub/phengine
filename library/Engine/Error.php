<?php
/**
 * @namespace
 */
namespace Engine;

use \Phalcon\DI\FactoryDefault as Di,
		\Phalcon\Exception as PhException;

/**
 * Class Exception
 *
 * @category   Engine
 * @package    Error
 */
class Error {
	/**
	 * Log normal error
	 *
	 * @param $type
	 * @param $message
	 * @param $file
	 * @param $line
	 */
	public static function normal( $type, $message, $file, $line ) {
		// Log it
		self::logError(
			$type,
			$message,
			$type,
			$file,
			$line
		);

		// Display it under regular circumstances
	}

	/**
	 * Trigger error_get_last
	 */
	public static function shutdown() {
		$error = error_get_last();
		if ( ! $error ) {
			return;
		}

		// Log it
		self::logError(
			$error['type'],
			$error['message'],
			0,
			$error['file'],
			$error['line']
		);

		// Display it under regular circumstances
	}

	/**
	 * Log exception
	 *
	 * @param \Exception $exception
	 */
	public static function exception( $exception ) {
		// Log the error
		self::logError(
			'Exception',
			$exception->getMessage(),
			$exception->getCode(),
			$exception->getFile(),
			$exception->getLine(),
			$exception->getTraceAsString()
		);
		// Display it
	}

	/**
	 * Write error to log
	 *
	 * @param $type
	 * @param $message
	 * @param $file
	 * @param $line
	 * @param string $trace
	 *
	 * @throws \Phalcon\Exception
	 */
	protected static function logError( $type, $message, $code=0, $file, $line, $trace = '' ) {
		$di         = Di::getDefault();
		$template   = "[%s] %s (File: %s Line: [%s])";
		$logMessage = sprintf( $template, $type, $message, $file, $line );

		if ( $di->has( 'profiler' ) ) {
			$profiler = $di->get( 'profiler' );
			if ( $profiler ) {
				$profiler->addError( $logMessage, $trace );
			}
		}

		if ( $trace ) {
			$logMessage .= $trace . PHP_EOL;
		} else {
			$logMessage .= PHP_EOL;
		}

		if ( ! $di->has( 'logger' ) ) {
			throw new PhException( $message, $code );
		}

		$logger = $di->get( 'logger' );
		if ( !$logger ) {
			throw new PhException( $message, $code );
		}

		$logger->error( $logMessage );
		throw new PhException( $message, $code );

	}
}