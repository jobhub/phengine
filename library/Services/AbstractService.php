<?php
namespace Library\Services;

abstract class AbstractService implements \Phalcon\DI\InjectionAwareInterface, \Phalcon\Events\EventsAwareInterface {
	use \Library\Tools\Traits\DIaware,
		  \Library\Tools\Traits\EventsAware;

	/**
   * Config object
   * @var \Phalcon\Config
   */
  protected $_config;

	/**
	 * Constructor
	 *
	 * @param \Phalcon\DiInterface $di
	 * @param \Phalcon\Events\ManagerInterface $eventManager
	 */
	public function __construct(\Phalcon\DiInterface $di, \Phalcon\Events\ManagerInterface $eventsManager, \Phalcon\Config $config)
	{
		$this->setDi($di);
		$this->setEventsManager($eventsManager);
        $this->_config = $config;
	}

	/**
	 * Service init method
	 */
	abstract public function init();
}