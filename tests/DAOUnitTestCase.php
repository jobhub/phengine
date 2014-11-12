<?php
use Phalcon\Config;
use Phalcon\DI\FactoryDefault;
use Phalcon\DI;

abstract class DAOUnitTestCase extends \UnitTestCase {
	/**
	 * @var array $_services
	 */
	protected $_services = array(
		'database'
	);

	/**
	 * @var $_tx \Phalcon\Mvc\Model\Transaction\Manager
	 */
	protected $tx_manager;

	/**
	 * @var $_tx \Phalcon\Mvc\Model\Transaction
	 */
	protected $_tx;

	public function setUp( \Phalcon\DiInterface $di = null, \Phalcon\Config $config = null ) {
		parent::setUp( $di, $config );

		$this->tx_manager = new \Phalcon\Mvc\Model\Transaction\Manager();

		$this->_tx = $this->tx_manager->get();

    $this->_tx->begin();
	}

  protected function tearDown() {
    if($this->tx_manager->getRollbackPendent()) {
      $this->_tx->rollback();
    }

    parent::tearDown();
  }

}