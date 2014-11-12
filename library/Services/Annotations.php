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
class Annotations extends AbstractService
{
    /**
     * Initializes Annotations system
     */
    public function init()
    {
        $di = $this->getDi();

        $config = $this->_config;
        $di->set('annotations', function () use ($config) {
            if (!$config->application->debug && isset($config->annotations)) {
                $annotationsAdapter = '\Phalcon\Annotations\Adapter\\'.$config->annotations->adapter;
                $adapter = new $annotationsAdapter($config->annotations->toArray());
            } else {
                $adapter = new \Phalcon\Annotations\Adapter\Memory();
            }

            return $adapter;
        }, true);
    }
} 