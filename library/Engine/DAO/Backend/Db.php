<?php
namespace Engine\DAO\Backend;
use Phalcon\Mvc\Model, Library\Tools\String;

class Db extends BackendAbstract {

  /**
   * Хендл базы данных
   * @var \Engine\Db\Adapter\Pdo\Postgresql
   */
  protected $_handle;

	protected $_type = 'db';

  /**
   * Таблица
   * @var string $_table_name
   */
  protected $_table_name;

	/**
	 * @var \Engine\Db\Select $_fetch_query
	 */
  protected $_fetch_query;
  protected $_id_property;

  public function __construct($config, \Phalcon\DiInterface $di=null, \Phalcon\Events\ManagerInterface $eventsManager=null) {
    if (!$config['primary']) {
      throw new \Exception("БД-бекенд может быть только первичным");
    }
    parent::__construct($config, $di, $eventsManager);

    $this->_supported_caps[] = self::CAP_PARTIAL_UPDATE;
    $this->_supported_caps[] = self::CAP_SEARCH_ADVANCED;

	  $this->_table = $config['table'];

    $this->_handle = isset($config['adapter'])?$config['adapter']:$di->get('db');
    $this->_id_property = isset($config['id_property'])?String::quoteIdentifier($config['id_property']):'id';
  }

  /**
   * Получить наименование таблицы
   * @return string
   */
  protected function _getTable() {
    /*if (!$this->_table) {
      if (!isset(self::$_cache["table_{$this->_table_name}"])) {
        self::$_cache["table_{$this->_table_name}"] = new Zend_Db_Table(array(
          Zend_Db_Table::NAME => $this->_table_name,
          Zend_Db_Table::ADAPTER => $this->_handle,
        ));
      }
      $this->_table = self::$_cache["table_{$this->_table_name}"];
    }*/

    return $this->_table;
  }

  /**
   * Получить селект выборки объекта по id
   * @return \Engine\Db\Select
   */
  protected function _getFetchQuery() {
    if (!$this->_fetch_query) {
      $fetch_query = "fetch_{$this->_table_name}";
      if (!isset(self::$_cache[$fetch_query])) {
	      $queryBuilder = new \Engine\Db\Select($this->_handle);
        self::$_cache[$fetch_query] = $queryBuilder
          ->from($this->_table_name)
          ->where("{$this->_id_property}=:id")
          ->limit(1);
      }
      $this->_fetch_query = self::$_cache[$fetch_query];
    }
    return $this->_fetch_query;
  }

  public function fetch($id) {
	  $builder = $this->_getFetchQuery();

	  $sql = $builder->__toString();
	  $result = $this->_handle->fetchOne($sql,\Phalcon\Db::FETCH_ASSOC,array('id'=>$id));
	  return $result;
    //return $this->_handle->fetchOne($this->_getFetchQuery(), \Phalcon\Db::FETCH_ASSOC, array('id'=>$id));
  }

  public function fetchBy($field, $value) {
    $select = new \Engine\Db\Select($this->_handle);
        $select->from($this->_table_name)
        ->where(String::quoteIdentifier($field)."=:v");

		$sql = $select->__toString();
    $result = $this->_handle->fetchAll($sql, \Phalcon\Db::FETCH_ASSOC, array('v'=>$value));

	  return $result;
  }

  protected function select($fields=null) {
	  $queryBuilder = new \Engine\Db\Select($this->_handle);
	  if(empty($fields)) {
		  $fields = array('*');
	  }
    $queryBuilder->from($this->_table_name, $fields);
	  /*if($fields) {
		  $queryBuilder->columns($fields);
	  }*/
	  return $queryBuilder;
  }

  protected function _whereId($id) {
    return $this->_handle->quoteInto("{$this->_id_property}=?", $id);
  }

  public function save($data) {
    if (isset($data['id']) && $data['id']) {
      $id = $data['id'];
      unset($data['id']);
      if (!$this->_handle->update($this->_table_name, array_keys($data), array_values($data), $this->_whereId($id))) {
        return false;
      }
	    return $data['id'];
    }
	  try {
		  $this->_handle->insert($this->_table_name, array_values($data), array_keys($data));
		  $id = $this->_handle->lastInsertId($this->_table_name."_id_seq");
	  } catch(\Exception $e) {
		  throw $e;
	  }


		return $id;
  }

  public function remove($id) {
    return $this->_handle->delete($this->_table_name, $this->_whereId($id));
  }

  public function getProperties() {
    $columns = $this->_handle->describeColumns($this->_table_name);

	  $properties = array();

	  foreach($columns as $c) {
		  $properties[] = $c->getName();
	  }
	  return $properties;
  }

  public function update($id, $data) {
    if (!$id) {
      throw new \Engine\Exception("Невозможно частичное обновление не сохраненной записи");
    }
    if (!$this->_handle->update($this->_table_name, array_keys($data), array_values($data), $this->_whereId($id))) {
      return false;
    }
    return $id;
  }

	protected function _findSimple($criteria) {

	}

  protected function _findAdvanced($criteria) {
    $select = $criteria->select;
    if ( !($select instanceof \Engine\Db\Select)) {
      throw new \Engine\Exception("Некорректные параметры выборки");
    }

    $start = intval($criteria->start);
    $limit = intval($criteria->limit);
    if ($criteria->sort) {
      foreach ($criteria->sort as $s) {
        $sortInfo = \Library\Tools\Arrays::createSortLimitFromPost($s);
        $sort  = $sortInfo['order'];
        $select->order($sort);
      }

    }
    if ($criteria->count) {
      if ($criteria->bind) {
        $select->bind($criteria->bind);
      }

      return self::getPagerData($select, $start, $limit,
                          isset($criteria->truncate)?$criteria->truncate:true,
                          isset($criteria->skip_count)?$criteria->skip_count:false);
    }
    if ($start || $limit) {
      $select->limit($limit, $start);
    }

	  $query = $select->__toString();

	  $bind = $criteria->bind;

    return $this->_handle->fetchAll($query,  \Phalcon\Db::FETCH_ASSOC, $bind);
  }

  public function convertTypeToRaw($value, $type) {
    switch ($type) {
      case 'bool':
        return $value?1:0;
      case 'array':
        return serialize(\Library\Tools\Arrays::toArray($value));
      default:
        return parent::convertTypeToRaw($value, $type);
    }
  }

  public function convertRawToType($value, $type) {
    switch ($type) {
      case 'array':
        return \Library\Tools\Arrays::toArray(unserialize("$value"));
      case 'id':
        return intval($value);
      default:
        return parent::convertRawToType($value, $type);
    }
  }

  public function removeAll()
  {
     return $this->_handle->delete($this->_table_name, '1=1');
  }

	public function transaction_start() {
		$this->_handle->begin();
	}

	public function transaction_commit() {
		$this->_handle->commit();
	}

	public function transaction_rollback() {
		$this->_handle->rollback();
	}

	public function has_transaction_pending() {
		$this->_handle->isUnderTransaction();
	}
}
