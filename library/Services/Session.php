<?php
namespace Library\Services;

use Phalcon\Session\Adapter\Database as SessionAdapter;

class Session extends AbstractService {


	public function init() {
		$di = $this->getDi();

		$di['session'] = function () {

			$inj = \Phalcon\DI::getDefault();
			/**
			 * @var $cfg \Phalcon\Config
			 */
			$cfg = $inj->get('config')->toArray();
	    //$mongo = \Engine\MongoDb::getInstance($inj, true, $cfg['mongo']);

	    //Passing a collection to the adapter
	    $session = new SessionAdapter(array(
		    'db' => $inj->get('db'),
		    'table' => 'sessions'
	       // 'collection' => new \MongoCollection($mongo, $cfg['application']['session']['collection_name'])
	    ));

	    $session->start();

	    return $session;
		};

	}
}