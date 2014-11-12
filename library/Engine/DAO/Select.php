<?php
namespace Engine\DAO;

class Select {

  public $select=null;
  public $sort=null;
  public $limit=null;
  public $start=null;
  public $bind=array();
  public $count=false;
  public $fields=false;
  public $aggregate=false;
  public $all = false;

  public function __construct(array $params) {
    foreach ($params as $key=>$value) {
      $this->$key = $value;
    }

  }

  public function convertToAdvanced(\Engine\Db\Select $select) {
    if ($this->select instanceof \Engine\Db\Select) {
      throw new \Engine\Exception('Внутренняя ошибка: запрос уже приведен к расширенной форме');
    }
    $i = 1;

    foreach ($this->select as $field=>$value) {
      $vindex = 'v'.$i;

      if (is_bool($value)) {
        // фикс булевых биндов из-за зендового бага
        $value = $value?1:0;
      } elseif (is_null($value)) {
        // для NULL синтаксис иной, биндов не надо
        $select->where(\Library\Tools\String::quoteIdentifier($field)." IS NULL");
        continue;
      }
      $select->where(\Library\Tools\String::quoteIdentifier($field)." = :v{$i}");

      $this->bind[$vindex] = $value;
      $i++;
    }

    $this->select = $select;
  }
}
