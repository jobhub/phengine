<?php
namespace Engine\MongoDb;

class Collection extends \MongoCollection {


  public function find(array $query = array(), array $fields = array()) {

    $ret = parent::find($query, $fields);
    return new Cursor($ret);
    //self::$_profiler->queryEnd($q);
    //return $ret;
  }

  /*public function aggregate(array $pipeline) {
    $args = func_get_args();
    $q = self::$_profiler->queryStart(array('aggregate '.$this->getName(), $args), Zend_Db_Profiler::SELECT);
    $ret = call_user_func_array('parent::aggregate', $args);
    self::$_profiler->queryEnd($q);
    return $ret;
  }

  public function findOne(array $query = array(), array $fields = array()) {
    $q = self::$_profiler->queryStart(array('findOne '.$this->getName(), $query), Zend_Db_Profiler::SELECT);
    if ($q) {
      $qp = self::$_profiler->getQueryProfile($q);
      $qp->bindParam('fields', $fields);
    }
    $ret = parent::findOne($query, $fields);
    self::$_profiler->queryEnd($q);
    return $ret;
  }

  public function count(array $query = array(), $limit = 0, $skip = 0) {
    $q = self::$_profiler->queryStart(array('count '.$this->getName(), $query, $limit, $skip), Zend_Db_Profiler::SELECT);
    $ret = parent::count($query, $limit, $skip);
    self::$_profiler->queryEnd($q);
    return $ret;
  }

  public function distinct($key, array $query=array()) {
    $q = self::$_profiler->queryStart(array('distinct '.$this->getName(), $key, $query), Zend_Db_Profiler::SELECT);
    $ret = parent::distinct($key, $query);
    self::$_profiler->queryEnd($q);
    return $ret;
  }

  public function findAndModify(array $query, array $update=array(), array $fields=array(), array $options=array()) {
    $q = self::$_profiler->queryStart(array('findAndModify '.$this->getName(), $query, $update, $fields, $options), Zend_Db_Profiler::UPDATE);
    $ret = parent::findAndModify($query, $update, $fields, $options);
    self::$_profiler->queryEnd($q);
    return $ret;
  }

  public function group($keys, array $initial=array(), MongoCode $reduce=null, array $options = array()) {
    $q = self::$_profiler->queryStart(array('group '.$this->getName(), $keys, $initial, $reduce->__toString(), $options), Zend_Db_Profiler::SELECT);
    $ret = parent::group($keys, $initial, $reduce, $options);
    self::$_profiler->queryEnd($q);
    return $ret;
  }

  protected function _sanitizeProfilerData($data) {
    if (is_array($data)) {
      foreach ($data as $k=>$v) {
        $data[$k] = $this->_sanitizeProfilerData($v);
      }
    } elseif (is_object($data) && $data instanceof MongoBinData) {
      $data = "[mongo binary data ".strlen($data->bin)." bytes]";
    } elseif (is_object($data)) {
      $data = "$data";
    }
    return $data;
  }

  public function insert($a, array $options = array()) {
    $q = self::$_profiler->queryStart(array('insert '.$this->getName(), $this->_sanitizeProfilerData($a), $options), Zend_Db_Profiler::INSERT);
    $ret = parent::insert($a, $options);
    self::$_profiler->queryEnd($q);
    return $ret;
  }

  public function remove(array $criteria = array(), array $options = array()) {
    $q = self::$_profiler->queryStart(array('remove '.$this->getName(), $criteria, $options), Zend_Db_Profiler::DELETE);
    $ret = parent::remove($criteria, $options);
    self::$_profiler->queryEnd($q);
    return $ret;
  }

  public function save($a, array $options = array()) {
    $q = self::$_profiler->queryStart(array('save '.$this->getName(), $a, $options), Zend_Db_Profiler::UPDATE);
    $ret = parent::save($a, $options);
    self::$_profiler->queryEnd($q);
    return $ret;
  }

  public function update(array $criteria, array $new_object, array $options = array()) {
    $q = self::$_profiler->queryStart(array('update '.$this->getName(), $criteria, $new_object, $options), Zend_Db_Profiler::UPDATE);
    $ret = parent::update($criteria, $new_object, $options);
    self::$_profiler->queryEnd($q);
    return $ret;
  }*/
}
