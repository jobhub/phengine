<?php
namespace Engine;

use Phalcon\DiInterface;
use Phalcon\Events\EventsAwareInterface, Phalcon\DI\InjectionAwareInterface;
use Phalcon\Mvc\Model as PhModel;

abstract class DAO implements InjectionAwareInterface, EventsAwareInterface {

	//-------- ATTRIBUTES -----------//
  /**
   * Массив доступных объекту бэкенд-хранилищ
   * @var array $_backends
   */
  protected  $_backends;

  /**
   * @var bool
   * Флаг включения логгирования изменений сущности в системный журнал
   */
  protected $_logable = false;

  /**
   * @var array
   * Настройки автоматического логгирования
   * поддерживаемые ключи
   * * * entity_type_name - человекочитаемое наименование сущности в родительном падеже (i.e. пользователя для модели Model_User)
   * * * save_event_name - наименование события, которое нужно файрить на сейве, по умолчанию afterSave, которое обрабатывается плагином Engine_Plugins_EntityBasicActions
   * * * save_event_message - шаблон комментария, сохраняемого при логгировании изменений сущности, по умолчанию '{status}: {operation_type} {entity_type} {entity_id}'
   * * * delete_event_name - наименование события, которое нужно файрить при удалении сущности, по умолчанию afterRemove, которое обрабатывается плагином Engine_Plugins_EntityBasicActions
   * * * delete_event_message шаблон комментария, сохраняемого при логгировании удаления сущности, по умолчанию '{status}: {operation_type} {entity_type} {entity_id}'
   */
  protected $_log_settings = array();

  /**
   * Конфигурация бекендов (будет передана в Engine_DAO_Backend_Abstract для инициализации)
   * Порядок бекендов важен: более ранним отображается первичный бекенд, остальные вторичны (кеши)
   * @var array $_backends_config
   **/
  protected $_backends_config = array(
    array(
      'type' => 'db',
      'table' => null,
	    'model_name' => null
    )
  );

  /**
   * Текущие данных экземпляра
   * @var array
   */
  protected $_data = array();

  /**
   * Текущие данные зависимостей
   * @var array
   */
  protected $_depends_data = array(); // Данные зависимостей сюда складываются объекты зависимостей

  /**
   * Параметры-свойства экземпляра (равны перечню полей хранилища),
   * каждый из элементов массива параметров, поименованный названием поля хранилища, представляет собой массив,
   * поддерживающий следующие ключи
   *  - pseudo - Человекопонятное название поля,
   *  - validators - Список валидаторов, с помощью которых следует проверить значение поля перед сохранением
   *  - params - Список параметров, необходимый валидаторам для проверок (i.e. max и min) @see Engine_DataValidation
   *  - type - тип данных в поле, используется для конвертации туда/обратно при извлечении данных и сохранении их
   *  - default - значение поля по умолчанию
   * @var array
   */
  protected $_parameters = array();

  /**
   * Список "грязных" (=затронутых изменениями) полей экземпляра
   * @var array
   */
  protected $_dirty = array();

  /**
   * Объект родителя зависимости
   * @var DAO
   */
  protected $_parent = null;

  /**
   * Массив свойств объекта
   * @var array
   */
  protected $_properties = array();

  /**
   * Конфиг зависимостей объекта в формате массива с элементами вида
   *   'пропертя' => array(параметры)
   * Параметры:
   *   class — класс объекта зависимости
   *   loader — функция-загрузчик объекта, принимающая id хозяина (можно не указывать,
   *            тогда будет использоваться типовой loadByOwner)
   *   opts — дополнительные опции к лоадеру
   *   single — true если зависимый объект может быть только один
   *   aggregate — аггрегируемая зависимость (будет включаться в бекенды с
   *               поддержкой аггрегации в составе объекта)
   * @var array
   */
  protected $_dependencies = array();

  /**
   * Мяпа методов для подготовки параметров перед сохранением
   *
   * @var array
   */
  protected  $_buildersMap = array();

	/**
	 * @var \Phalcon\Translate\Adapter\NativeArray
	 */
	protected $t;



  //-------- END ATTRIBUTES -----------//

	use \Library\Tools\Traits\DIaware,
		  \Library\Tools\Traits\EventsAware;
  //-------- PUBLIC OPERATIONS -----------//

  public function __construct() {
		if(null == $this->_di) {
			$this->setDI(\Phalcon\DI::getDefault());
		}

	  if(null == $this->_eventsManager) {
		  $this->setEventsManager($this->getDI()->get('eventsManager'));
	  }
	  $this->t = $this->getDI()->get('translate');
	  $this->_setupBackend();
    $this->_initProperties();
  }

	/**
	 * Загружаем данные в объект по ID
	 *
	 * @param $id
	 *
	 * @return DAO
	 */
	public static function get($id) {
		$class_name = get_called_class();

		$m = new $class_name();
		/**
		 * @var $m DAO
		 */
		$m->_load($id);

		return $m;
	}

	/**
	 * Загружаем данные в объект по значению уникального поля
	 *
	 * @param $field string
	 *
	 * @param $value mixed
	 *
	 * @return DAO
	 */
	public static function getBy($field, $value) {
		$class_name = get_called_class();

		$m = new $class_name();
		/**
		 * @var $m DAO
		 */
		$status = $m->loadBy($field, $value);

		return $status ? $m : false;

	}

	public function _($msg, $params = array()) {
		return $this->t->_($msg, $params);
	}

  /**
   * Загрузка данных в экземпляр по значению одного из полей
   *
   * @param string $field
   * @param string $value
   * @return bool|DAO
   */
  public function loadBy($field, $value) {
    $params=array($field=>$value);

    // Обходим бекенды начиная с самого последнего (т.е. быстрого)
    for ($i=count($this->_backends)-1; $i>=0; $i--) {
      $b = $this->_backends[$i];
      if(method_exists($b, 'fetchBy')) {
        $params = $this->_prepareRawData($params, $b);
        $value = $params[$field];
        $data = $b->fetchBy($field, $value);
        if ($data) {
          $status = $this->_loadData($data[0], $b);
          if ($status && $i>0 && $i<count($this->_backends)-1) {
            $this->_updateCache();
          }
          return $this;
        }
      }
    }
    return false;
  }

  /**
   * Загрузка данных в экземпляр по группе условий
   * @param array $params
   * @return DAO|bool
   */
  public function loadConditional($params) {
    $b = $this->getPrimaryBackend();
    $criteria = array('select'=>$this->_prepareRawData($params, $b), 'limit'=>1);
    $result = $this->findByCriteria($criteria);
    if(empty($result)) {
      return false;
    }
    $this->_loadData($result[0], $b);
    if(isset($result[0]['_id'])) {
	    /**
	     * @var $mongoId \MongoId
	     */
	    $mongoId = $result[0]['_id'];
      $this->_setValue('id', $mongoId->__toString());
    }
    return $this;
  }

  public function getParameters() {
    return $this->_parameters;
  }

  /**
   * Поиск по критериям
   * @param array $params
   *  select - ассоциативный массив критериев или готовый Zend_Db_Select
   *  bind - бинды к запросу в случае Zend_Db_Select
   *  aggregate - использовать ли для выборки MongoDB Aggregation Framework, по умолчанию FALSE
   *  sort - критерии сортировки, массив вида array(array('sort'=>'Имя поля для сортировки', 'dir'=>'Направление сортировки'))
   *  limit - количество записей (по умолчанию берется из 700-interface.ini interface->linePerPage, если это не то, что нужно, следует передавать ключ all=>true)
   *  start - отступ, по умолчанию 0
   *  count - считать общее число записей или нет (по умолчанию нет), если установлено true,
   *          то будет возвращен массив вида array(данные, число_записей),
   *          иначе просто данные
   *  fields - список полей (имена через запятую), если ключ отсутствует - будут выбраны все поля
   * @return array
   */
  public function findByCriteria($params=array()) {
    // нельзя использовать другие бекенды, т.к. они кеши, и в них может не быть данных
    /* @var $backend \Engine\DAO\Backend\BackendAbstract */
    $backend = $this->_backends[0];
    $default_params = array(
      'aggregate' => false,
      'limit'=>20,
      'start'=>0,
      'count'=>false,
      'fields'=>array('*')
    );
    $params = array_merge($default_params, $params);

    if(isset($params['all']) && $params['all']) {
      unset($params['limit']);
    }
    if (isset($params['select']) && is_array($params['select'])) {
      //$params['select'] = $this->_prepareRawData($params['select'], $backend);
      //выполняем приведение типов только для условий вида field => value.
      foreach ($params['select'] as $property => $criteria) {
        if (is_array($criteria)) {
          continue;
        }
        $raw = $this->_prepareRawData(array($property => $criteria), $backend);
        $params['select'][$property] = $raw[$property];
      }
    }

    return $backend->findAll($params);
  }

  /**
   * Получение данных экземпляра в виде массива
   * @return array
   */
  public function toArray($recursive=false) {
    return $this->_getDataAsArray($recursive);
  }

  /**
   * Метод update критичен для автоматического обновления неаггрегированных зависимостей - это шаблон,
   * его бы лучше всего объявить абстрактным и заставить реализовать в каждой модели, но это нужно рефакторить   *
   * @param array $data
   * @param array $white_list
   */

  public function update($data=null, $white_list=null) {
    $this->_setData($data);
  }

  /**
   * Заполнение экземпляра данными
   * @param array $data
   * @return DAO
   */
  public function _setData($data) {
    foreach ($data as $property=>$value) {
      if (isset($this->_parameters[$property])) {
        $this->_setValue($property, $value);
      } elseif (isset($this->_dependencies[$property])) {
        $depconfig = $this->_dependencies[$property];
        $this->_setValue($property, $this->_constructDependency($depconfig, $value));
      }
    }
    return $this;
  }

  /**
   * Проверяет, был ли уже сохранен экземпляр
   * @return bool
   */
  public function isNew() {
    return null == $this->_getValue('id');
  }

  /**
   * Получает настройки логгирования
   * @param string $k ключ настроек. По умолчанию возвращает полный список настроек
   * @return string|array
   */
  public function getLogSettings($k=null) {
    if(!empty($k)) {
      if(isset($this->_log_settings[$k])) {
        return $this->_log_settings[$k];
      }
      return null;
    }
    return $this->_log_settings;
  }

	public function validatePropertyValue($value, array $pseudo, array $validators, $validation_params=array()) {
		$validation = new Validation();
		if(count($validators)) {
			foreach ( $validators as $class_name ) {

				$options = isset( $validation_params[ $class_name ] ) ? $validation_params[ $class_name ] : array();

				$v = $this->_getValidator( $class_name, $options );
				$validation->add( 'attribute', $v );
			}
		}

		$validation_data = array('attribute'=>$value);

		$validation->validate( $validation_data );

		$messages = $validation->getMessages($pseudo);

		return $messages;
	}

  /**
   * Валидация данных в свойствах объекта
   * Евенты:
   *   validate: вызывается перед валидацией,
   *     параметры: нет
   *     результат: игнорируется
   *     результат фейла: ошибка валидации
   * @return DAO
   */
  public function performValidation() {

	  $perform_validation = false;

	  $validation = new Validation();
		$pseudos = array();
	  foreach ($this->_parameters as $property=>$parameters) {
      $pseudos[$property] = $parameters['pseudo'];
		  if (isset($parameters['validators'])) {
	      $perform_validation = true;
	      $validation_params = (isset($parameters['params'])) ? $parameters['params'] : array();
	      foreach($parameters['validators'] as $class_name) {

		      $options = isset($validation_params[$class_name]) ? $validation_params[$class_name] : array();

		      $v = $this->_getValidator($class_name, $options);
		      $validation->add($property, $v);
	      }
      }
    }
	  if($perform_validation) {
		  $data = $this->_getDataAsArray(false);
		  $validation->validate( $data );

		  $messages = $validation->getMessages($pseudos);

		  if(count($messages)) {
			  $text = join("<br/>\n", $messages);
        throw new \Engine\Exception("При сохранении информации имели место следующие ошибки: <br/>\n$text", 400);
		  }
	  }
	  return $this;
  }

	protected function _getValidator($class_name, $options) {

		if ( isset( $options['table'] ) && isset( $options['field'] ) ) {
			$db = $this->getDi()->get('db');
			$fld = $options['field'];
			/**
			 * @var $db \Phalcon\Db\Adapter
			 */
			$records = $db->fetchAll("SELECT ".\Library\Tools\String::quoteIdentifier($fld)." FROM {$options['table']} ", \Phalcon\Db::FETCH_ASSOC);

			$options = array('domain'=>array()	);

			foreach($records as $r) {
				$options['domain'][] = $r[$fld];
			}
			$options['domain'][]='';
			$options['domain'][]=null;
		}

		if(!is_array($options)) {
			$options = array();
		}

		$my_cl = 'Engine\Validator\\'.$class_name;
		if(class_exists($my_cl)) {

			return new $my_cl($options);
		}

		$ph_cl = '\Phalcon\Validation\Validator\\'.$class_name;

		if(class_exists($ph_cl)) {
			return new $ph_cl($options);
		}
		throw new \Engine\Exception('Неопознанный тип валидатора');
	}

  /**
   * Сохранить объект в базе.
   * Евенты:
   *   beforeSave: вызывается перед сохранением, параметры:
   *     0 ($data) — данные для сохранения
   *     результат: обновленные данные для сохранения (если null/false, то остаются как были)
   *     результат фейла: тихий отказ от сохранения.
   *   afterSave: вызывается после сохранения, параметры
   *     0 ($data) — сохраненные данные
   *     результат: игнорируется
   *     результат фейла: игнорируется
   * @return DAO
   * @throws \Exception
   */
  public function save($comment=null, $cancel_log=false) {
    if(empty($comment)) {
      $cancel_log = true;
    }
    if ($this->_parent) {
      $this->_parent->save($comment, $cancel_log);
    } else {
      $this->_saveData(true, $comment, $cancel_log);
    }
    return $this;
  }

  /**
   * Сохранить черновик объекта в базе.
   * Евенты:
   *   beforeSave: вызывается перед сохранением, параметры:
   *     0 ($data) — данные для сохранения
   *     результат: обновленные данные для сохранения (если null/false, то остаются как были)
   *     результат фейла: тихий отказ от сохранения.
   *   afterSave: вызывается после сохранения, параметры
   *     0 ($data) — сохраненные данные
   *     результат: игнорируется
   *     результат фейла: игнорируется
   * @return DAO
   * @throws \Exception
   */
  public function saveDraft() {
    if ($this->_parent) {
      $this->_parent->saveDraft();
    } else {
      $this->_saveData(false,null,true);
    }
    return $this;
  }

  /**
   * Удаление экземпляра из всех хранилищ
   * @param int | string $id - идентификатор сущности
   * @param string $comment - сообщение для логирования
   */
  public function remove($id=null, $comment=null) {
    if(empty($id)) {
      $id = $this->_getValue('id');
    }
    foreach ($this->_backends as $backend) {
	    /**
	     * @var $backend \Engine\DAO\Backend\BackendAbstract
	     */
	    $backend->remove($id);
    }
  }

  /**
   * Полная очистка всех хранилищ, убивает все данные вообще
   * (применять с осторожностью!)
   */
  public function removeAll() {
	  $di = $this->getDi();
	  $app = $di->get('app');
    if($app->isDebug()) {
      foreach ($this->_backends as $backend) {
        /**
		     * @var $backend \Engine\DAO\Backend\BackendAbstract
		     */
	      $backend->removeAll();
      }
    }
  }

  /**
   * Публичный метод получения геттера/сеттера из имени поля
   * @param $property
   * @param null $method_type
   * @return string
   */
  public function getMethodByProperty($property, $method_type=null)
  {
    $method_name = $this->_getMethodByProperty($property);

    if(!empty($method_type)) {
      $method_name = $method_type.$method_name;
    }
    return $method_name;
  }

  /**
   * Генерилка сеттеров-геттеров
   * @param string $fname - имя метода
   * @param array $arguments - параметры
   * @return null|void
   * @throws \Exception
   */
  public function __call($fname, $arguments=null) {
    if (preg_match('@^(set|get)(.+)$@i', $fname, $matches))
    {
      $property = $this->_getPropertyByMethod($matches[2]);
      if ('set'==strtolower($matches[1]))
      {
        if (count($arguments)<1)
        {
          throw new \Exception("Set what? (in {$fname})");
        }
        $value = $arguments[0];
        $this->_setValue($property, $value);
	      return true;
      } else
      {
        return $this->_getValue($property);
      }
    }
    throw new \Exception('Invalid method call: '.$fname);
  }

  /**
   * Магические статические методы (работают только в PHP 5.3+!):
   *   fetchFieldByOtherField($v) — возвращает значение в ячейке Field у строчки, у которой в OtherField стоит значение $v
   *     Имена ячеек конвертируются в нижний регистр, а перед каждым блоком заглавных букв, ставится «_». Первая
   *     буква имени всегда считается как в нижнем регистре (поэтому нельзя получить имя ячейки, начинающееся с «_»)
   *     Функция всегда вызывает запрос в базу, поэтому выбирать следует только по тем колонкам, по которым есть индексы,
   *     также следует учитывать что будут проигнорированны не сохраненные в базу данные инстанциированных объектов
   *     Пример: $registry_number = Model_Procedure::fetchRegistryNumberById($some_id)
   * @param string $fname имя вызываемого метода
   * @param array $arguments аргументы
   * @return mixed
   */
  public static function __callStatic($fname, $arguments=null) {
    if (preg_match('@^fetch([a-zA-Z0-9]+)By([a-zA-Z0-9]+)$@i', $fname, $matches)) {
      $field_fetch = \Library\Tools\String::strtolower(preg_replace('@([A-Z]+)@', '_$1', lcfirst($matches[1])));
      $field_by    = \Library\Tools\String::strtolower(preg_replace('@([A-Z]+)@', '_$1', lcfirst($matches[2])));
      $className = get_called_class();
      /* @var $model DAO */
      $model = new $className(null);
      $row = $model->findByCriteria(array(
          'limit'=>1,
          'fields'=>$field_fetch,
          'select'=>array(
            $field_by => $arguments[0],
          ))
      );
      return $row?$row[0][$field_fetch]:null;
    }
    throw new \Exception('Invalid method call: '.$fname);
  }

  public function getPrimaryTable() {
    return $this->_backends_config[0]['table'];
  }

  //----------- PUBLIC OPERATIONS FOR DEPENDENCIES ------------//

  /**
   * Проверяет наличие зависимости в конфигураторе зависимостей
   * @param $name
   * @return null
   */
  public function hasDependency($name) {
    if(empty($this->_dependencies)) return false;

    return array_key_exists($name, $this->_dependencies);
  }

  /**
   * Получаем значение конфигурационного параметра зависимости или полный конфиг зависимости по ее имени
   * @param string $dep_name
   * @param string $cfg_field
   * @return string|array
   */
  public function getDepConfig($dep_name, $cfg_field=null) {
    if (empty($this->_dependencies) || !isset($this->_dependencies[$dep_name])) {
      return null;
    }

    $cfg = $this->_dependencies[$dep_name];
    if(empty($cfg_field)) {
      return $cfg;
    }
    return $cfg[$cfg_field];
  }

  /**
   * Загрузка зависимости по родителю
   * @param DAO $o
   * @param int|string $parent_id
   * @param string $field
   * @return array of DAO
   */
  public static function  loadByParent($o, $parent_id, $field, $extra_conditions = array()) {
    $params = array($field=>$parent_id);

    if(!empty($extra_conditions)) {
      $params = array_merge($params,$extra_conditions);
    }

    $data = $o->findByCriteria(array('select'=>$params, 'limit'=>false));
    $objects = array();
    foreach ($data as $d) {
      if ($o->_loadData($d, $o->getPrimaryBackend())) {
        $objects[] = $o;
      }
    }
    return $objects;
  }

  /**
   * Загрузка данных в конкретную зависимость
   * @param $dep_name
   * @param $dep_config
   * @return mixed
   */
  public function loadDependency($dep_name, $dep_config = null) {
    if(empty($dep_config)) {
      $dep_config = $this->_dependencies[$dep_name];
    }

    if ($dep_config['aggregate'] && array_key_exists($dep_name, $this->_depends_data)) {
      return $this->_depends_data[$dep_name];
    }

    $class = $dep_config['class'];
    $obj = new $class();
    $obj->_parent = $this;

    if(isset($dep_config['args'])) {
      $args = $dep_config['args'];
      array_unshift($args, $this->getId());
    } else {
      $args = array($this->getId());
    }

    $loaded_data = call_user_func_array(array($class, $dep_config['loader']), $args);
    if (!empty($loaded_data)) {
      $dep_data = array();
      foreach($loaded_data as $row) {
	      /**
	       * @var $row DAO
	       */
	      $row->_loadDependencies();
        $dep_data[] = $row;
      }
      $this->_depends_data[$dep_name] = (isset($dep_config['single']) && $dep_config['single']) ? $dep_data[0] : $dep_data;
    } else {
      $this->_depends_data[$dep_name] = array();
    }
  }

  /**
   * Загрузка данных во все зависимости
   * (граната в руках обезьяны - применять с осторожностью)
   */
  public function _loadDependencies() {
    foreach ($this->_dependencies as $dep=>$config) {
      if($config['aggregate'] && isset($this->_depends_data[$dep])) {
        continue;
      }
      $this->loadDependency($dep, $config);
      if (!isset($this->_depends_data[$dep])) {
        $this->_depends_data[$dep] = (isset($config['single']) && $config['single']) ? null : array();
      }
    }
  }

  /**
   * Обновляет данные неаггрегированных зависимостей
   * @param array $dependencies_data
   * @throws \Exception
   */
  public function updateNonAggregatedDeps($dependencies_data) {
    foreach($dependencies_data as $dep_name=>$dep_data) {
      $cfg = $this->_dependencies[$dep_name];
      if ($cfg['aggregate']) {
        throw new \Exception ('Метод не работает с аггрегированными зависимостями');
      }
      $class_name = $cfg['class'];
      if(isset($cfg['single']) && $cfg['single']) {
        $dep_data = array($dep_data);
      }
      foreach($dep_data as $dep) {
        if(!empty($dep)) {
          $dep_id = (isset($dep['id'])) ? $dep['id'] : null;
	        /**
	         * @var $obj DAO
	         */
          $obj = new $class_name($dep_id);
          $parent_field = $cfg['parent_identity_field'];
          $dep[$parent_field] = $this->_getValue('id');
          $obj->update($dep);
          $obj->save();
          if(count($obj->_dependencies)) {
            $obj_dep_data = $obj->collectNonAggregatedDepsData($dep);
            if(!empty($obj_dep_data)) {
              $obj->updateNonAggregatedDeps($obj_dep_data);
            }
          }
        }
      }
      if(isset($this->_depends_data[$dep_name])) {
//        $this->_depends_data[$dep_name] = isSet($cfg['single']) && true === $cfg['single'] ? null : array();
//        Удаляем данные зависимости, чтобы они загрузились из БД
        unSet($this->_depends_data[$dep_name]);
      }
    }
    $this->_loadDependencies();
  }

  /**
   * Строит модели аггрегированных зависимостей из параметров
   * @param $params
   * @return mixed
   */
  public function buildAggregatedDependencies($params) {
    if (!empty($this->_dependencies)) {
      foreach($this->_dependencies as $dep_name=>$dep_cfg) {
        if (!isset($params[$dep_name])) {
          continue;
        }
        $params[$dep_name] = $this->buildAggregatedDep($dep_name, $params[$dep_name]);
      }
    }
    return $params;
  }

  public function buildAggregatedDep($name, $value) {
    if(empty($this->_dependencies) || !isset($this->_dependencies[$name])
        || is_object($value) || $name=='files' || $name=='images') {
      return $value;
    }

    $dep_cfg = $this->_dependencies[$name];
    $is_single = false;

    if(isset($dep_cfg['single'])) {
      $is_single = $dep_cfg['single'];
    }

    $class_name = $dep_cfg['class'];
    $instance = new $class_name();
	  /**
	   * @var $instance DAO
	   */
    $instance->_setData($value);

    if($is_single) {
      $instance = new $class_name();
      $instance->_setData($value);
      $current = $instance;
    } else {
      $current = array();
      if(is_array($value)) {
        foreach($value as $entry) {
          $instance = new $class_name();
          $instance->_setData($entry);
          $current[] = $instance;
        }
      } else {
        $instance = new $class_name();
        $instance->_setData($value);
        $current[] = $instance;
      }
    }
    return $current;
  }

  //-------------END PUBLIC OPERATIONS FOR DEPENDENCIES----------------//

  public function on($event, $handler) {
    return addListener(get_class($this) . '.' . $event, $handler);
  }

  public function un($event, $handler) {
    return removeListener(get_class($this) . '.' . $event, $handler);
  }

  public function fireEvent($event) {
    $params = func_get_args();
    $params[0] = get_class($this) . '.' . $event;
    return call_user_func_array('fireEvent', $params);
  }

	public function transaction_start() {
		$this->getPrimaryBackend()->transaction_start();
	}

	public function transaction_commit() {
		$this->getPrimaryBackend()->transaction_commit();
	}

	public function transaction_rollback() {
		$this->getPrimaryBackend()->transaction_rollback();
	}

	public function has_transaction_pending() {
		return $this->getPrimaryBackend()->has_transaction_pending();
	}

  //-------------END PUBLIC OPERATIONS ----------------//




  //-------------PROTECTED OPERATIONS----------------//

  /**
   * Возвращает объект главного бэкенда, полезно для последующей загрузки данных в экземпляр
   * @return \Engine\DAO\Backend\BackendAbstract
   */
  protected function getPrimaryBackend() {
    foreach($this->_backends as $b) {
	    /**
	     * @var $b \Engine\DAO\Backend\BackendAbstract
	     */
	    if($b->isPrimary()) {
        return $b;
      }
    }
    return \Engine\DAO\Backend\BackendAbstract::factory($this->_backends_config[0], $this->getDi(), $this->getDi()->get('eventsManager'));
  }

  /**
   * Извлечь объект из базе.
   * Евенты:
   *   loaded: вызывается после загрузки данных из базы, параметры:
   *     0 ($data) — загруженные данные
   *     результат: обновленные данные для загрузки (если null/false, то остаются как были)
   *     результат фейла: фейл загрузки
   * @param integer|string $id
   * @return boolean статус загрузки
   */
  protected function _load($id) {
    // Обходим бекенды начиная с самого последнего (т.е. быстрого)
    for ($i=count($this->_backends)-1; $i>=0; $i--) {
	    /**
	     * @var $b \Engine\DAO\Backend\BackendAbstract
	     */
	    $b = $this->_backends[$i];
	    $data = $b->fetch($id);
      if ($data) {
        $status = $this->_loadData($data, $b);
        if ($status && $i>0 && $i<count($this->_backends)-1) {
          $this->_updateCache();
        }
        return $status;
      }
    }
    return false;
  }

  /**
   * Загружает в объект данные из массива (голые данные бекенда)
   * @param array $data
   * @param \Engine\DAO\Backend\BackendAbstract $backend
   * @return boolean статус загрузки
   */
  protected function _loadData($data, $backend) {
    $data = $this->_prepareFormattedData($data, $backend);

	  /*$this->getEventsManager();
    $result = $this->fireEvent('beforeload', new Engine_Event($data));
    if ($result->isFailed()) {
      return false;
    }
    if ($result->getResult()) {
      $data = $result->getResult();
    }*/

    $this->_setData($data);
    $this->_dirty = array(); // после загрузки мы уже чистые
    return true;
  }

  /**
   * Подготавливает данные к тому, чтобы сохранить их в бекенде
   * (подставляет дефолтные значения, делает приведение типов)
   * @param array $data
   * @param \Engine\DAO\Backend\BackendAbstract $backend
   * @return array
   */
  protected function _prepareRawData($data, $backend) {
    foreach ($this->_parameters as $property=>$parameters) {
      if (isset($parameters['type']) && array_key_exists($property, $data)) {
        if($parameters['type']==='id' && (empty($data[$property]) || !$data[$property])) {
          unset($data[$property]);
          continue;
        }
        $data[$property] = $backend->convertTypeToRaw($data[$property], $parameters['type']);
      }
    }
    foreach ($this->_dependencies as $dep=>$config) {
      if (!isset($data[$dep])) {
        continue;
      }
      $cls = new $config['class'];
	    /**
	     * @var $cls DAO
	     */
      if (isset($config['single']) && $config['single']) {
        $data[$dep] = $cls->_prepareRawData($data[$dep], $backend);
      } else {
        foreach ($data[$dep] as $k=>$v) {
          $data[$dep][$k] = $cls->_prepareRawData($data[$dep][$k], $backend);
        }
      }
    }
    return $data;
  }

  /**
   * Подготавливает данные к тому, чтобы заполнить ими экземпляр
   * (подставляет дефолтные значения, делает обратное приведение типов)
   * @param array $data
   * @param \Engine\DAO\Backend\BackendAbstract $backend
   * @return array
   */
  protected function _prepareFormattedData($data, $backend) {
    foreach ($this->_parameters as $property=>$parameters) {
      if (!isset($data[$property])) {
        $data[$property] = isset($parameters['default'])?$parameters['default']:null;
      }
      if (isset($parameters['type'])) {
        $data[$property] = $backend->convertRawToType($data[$property], $parameters['type']);
      }
    }
    foreach ($this->_dependencies as $dep=>$config) {
      if (!isset($data[$dep]) ||empty($data[$dep])) {
        continue;
      }
      $cls = new $config['class'];
	    /**
	     * @var $cls DAO
	     */
      if (isset($config['single']) && $config['single']) {
        $data[$dep] = $cls->_prepareFormattedData($data[$dep], $backend);
      } else {
        foreach ($data[$dep] as $k=>$v) {
          $data[$dep][$k] = $cls->_prepareFormattedData($data[$dep][$k], $backend);
        }
      }
    }
    return $data;
  }

  /**
   * Конструирует навание метода из названия поля
   * @param string $property
   * @return string
   */
  protected function _getMethodByProperty($property)
  {
    return str_replace(' ', '', ucwords(str_replace('_', ' ', $property)));
  }

  /**
   * Получает название поля из названия метода
   * @param string $method
   * @return string
   */
  protected function _getPropertyByMethod($method)
  {
    $property = preg_replace('@([A-Z]+)@', '_$1', $method);
    return ltrim(strtolower($property), '_');
  }

  /**
   * Установка значения поля экземплара
   * @param string $property имя поля
   * @param string|array|object|boolean $value присваеваемое значение
   * @throws \Exception
   */
  protected function _setValue($property, $value) {
    //logVar($value, "setting $property");
    if (isset($this->_dependencies[$property])) {
      $depconfig = $this->_dependencies[$property];
      if(isset($depconfig['single']) && $depconfig['single']) {
        $this->_depends_data[$property] = $value;
      } else {
        if (empty($value)) { //todo: Нужна проверка хранения массивов. Возможно костыль
          $this->_depends_data[$property] = array();
          return;
        }
        $this->_depends_data[$property] = (is_array($value)&&isset($value[0]))?$value:array($value);
      }

    } else {
      if (!in_array($property, array_keys($this->_parameters), true)) {
        throw new \Exception("Нет такой проперти: $property");
      }
      if (!isset($this->_data[$property]) || $this->_data[$property] !== $value) {
        $this->_dirty[$property] = true;
        $this->_data[$property] = $value;
        if ($this->_parent) {
          $this->_parent->_dirty[get_class($this)] = true;
        }
      }
    }
  }

  /**
   * Получение значения поля экземпляра
   * @param string $property имя поля
   * @return null
   * @throws \Exception
   */
  protected function _getValue($property) {
    //logVar($this->_data, "getting $property");
    if (isset($this->_dependencies[$property])) {
      if (!isset($this->_depends_data[$property])) {
        $depconfig = $this->_dependencies[$property];
        if(!isset($depconfig['class'])) {
          throw new \Exception('Неправильно сконфигурирована зависимость '.$property.' - не определен соответствующий класс');
        }
        $class = $depconfig['class'];
        // Подгрузка аггрегированных зависимостей (которые лежат в свойствах у папы)
        if(array_key_exists('aggregate', $depconfig) && $depconfig['aggregate']) {
          $this->_depends_data[$property] = ($depconfig['single']) ? null: array();
          if(array_key_exists($property, $this->_data)) {
            $dep_value = $this->_data[$property];
            if(isset($depconfig['single']) && $depconfig['single']) {
              $this->_depends_data[$property] = $dep_value;
            } else {
              if(!empty($dep_value)) {
                if(!isset($dep_value[0])) {
                  $dep_value = array($dep_value);
                }
                $this->_depends_data[$property] = $dep_value;
              }
            }
          }
        } else {
          $params = array();
          // Подгрузка неаггрегированных зависимостей
          if (!isset($depconfig['loader'])) {
            $depconfig['loader'] = 'loadByParent';
            $name = $this->_getPropertyByMethod(preg_replace('@Model_@', '', get_class($this)));
            $depconfig['opts'] = "{$name}_id";
            $params[] = new $class();
          }
          $loader = $depconfig['loader'];
          $fn = "$class::$loader";
          $params[] = $this->getId();
          if (isset($depconfig['opts']) && !empty($depconfig['opts']) && is_array($depconfig['opts'])) {
            $params = array_merge($params, $depconfig['opts']);
          }
          $dep_data = call_user_func_array($fn, $params);
          if(isset($depconfig['single'])){
            if($depconfig['single']) {
              $dep_data = $dep_data[0];
            }
          }
          $this->_depends_data[$property] = $dep_data;
        }
      }
      $prop = $this->_depends_data[$property];
      return $prop;
    }
    if (!array_key_exists($property, $this->_data)) {
      if (!array_key_exists($property, $this->_parameters) && !in_array($property, $this->_properties, true)) {
        throw new \Exception('Попытка получения значения отсутствующего поля');
      }
      return isset($this->_parameters[$property]['default'])?$this->_parameters[$property]['default']:null;
    }
    return $this->_data[$property];
  }

  /**
   * Преобразование данных экземпляра в массив
   * @param bool $recursive включить данные зависимостей
   * @return array
   */
  protected function _getDataAsArray($recursive = false) {
    $data = array();
    // Подгружаем только данные модели
    foreach ($this->_properties as $property) {
      $method = 'get'.$this->_getMethodByProperty($property);
      $data[$property] = $this->$method();
    }
    // Подгружаем еще и зависимости
    if ($recursive) {
      //$this->_loadDependencies();
      foreach ($this->_dependencies as $dep=>$config) {

        if(isset($config['single']) && $config['single']) {
          $data[$dep] = null;
        } else {
          $data[$dep] = array();
        }

        $this->loadDependency($dep, $config);

        if (!isset($this->_depends_data[$dep]) || empty($this->_depends_data[$dep])) {
          continue;
        }
        if(isset($config['single']) && $config['single']) {
          if(!empty($this->_depends_data[$dep])) {
            $dependency = $this->_depends_data[$dep];
	          /**
	           * @var $dependency DAO
	           */
            $data[$dep] = (is_array($dependency)) ? $dependency : $dependency->_getDataAsArray($recursive);
          } else {
            $data[$dep] = null;
          }
        } else {
          foreach ($this->_depends_data[$dep] as $dependency) {
            if(!empty($dependency)) {
              $data[$dep][] = (is_array($dependency)) ? $dependency : $dependency->_getDataAsArray($recursive);
              /*foreach($dependency as $depobj) { // ajh
                $data[$dep][] = $depobj->_getDataAsArray($recursive);
              }*/
            }
          }
        }
      }
    }
    return $data;
  }

  /**
   * Актуализация кэша
   */
  protected function _updateCache() {
    if ($this->_parent) {
      $this->_parent->_saveData(true,null,true,true);
    } else {
      $this->_saveData(true, null, true, true);
    }
  }

  protected function buildParams($params) {
    foreach($params as $key => $param) {
      if (isset($this->_buildersMap[$key])) {
        $methodName = $this->_buildersMap[$key]['method'];
        $params = $this->$methodName($params, $key);
      }
      if($this->hasDependency($key)) {
        $params[$key] = $this->buildAggregatedDep($key, $params[$key]);
      }
    }
    return $params;
  }

  //-------------PROTECTED OPERATIONS FOR DEPENDENCIES----------------//

  /**
   * Вытаскиваеn из массива загружаемых в объект данных только данные неаггрегированных зависимостей
   * @param $data
   * @return array
   */
  protected function collectNonAggregatedDepsData($data) {
    $depends_data = array();
    foreach($this->_dependencies as $dep_name=>$dep_cfg) {
      if(array_key_exists($dep_name, $data) && !isset($dep_cfg['aggregate']) || !$dep_cfg['aggregate']) {
        if(array_key_exists('single',$dep_cfg) && $dep_cfg['single']) {
          $depObj = $this->loadDependency($dep_name, $dep_cfg);
          if($depObj) {
            $depends_data[$dep_name] = array_merge($depObj->toArray(), $data[$dep_name]);
          } else {
            $depends_data[$dep_name] = $data[$dep_name];
          }
        } else {
          $depends_data[$dep_name] = $data[$dep_name];
        }
      }
    }
    return $depends_data;
  }

  //-------------END PROTECTED OPERATIONS FOR DEPENDENCIES----------------//

  //-------------END PROTECTED OPERATIONS ----------------//



  //------------- PRIVATE OPERATIONS --------------//

  /**
   * Сохранение данных экземпляра
   * @param bool $ignore_primary - сохранять только в кэш
   * @return DAO
   * @throws \Exception
   */
  private function _saveData($validate = true, $comment=null, $cancel_log=false, $ignore_primary=false) {
    if (empty($this->_dirty)) {
      // ничего не менялось — нет смысла что-либо делать
      if(!$this->checkDirtyChild()){
        return $this;
      }
    }
    if($validate) {
      $this->performValidation();
    }

    $is_new_record=(null===$this->getId());

    $data = $this->_getDataAsArray(false);

    /*$result = $this->fireEvent('beforeSave', new Engine_Event($data));
    if ($result->isFailed()) {
      return $this;
    }
    if ($result->getResult()) {
      $data = $result->getResult();
    }*/
    $primary_ok = false;

    /* @var $backend \Engine\DAO\Backend\BackendAbstract */
    foreach ($this->_backends as $backend) {
      $backend_data = $data;
      try {
        if ($ignore_primary && !$primary_ok) {
          $primary_ok = true;
          $id = $this->getId();
          if (!$id) {
            throw new \Exception("Невозможно закешировать несохраненный объект");
          }
          continue;
        }

        if ($backend->getCapability(\Engine\DAO\Backend\BackendAbstract::CAP_AGGREGATE)) {
          foreach ($this->_dependencies as $dep=>$config) {
            if ($config['aggregate']) {
              $backend_data[$dep] = $this->_getValue($dep);
              if (isset($config['single']) && $config['single']) {
                if(!empty($backend_data[$dep])) {
	                $dependency = $backend_data[$dep];
	                /**
	                 * @var $dependency DAO
	                 */
                  $backend_data[$dep] = $dependency->_getDataAsArray(true);
                }
              } else {
                if(!empty($backend_data[$dep])) {
                  foreach ($backend_data[$dep] as $k=>$v)  {
	                  /**
	                   * @var $v DAO
	                   */
                    if(!empty($v)) {
                      $backend_data[$dep][$k] = $v->_getDataAsArray(true);
                    }
                  }
                }
              }
            }
          }
          $id = $backend->save($this->_prepareRawData($backend_data, $backend));
        } else {
          $row = $this->getPrimaryBackend()->fetch($data['id']);
          if ($data['id'] && $backend->getCapability(\Engine\DAO\Backend\BackendAbstract::CAP_PARTIAL_UPDATE)) {
            $backend_data = array();
            foreach ($this->_dirty as $prop=>$config) {
              if (array_key_exists($prop, $data)) {
                $backend_data[$prop] = $data[$prop];
              }
            }
            $backend_data = $this->_prepareRawData($backend_data, $backend);
            $id = $data['id'];
            if(count($backend_data)) {
              $id = $backend->update($data['id'], $backend_data);
            }
          } else {
            $backend_data = $this->_prepareRawData($backend_data, $backend);
            $id = $backend->save($backend_data);
          }

          if(!$is_new_record && $id && $backend->isPrimary()) {
            $this->_logChanges($backend_data, $row, $id, false);
          }
        }
        if (!$id && !$primary_ok) {
          // не удалась вставка в первичную базу — это фатальная ошибка
          throw new \Exception("Ошибка сохранения объекта в первичной базе");
        } elseif (!$id) {
          // не удалась вставка во вторичную базу (в кеш) — удаляем эту запись из базы
          $backend->remove($data['id']);
          continue;
        }
        if (!$primary_ok) {
          // это была вставка в первичную базу
          $primary_ok = true;
          if (!isset($data['id']) || !$data['id']) {
            $data['id'] = $id;
            $this->setId($id);
          }
        }
      } catch(\Exception $e) {
        //logVar($backend_data, get_called_class().'::data');
        throw ($e);
      }
    }

    if($this->_logable && !$cancel_log) {
      $event_name = (isset($this->_log_settings['save_event_name'])) ? $this->_log_settings['save_event_name'] : 'afterSave';
      fireEvent($event_name, $this, $comment, $is_new_record);
    }

    if (!$ignore_primary) {
      // сохранились => чистенькие
      $this->_dirty = array();
    }
    return $this;
  }

  protected function _logChanges($new_data, $old_data, $id, $comment = false) {
  }

  protected function _initProperties() {
    if (!isset($this->_backends[0])) {
      throw new \Exception("Отсутствуют бекенды DAO");
    }
    $primary_backend = $this->getPrimaryBackend();
	  $properties = $primary_backend->getProperties();

	  if (!$properties) {
      $properties = array_keys($this->_parameters);
    }

	  $this->_properties = $properties;

	  foreach ($this->_dependencies as $dep=>$config) {
      if (isset($config['aggregate']) && $config['aggregate']) {
        $this->_properties[] = $dep; // добавляем в _properties только агрегированные зависимости
        if (isset($config['single']) && $config['single']) {
          $this->_depends_data[$dep] = null;
        } else {
          $this->_depends_data[$dep] = array();
        }
      }
    }
    return $this;
  }

  private function _setupBackend() {
    $this->_backends = array();
    $primary = true;
    $primary_table = false;
    foreach ($this->_backends_config as $cfg) {
      $cfg['primary'] = $primary;
      if (!$primary && !isset($cfg['table'])) {
        $cfg['table'] = $primary_table;
      }
      $this->_backends[] = \Engine\DAO\Backend\BackendAbstract::factory($cfg, $this->getDi(), $this->getEventsManager());
      if ($primary) {
        $primary_table = $cfg['table'];
        $primary = false;
      }
    }
  }

  /**
   * Проверка зависимостей на затронутость изменениями
   * @return bool
   */
  private function checkDirtyChild(){
    if (isset($this->_depends_data)){
      foreach($this->_depends_data as $dep_name=>$depObj){
        if(isset($this->_dependencies[$dep_name]['single']) && $this->_dependencies[$dep_name]['single']) {
          if(count($depObj->_dirty)>0){
            return true; // дети грязные
          }
        } else {
          foreach($depObj as $dep) {
            if(count($dep->_dirty)>0){
              return true; // один из детей грязный, значит всю зависимость надо пересейвливать
            }
          }
        }
      }
    }
    return false;
  }

  //------------- PRIVATE OPERATIONS FOR DEPENDENCIES--------------//
  /**
   * Собственно построитель объекта зависимости
   * @param string $class
   * @param array $depconfig
   * @param array $data
   */
  private function _doConstructDep($class, $depconfig, $data) {
    $obj = new $class();
    $obj->_parent = $this;
    if(!isset($depconfig['aggregate']) || !$depconfig['aggregate']) {
      $id = $this->_getValue('id');
      if(array_key_exists('parent_identity_field', $depconfig) && !empty($id)) {
        $data[$depconfig['parent_identity_field']] = $id;
      }
    }

	  /**
	   * @var $obj DAO
	   */
    return $obj->_setData($data);
  }

  /**
   * Метод, управляющий построением зависимости
   * @param $depconfig
   * @param $data
   * @return array|DAO
   */
  private function _constructDependency($depconfig, $data) {
    $class = $depconfig['class'];
    if(isset($depconfig['single']) && $depconfig['single']) {
      if(is_array($data)) {
        return $this->_doConstructDep($class, $depconfig, $data);
      }
    } else {
      if (isset($data[0]) && is_array($data[0])) {
        $objs = array();
        foreach($data as $r) {
          $objs[] = $this->_doConstructDep($class, $depconfig, $r);
        }
        return $objs;
      }
    }
    return $data;
  }
  //-------------END PRIVATE OPERATIONS FOR DEPENDENCIES--------------//
  //-------------END PRIVATE OPERATIONS----------------//
}