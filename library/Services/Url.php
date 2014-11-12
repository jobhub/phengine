<?php
namespace Library\Services;

use Phalcon\Mvc\Url as UrlResolver;

class Url extends AbstractService {


	public function init() {
		$di = $this->getDi();

		$di['url'] = function () {
		    $url = new UrlResolver();
		    $url->setBaseUri($this->_config->application->baseUri);

		    return $url;
		};

	}
}