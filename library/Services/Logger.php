<?php
/**
 * @namespace
 */
namespace Library\Services;

/**
 * Class the logger
 *
 * @category   Library
 * @package    Services
 */
class Logger extends AbstractService
{
    /**
     * Initializes the logger
     */
    public function init()
    {
        $di = $this->getDi();

        $config = $this->_config;
        if ($config->application->logger->enabled) {
            $di->set('logger', function () use ($config) {
                $logger = new \Phalcon\Logger\Adapter\File($config->application->logger->path."main.log");
                $formatter = new \Phalcon\Logger\Formatter\Line($config->application->logger->format);
                $logger->setFormatter($formatter);
                return $logger;
            });
        } else {
            $di->set('logger', function () use ($config) {
                $logger = new \Phalcon\Logger\Adapter\Syslog($config->application->logger->project, [
                    'option' => LOG_NDELAY,
                    'facility' => LOG_DAEMON
                ]);
                return $logger;
            });
        }
    }
} 