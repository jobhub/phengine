<?php
namespace Engine\MongoDb;

class Cursor implements \Iterator {
  protected $_cursor;
  protected $_in_pre_query;
  protected $_queryParams;

  public function __construct(\MongoCursor $cursor) {
    $this->_cursor = $cursor;
    $this->_in_pre_query = true;
    $this->_queryParams = array();
  }

  public function current() {
    return $this->_cursor->current();
  }

  public function count() {
    $data = $this->_cursor->count();

    return $data;
  }

  public function __call($fn, $args) {
    return call_user_func_array(array($this->_cursor, $fn), $args);
  }

  public function next() {
    $this->_cursor->next();
  }

  public function key() {
    return $this->_cursor->key();
  }

  public function getNext() {
    $this->next();
    return $this->current();
  }

  public function rewind() {
    $this->_cursor->rewind();
  }

  public function valid() {
    return $this->_cursor->valid();
  }

  public function sort($fields) {
    $this->_queryParams['sort'] = $fields;
    return $this->_cursor->sort($fields);
  }

  public function limit($num) {
    $this->_queryParams['limit'] = $num;
    return $this->_cursor->limit($num);
  }

  public function skip($num) {
    $this->_queryParams['skip'] = $num;
    return $this->_cursor->skip($num);
  }
}
