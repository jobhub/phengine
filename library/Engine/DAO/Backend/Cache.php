<?php
namespace Engine\DAO\Backend;
use Phalcon\Mvc\Model, Library\Tools\String;

class Cache extends BackendAbstract {

  /**
   * @var \Phalcon\Cache\FrontendInterface
   */
  protected $_handle;
	
	protected $_type = 'cache';

  public function __construct($config, \Phalcon\DiInterface $di, \Phalcon\Events\ManagerInterface $eventsManager) {
    parent::__construct($config, $di, $eventsManager);

    $this->_supported_caps[] = self::CAP_AGGREGATE;

    if ($config['primary']) {
      throw new \Engine\Exception("Кеш-бекенд не может быть первичным");
    }
    if (isset($config['adapter'])) {
      $this->_handle = $config['adapter'];
    } else {
      $this->_handle = $di->get('cacheData');
    }
    if (!$this->_handle) {
      throw new \Engine\Exception("Отсутствует кеш-хранилище");
    }
  }

  public function fetch($id) {
    return $this->_handle->load("{$this->_table_name}_{$id}");
  }

  public function save($data) {

    return $this->_handle->save($data, "{$this->_table_name}_{$data['id']}");
  }

  public function remove($id) {
    return $this->_handle->remove("{$this->_table_name}_{$id}");
  }

  public function convertRawToType($value, $type) {
    switch ($type) {
      case 'binary':
      case 'bindata':
        return base64_decode($value);
      default:
        return parent::convertRawToType($value, $type);
    }
  }

  public function convertTypeToRaw($value, $type) {
    switch ($type) {
      case 'binary':
      case 'bindata':
        return base64_encode($value);
      default:
        return parent::convertTypeToRaw($value, $type);
    }
  }

  public function removeAll()
  {
    // TODO: Implement removeAll() method.
  }

	protected function _findSimple($criteria) {
		throw new \Engine\Exception("Поиск по кэш-хранилищу не поддерживается");
	}
}
