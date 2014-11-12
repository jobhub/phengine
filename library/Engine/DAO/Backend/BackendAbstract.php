<?php
namespace Engine\DAO\Backend;

use Phalcon\Events\EventsAwareInterface, Phalcon\DI\InjectionAwareInterface,
	Library\Tools\String;

abstract class BackendAbstract implements EventsAwareInterface, InjectionAwareInterface  {
	use \Library\Tools\Traits\DIaware,
		  \Library\Tools\Traits\EventsAware;

  protected static $_cache = array();
  protected $_table_name;
  protected $_supported_caps = array();
  protected $_is_primary = false;
	protected $_type = null;

  const CAP_AGGREGATE = 1; // бекенд способен аггегировать одни объекты внутри других
  const CAP_PARTIAL_UPDATE = 2; // бекенд способен обновлять только часть записей
  const CAP_SEARCH = 3; // бекенд способен делать выборки по значениям полей
  const CAP_SEARCH_ADVANCED = 4; // бекенд способен делать выборки по Zend_Db_Select

  public function __construct($config, \Phalcon\DiInterface $di=null, \Phalcon\Events\ManagerInterface $eventsManager=null) {
    if (!isset($config['table'])) {
      throw new \Engine\Exception('Database backend: table not specified');
    }
    $this->_table_name = $config['table'];
    if(isset($config['primary']) && $config['primary']) {
      $this->_is_primary = true;
    }
  }

  public function isPrimary() {
    return $this->_is_primary;
  }

	public function getType() {
		return $this->_type;
	}

  public static function factory($options, $di, $eventsManager) {
    $backend = isset($options['type'])?ucfirst($options['type']):'Db';

    $class = '\Engine\DAO\Backend'."\\$backend";
    if (!class_exists($class)) {
      throw new \Engine\Exception("Bad backend $backend");
    }
    return new $class($options, $di, $eventsManager);
  }

  abstract public function fetch($id);

  abstract public function save($data);

  public function update($id, $data) {
    // необходимо переопределить функцию в случае поддержки CAP_PARTIAL_UPDATE
    return false;
  }

  /**
   * Обратное приведение типов при заполнении экземпляра
   * @param $value
   * @param $type
   * @return bool|float|int|mixed|string
   * @throws \Engine\Exception
   */
  public function convertRawToType($value, $type) {
    if (null === $value) {
      return null;
    }
    if (empty($type)) {
      return $value;
    }
    switch ($type)
    {
      case 'serialize':
        $value = unserialize($value);
        break;
      case 'money':
        $value=\Library\Tools\Number::filterPrice($value);
        if (empty($value))
          $value = 0;
        break;
      case 'bool':
        $value = !!intval($value);
        break;
      case 'isodate':
        $value = \Library\Tools\DateTime::toIsoDate($value);
        break;
      /*case 'address':
        $value=new Model_Address($value);
        break;*/
      case 'binary':
      case 'bindata':
      case 'id':
      case 'string':
        $value = "$value";
        break;
      case 'int':
      case 'int64':
      case 'bigint':
        $value = intval("$value");
        break;
      case 'array':
        $value = \Library\Tools\Arrays::toArray($value);
        break;
      case 'json':
        $value = json_decode($value, true);
        break;
      default:
        throw new \Engine\Exception("Внутренняя ошибка: неподдерживаемый тип $type");
    }
    return $value;
  }

  /**
   * Приведение типов перед сохранением в БД
   * @param $value
   * @param $type
   * @return bool|float|int|mixed|string
   * @throws \Engine\Exception
   */
  public function convertTypeToRaw($value, $type) {
    if (null === $value) {
      return null;
    }
    switch ($type)
    {
      case 'serialize':
        $value=serialize($value);
        break;
      case 'money':
        $value = \Library\Tools\Number::filterPrice($value);
        if (empty($value))
          $value=0;
        break;
      case 'bool':
        $value=!!$value;
        break;
      case 'isodate':
        $value = \Library\Tools\DateTime::toIsoDate($value);
        break;
      case 'binary':
      case 'bindata':
      case 'id':
      case 'string':
        $value = "$value";
        break;
      case 'array':
        $value = \Library\Tools\Arrays::toArray($value);
        break;
      case 'json':
        $value = json_encode($value);
        break;
      default:
        throw new \Engine\Exception("Внутренняя ошибка: неподдерживаемый тип $type");
    }
    return $value;
  }

  /**
   * Получить селектор бекенда
   * @param array $fields перечень полей для селекта (null если все)
   * @return \Phalcon\Mvc\Model\Query
   */
  protected function select($fields=null) {
    // необходимо переопределить функцию в случае поддержки CAP_SEARCH_ADVANCED
    return null;
  }

  /**
   * Поиск данных по критерию
   * @param \Engine\DAO\Select|array $criteria Критерий поиска, объект или конфигурация для конструктора
   * @return array
   */
  public function findAll($criteria) {
    if (!is_object($criteria)) {
      $criteria = new \Engine\DAO\Select($criteria);
    }
    if (is_array($criteria->select)
        && !$this->getCapability(self::CAP_SEARCH)
        && $this->getCapability(self::CAP_SEARCH_ADVANCED)
       )
    {
      $criteria->convertToAdvanced($this->select($criteria->fields));
    }
    if (is_array($criteria->select) && $this->getCapability(self::CAP_SEARCH)) {
      if ($criteria->aggregate)
        return $this->_findAdvanced($criteria);
      else
        return $this->_findSimple($criteria);
    } elseif ($criteria->select instanceof \Engine\Db\Select && $this->getCapability(self::CAP_SEARCH_ADVANCED)) {
      return $this->_findAdvanced($criteria);
    } elseif (empty($criteria->select)) {
      return $this->_findSimple($criteria);
    }
    throw new \Engine\Exception("Объект не поддерживает такие возможности поиска");
  }

  abstract public function remove($id);

  abstract public function removeAll();

  public function getProperties() {
    return false;
  }

  public function getCapability($cap) {
    return in_array($cap, $this->_supported_caps);
  }

	abstract protected function _findSimple($criteria);

	protected  function _findAdvanced($criteria) {
		return $this->_findSimple($criteria);
	}

	public function quoteInto($text, $value, $type = null, $count = null)
  {
      if ($count === null) {
          return str_replace('?', String::quote($value, $type), $text);
      } else {
          while ($count > 0) {
              if (strpos($text, '?') !== false) {
                  $text = substr_replace($text, String::quote($value, $type), strpos($text, '?'), 1);
              }
              --$count;
          }
          return $text;
      }
  }

	/**
	 * Получает данные и их количество из пагинатора. Оптимизирует получение количества:
	 * не дергает базу, если она вернула меньше результатов чем просили.
	 * Использование:
	 *   list($rows, $count) = getPagerData($select, $params['start'], $params['limit']);
	 *
	 *
	 * @param \Engine\Db\Select $pager пагинатор или селект
	 * @param int $start
	 * @param int $limit
	 * @param bool|int $truncate автоматически ограничивать значение $limit.
	 *   * false — не ограничивать
	 *   * true — ограничивать значением из конфига
	 *   * число — ограничивать числом
	 * @return array($data, $count)
	 */
	public function getPagerData($select, $start=0, $limit=25, $truncate = true) {

		$pager = new \Engine\Paginator(array(
		    "builder" => $select,
		    "limit"=> $limit,
		    "offset" => $start
		));

	  $limit = intval($limit);
	  if ($truncate) {
	    $max_limit = is_int($truncate)?$truncate:intval(\Engine\Config::getConfigValue(\Phalcon\DI::getDefault(), 'application->linesPerPage', 20));
	    if (empty($limit) || $limit>$max_limit) {
	      $limit = $max_limit;
	    }
	  }
	  $count = false;
	  //logVar($select->__toString());
	  $data = $pager->getPaginate();
	  //logVar($select->__toString());
	  if (false===$count) {
	    $count = count($data);
	    if ($count<$limit && (0==$start||$count>0)) {
	      $count += $start;
	    } else {
	      //logVar($pager->getCountSelect()->__toString(), 'count select');
	      $count = $pager->count();
	    }
	  }

	  return array($data, $count);
	}

	public function transaction_start() {

	}

	public function transaction_commit() {

	}

	public function transaction_rollback() {

	}

	public function has_transaction_pending() {
		return false;
	}
}
