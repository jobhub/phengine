<?php
namespace Engine;

use Phalcon\Mvc\Micro\MiddlewareInterface,
		Phalcon\Mvc\View,
		Phalcon\Http\Response;

class ResponseMiddleware implements MiddlewareInterface {

	public function call( $app ) {
		if($app->request->getMethod()!='OPTIONS') {
			/**
			 * @var $app \Engine\Application
			 */
			$app->view->disableLevel( array(
				View::LEVEL_ACTION_VIEW     => true,
				View::LEVEL_LAYOUT          => true,
				View::LEVEL_MAIN_LAYOUT     => true,
				View::LEVEL_AFTER_TEMPLATE  => true,
				View::LEVEL_BEFORE_TEMPLATE => true
			) );

			$app->response->setContentType( 'application/json', 'UTF-8' );
			$data = $app->view->getParamsToView();

			return $app->manageResponse( $data );
		}

	}

}