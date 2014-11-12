<?php
namespace Engine\DAO\Backend;
use Phalcon\Mvc\Model;

class Mongo extends BackendAbstract {

  /**
   * @var Mongo
   */
  protected $_handle;
  protected $_primary;

	protected $_type = 'mongo';

  /**
   *
   * @var \MongoCollection
   */
  protected $_collection;

  public function __construct($config, \Phalcon\DiInterface $di, \Phalcon\Events\ManagerInterface $eventsManager) {
    parent::__construct($config, $di, $eventsManager);

    $this->_supported_caps[] = self::CAP_AGGREGATE;
    $this->_supported_caps[] = self::CAP_SEARCH;

    $this->_primary = $config['primary'];
    $this->_handle = isset($config['adapter'])?$config['adapter']:\Engine\MongoDb::getInstance($di);
  }

  /**
   * @return \MongoCollection
   */
  protected function _getCollection() {
    if (!$this->_collection) {
      if (!isset(self::$_cache["mongo_{$this->_table_name}"])) {
        self::$_cache["mongo_{$this->_table_name}"] = new \MongoCollection($this->_handle, $this->_table_name);
      }
      $this->_collection = self::$_cache["mongo_{$this->_table_name}"];
    }
    return $this->_collection;
  }

  protected function _whereId($id) {
    if ($this->_primary) {
      if (!($id instanceof \MongoId)) {
        $id = new \MongoId($id);
      }
      return array('_id'=>$id);
    } else {
      return array('_id'=>intval($id));
    }
  }

  public function fetch($id) {
    $record = $this->_getCollection()->findOne($this->_whereId($id));
    if (!$record) {
      return false;
    }
    $record['id'] = "{$record['_id']}";
    unset($record['_id']);
    return $record;
  }

  public function fetchBy($field, $value) {
    $record = $this->_getCollection()->findOne(array($field=>$value));
    if (!$record) {
      return false;
    }
    $record['id'] = "{$record['_id']}";
    unset($record['_id']);
    return array($record);
  }

  public function convertRawToType($value, $type) {
    if (null === $value) {
      return null;
    }

    switch ($type) {
      case 'id':
        return "$value";
      case 'binary':
      case 'bindata':
        if ($value instanceof \MongoBinData) {
          return $value->bin;
        } else {
          return "$value";
        }
      default:
        return parent::convertRawToType($value, $type);
    }
  }

  public function convertTypeToRaw($value, $type) {
    if (null === $value) {
      return null;
    }

    switch ($type) {
      case 'id':
        $value = "$value";
        if (strlen($value<10) && is_numeric($value)) {
          return intval($value);
        }
        if (''===$value) {
          return null;
        }
        return new \MongoId($value);
      case 'int':
        return new \MongoInt32($value);
      case 'int64':
      case 'bigint':
        return new \MongoInt64($value);
      case 'binary':
      case 'bindata':
        if ($value instanceof \MongoBinData) {
          return $value;
        } else {
          return new \MongoBinData($value, 2);
        }
      case 'isodate':
        return new \MongoDate(\Library\Tools\DateTime::toTimestamp($value));
      default:
        return parent::convertTypeToRaw($value, $type);
    }
  }

  public function save($record) {
    if (isset($record['id'])) {
      $id = $record['id'];
      $record['_id'] = $this->_primary?(new \MongoId($id)):intval($id);
      unset($record['id']);
      $record = $this->_getCollection()->update($this->_whereId($id), $record, array('upsert'=>true));
    } else {
      unset($record['_id']);
      unset($record['id']);
      $result = $this->_getCollection()->insert($record, array('safe'=>true));
      if (isset($record['_id'])) {
        $id = $record['_id'];
      } elseif ($result && isset($result['upserted'])) {
        $id = $result['upserted'];
      } else {
        $id = false;
      }
      //logVar($result, 'insert result');
    }
    //logVar($id, 'inserted id');
    return $this->convertRawToType($id, 'id');
  }

  public function remove($id) {
    return $this->_getCollection()->remove($this->_whereId($id));
  }

  protected function _findSimple($select) {
    $collection = $this->_getCollection();
    $fields = array();
    if ($select->fields && !in_array('*',$select->fields)) {
      foreach ($select->fields as $f) {
        $fields[$f] = true;
      }
    }
    $result = $collection->find($select->select, $fields);
    if ($select->sort) {
      $sort = array();
      foreach ($select->sort as $s) {
        //$sort[$s['sort']] = ('asc'==strtolower($s)||'1'==$s)?1:-1;                // Выдает варнинг на массив вместо строки на strtolower
        $sort[$s['property']] = ('asc'==strtolower($s['direction'])||'1'==$s['direction'])?1:-1;  // TODO: потестить после правки
      }
      $result->sort($sort);
    }
    if ($select->start) {
      $result->skip($select->start);
    }
    if ($select->count) {
      $count = $result->count();
    }
    if ($select->limit) {
      $result->limit($select->limit);
    }
    if ($select->count) {
      return array(array_values(iterator_to_array($result)), $count);
    }
    return array_values(iterator_to_array($result));
  }

  protected function _findAdvanced( $select) {
    $collection = $this->_getCollection();
    $fields = array();
    if ($select->fields && !in_array('*',$select->fields)) {
      foreach ($select->fields as $f) {
        $fields[$f] = 1;
      }
    }

    $ops = array();

    if(!empty($fields)) {
      $ops[]['$project'] = $fields;
    }
    // Так как порядок операторов важен - копипаста (ведь массив могли собрать произвольно)
    if(isset($select->select['$match'])) {
      $ops[]['$match'] = $select->select['$match'];
    }

    if(isset($select->select['$unwind'])) {
      $ops[]['$unwind'] = $select->select['$unwind'];
    }

    if(isset($select->select['$group'])) {
      foreach($select->select['$group'] as $row) {
        $ops[]['$group'] = $row;
      }
    }

    if ($select->start) {
      $ops[]['$skip'] = (int) $select->start;
    }

    if ($select->limit && !$select->all) {
      $ops[]['$limit'] = (int) $select->limit;
    }

    if ($select->sort) {
      $sort = array();
      foreach ($select->sort as $s) {
        //$sort[$s['sort']] = ('asc'==strtolower($s)||'1'==$s)?1:-1;                // Выдает варнинг на массив вместо строки на strtolower
        $sort[$s['sort']] = ('asc'==strtolower($s['dir'])||'1'==$s['dir'])?1:-1;  // TODO: потестить после правки
      }
      $ops[]['$sort'] = $sort;
    }

    $result = $collection->aggregate($ops);

    if (!$result['ok']) {
      return false;
    }

    $result = $result['result'];
    if ($select->count) {
      $count = count($result);
      return array($result, $count);
    }
    return $result;
  }

  public function removeAll()
  {
    return $this->_getCollection()->drop();
  }
}
