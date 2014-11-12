<?php
/**
 * @namespace
 */
namespace Library\Services;

/**
 * Class Annotations
 *
 * @category   Engine
 * @package    Application
 * @subpackage Service
 */
class Dispatcher extends AbstractService
{
    /**
     * Initializes Annotations system
     */
    public function init()
    {
        $di = $this->getDi();

        $di->set('dispatcher', function() use ($di) {

            $eventsManager = $this->getEventsManager();



            $dispatcher = new \Phalcon\Mvc\Dispatcher();
            $dispatcher->setEventsManager($eventsManager);

            return $dispatcher;
          });
    }
} 