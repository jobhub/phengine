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
class Translate extends AbstractService
{
    /**
     * Initializes Annotations system
     */
    public function init()
    {
        $di = $this->getDi();

        $config = $this->_config;

        $localesDir = $this->_config->application->localesDir;

	      $default_lang = $this->_config->application->default_lang;

        if(!$default_lang) {
          $default_lang = 'en';
        }

        require $localesDir."/{$default_lang}.php";
        /**
         * @var $messages array
         */

        $di->set('translate', function() use ($messages) {
          new \Phalcon\Translate\Adapter\NativeArray(array(
           "content" => $messages
          ));
        }) ;
    }
} 