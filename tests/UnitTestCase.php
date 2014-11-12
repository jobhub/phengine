<?php
use Phalcon\Config;
use Phalcon\DI\FactoryDefault;
use Phalcon\DI;
use Phalcon\DiInterface;

abstract class UnitTestCase extends \PHPUnit_Framework_TestCase {


	/**
	 * @var \Phalcon\Config
	 */
	protected $_config;

	/**
	 * @var bool
	 */
	protected $_loaded = false;

	/**
	 * @var \Phalcon\DiInterface
	 */
	protected $_di;

	private $_default_services = array(
		'url',
		'translate'
	);

	protected $_services = array();

	public function setUp() {
		if ( empty( $this->_config ) ) {
			include_once( BASE_PATH . '/config/config.php' );
			$this->_config = getConfig();
		}
		$di = DI::getDefault();

		if ( is_null( $di ) ) {
			$di = $this->initDI();
		}

		$di = $this->setupLoader( $di );
		$di = $this->registerServices( $di );

		$this->_di     = $di;
		$this->_loaded = true;
	}

	protected function initDI() {
		DI::reset();

		// Instantiate a new DI container
		$di = new FactoryDefault();

		return $di;
	}

	protected function setupLoader( \Phalcon\DI $di = null ) {
		$loader = new \Phalcon\Loader();

		$loader->registerDirs( array(
			ROOT_PATH
		) );


		$namespaces = array(
			'Microapp'             => BASE_PATH . '/apps',
			'Microapp\Controllers' => realpath( __DIR__ . '/controllers/' ),
			'Microapp\Models'      => realpath( __DIR__ . '/models/' ),
			'Library'             => realpath( __DIR__ . '/../library/' ),
			'Engine'              => realpath( __DIR__ . '/../library/Engine' )
		);


		$loader->registerNamespaces( $namespaces );
		$loader->register();

		if ( $di instanceof \Phalcon\DI ) {
			$di->setShared( 'loader', $loader );
		}

		return $di;
	}

	protected function registerServices( \Phalcon\DI $di ) {
		$core_ns = $this->_config->application->ns;

		$services = array_merge( $this->_default_services, $this->_services );

		$eventsManager = new \Phalcon\Events\Manager();

		foreach ( $services as $name ) {
			$serviceName = $this->_config->application->services_ns . "\\" . ucfirst( $name );

			$service = new $serviceName( $di, $eventsManager, $this->_config );
			if ( ! ( $service instanceof $core_ns . '\Library\Services\AbstractService' ) ) {
				throw new \Engine\Exception( "Service '{$serviceName}' is not an instance of AbstractService" );
			}
			/**
			 * @var $service \Library\Services\AbstractService
			 */
			$service->init();
		}

		$di->set( 'eventsManager', $eventsManager );

		return $di;
	}

	/**
	 * Проверка на то, что тест правильно настроен
	 * @throws \PHPUnit_Framework_IncompleteTestError;
	 */
	public function __destruct() {
		if ( ! $this->_loaded ) {
			throw new \PHPUnit_Framework_IncompleteTestError( 'Please run parent::setUp().' );
		}
	}

	/**
	 * @return \Phalcon\DiInterface
	 */
	protected function getDI() {
		return $this->_di;
	}

	protected function tearDown() {
		$di = $this->getDI();

		if ( $di && $di instanceof \Phalcon\DI ) {
			$di::reset();
		}
		parent::tearDown();
	}
}