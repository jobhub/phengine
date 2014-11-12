<?php
namespace Engine;

use Phalcon\Mvc\Micro\MiddlewareInterface;

class CacheMiddleware implements MiddlewareInterface {

	/**
	 * @param \Engine\Application $application
	 *
	 * @return bool
	 */
	public function call( $application ) {
		if ( ! $application->isDebug() ) {
			$cache  = $application['cacheOutput'];
			$router = $application['router'];

			$key = preg_replace( '/^[a-zA-Z0-9]/', '', $router->getRewriteUri() );

			// Проверяем, закэширован ли запрос
			if ( $cache->exists( $key ) ) {
				echo $cache->get( $key );

				return false;
			}
		}

		return true;
	}

}