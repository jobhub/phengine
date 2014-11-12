<?php
namespace Engine;

class DbMigration extends \CDbMigration {

  const SET = 'SET';
  const DROP = 'DROP';

  /**
   * @var array
   * Массив ресурсов для добавления аклей, каждый ресурс вида
   *  * array ('type'=> "api" / "gui",
   *  * * для api 'controller' => 'controllername', 'action' => 'actionname', 'log'=>'FALSE' / 'TRUE'
   *  * * для gui 'url' => 'урл',
   *  'descr' =>'this is a cool resource',
   *  'roles' => array('allow'=>array(1,2,3), 'deny'=>array(4,5,6))
   *  * * группу deny задавать необязательно
   *  );
   */
  protected $_resources = array();

  /**
   * @var array Массив для добавления пунктов меню.
   * Каждый пукнт меню описывается структурой вида:
   * array(
   *   //обязательные параметры:
   *   'url'    => 'URL пункта меню',
   *   'weight' => 'Вес пункта меню',
   *   'icon'   => 'URL иконки',
   *   'path'   => 'Путь в меню (например: Настройки/Администрирование/Роли и права доступа)',
   *
   *   //опциональные параметры:
   *   'currentPath' => 'Текущий путь в меню (если нужно изменить пункт)',
   * )
   */
  protected $_menues = array();

  /**
   * @var null|MongoDb
   */
  protected $mongoDb = null;

  protected function _checkTemplate($code) {

    $criteria = new \CDbCriteria(array(
      'select'=>'id',
      'condition'=>"code='{$code}'"
    ));
    $tpl_row = $this->dbConnection->getCommandBuilder()->createFindCommand("vocab_doc_templates", $criteria)->queryRow();

    if(!$tpl_row) {
      return false;
    }

    return $tpl_row['id'];
  }

  /**
   * Добавление нового шаблона
   *
   * @param string $code
   * @param string $name
   * @param string $content
   * @param int $format
   */
  protected function addTemplate($code, $name, $content, $format = 1) {

    $template_id = $this->_checkTemplate($code);

    $data = array(
      'name' => $name,
      'content' => $content,
      'code' => $code,
      'date_last_saved' => 'now',
    );
    if (null!==$format) {
      $data['format'] = $format;
    }
    if(!empty($template_id)) {
      $this->updateTemplate($code, $data);
    } else {
      $this->insert('vocab_doc_templates', $data);
    }
  }

  /**
   * Удаление шаблона по коду
   *
   * @param string $code
   */
  public function deleteTemplate($code) {
    $this->delete('vocab_doc_templates', 'code=' . $this->dbConnection->quoteValue($code));
  }

  public function updateTemplate($code, array $data) {
    if (
      empty($data['code']) && isSet($data['name']) && isSet($data['content']) && isSet($data['format'])
    ) {
      throw new Exception('Нужно указать хотя бы одно поле для обновления: code, name, content или format');
    };
    $this->update('vocab_doc_templates', $data, 'code=' . $this->dbConnection->quoteValue($code));
  }

  protected function _getResourceId($resource=array()) {
    if(empty($resource)) {
      throw new \CException('Нет данных о ресурсе');
    }
    $type = strtolower($resource['type']);
    if($type=='api') {
      $controller = $resource['controller'];
      $action = $resource['action'];
      $module = 'default';
      if(isset($resource['module'])) {
        $module = $resource['module'];
      }
      $criteria = new \CDbCriteria(array(
        'select'=>'id',
        'condition'=>"controller='{$controller}' AND action='{$action}' AND module='{$module}'"
      ));
    } else {
      $url = str_replace('#','', $resource['url']);
      $criteria = new \CDbCriteria(array(
        'select'=>'id',
        'condition'=>"url='{$url}'"
      ));
    }

    $resource_row = $this->dbConnection->getCommandBuilder()->createFindCommand("{$type}_resources", $criteria)->queryRow();
    //throw new Exception($api_resource_id['id']);
    return $resource_row['id'];
  }

  public function createTable($table, $columns, $comment = '', $options = null)
 	{
    parent::createTable($table, $columns, $options = null);
    if (!empty($comment)) {
      $this->addTableComment($table, $comment);
    }
 	}

  public function addTableComment($table, $comment) {
    echo "    > add comment '$comment' to table $table ...";
    $time = microtime(true);
    $this->getDbConnection()->createCommand("COMMENT ON TABLE $table IS '$comment'")->execute();
    echo " done (time: " . sprintf('%.3f', microtime(true) - $time) . "s)\n";
  }

  public function addColumn($table, $column, $type, $comment = '') {
    parent::addColumn($table, $column, $type);
    if (!empty($comment)) {
      $this->addColumnComment($table, $column, $comment);
    }
 	}

  public function addColumnComment($table, $column, $comment) {
    echo "    > add comment '$comment' in table $table to $column ...";
    $time = microtime(true);
    $this->getDbConnection()->createCommand("COMMENT ON COLUMN $table.$column IS '$comment'")->execute();
    echo " done (time: " . sprintf('%.3f', microtime(true) - $time) . "s)\n";
  }

  public function alterNotNull($table, $column, $action = self::SET) {
    echo "    > $action not null in table $table to $column ...";
    $time = microtime(true);
    $this->getDbConnection()->createCommand("ALTER TABLE $table ALTER COLUMN $column $action NOT NULL")->execute();
    echo " done (time: " . sprintf('%.3f', microtime(true) - $time) . "s)\n";
  }

  public function alterDefault($table, $column, $action = self::SET, $default = null) {
    echo "    > $action default " . ($default !== null ? "$default " : '') . "in table $table to $column ...";
    $time = microtime(true);
    $this->getDbConnection()
      ->createCommand("ALTER TABLE $table ALTER COLUMN $column $action DEFAULT " . ($default !== null ? $default : ''))->execute();
    echo " done (time: " . sprintf('%.3f', microtime(true) - $time) . "s)\n";
  }


  protected function checkRights ($role_id, $resource_id, $type='api') {
    $criteria = new \CDbCriteria(array(
      'select'=>'mode',
      'condition'=>"{$type}_resource_id={$resource_id} AND acl_role_id={$role_id}"
    ));
    $resource_row = $this->dbConnection->getCommandBuilder()->createFindCommand("{$type}_access_rights", $criteria)->queryRow();
    if($resource_row) {
      return true;
    }
    return false;
  }

  private function getResources($name=null) {
    if (is_array($name)) {
      return $name;
    }
    if(is_string($name) && property_exists($this, $name)) {
      return $this->$name;
    }

    if ($name === null) {
      if (empty($this->_resources)) {
        throw new \CException('Нет данных о ресурсах');
      }
      return $this->_resources;
    }
    throw new \CException('Ресурсы переданы не верно');
  }

  /**
   * Добавить ресурсы
   * @param array $resources массив ресурсов, если не задано будет использовано свойство _resources для получения ресурсов
   * @throws \Exception
   */
  protected function addResources($resources = null) {
    foreach($this->getResources($resources) as $r) {
      $type = strtolower($r['type']);
      $resource_id = $this->_getResourceId($r);
      if(empty($resource_id)) {
        if($type=='api') {
          $this->insert('api_resources', array(
            'controller'=>$r['controller'],
            'action'=>$r['action'],
            'module'=>(isset($r['module'])) ? $r['module']:'default',
            'descr'=>(isset($r['descr'])) ? $r['descr'] : null,
            'log'=>(isset($r['log'])) ? $r['log'] : 'FALSE'
          ));
        } else {
          $this->insert('gui_resources', array(
            'url'=>$r['url'],
            'descr'=>(isset($r['descr'])) ? $r['descr'] : null
          ));
        }
        $resource_id = $this->_getResourceId($r);
        if(empty($resource_id)) {
          throw new \CException('Не достали ид ресурса');
        }
      }
      $this->addAccessRight($resource_id, $type, $r);
    }
  }

  /**
   * @param $resource_id
   * @param $type
   * @param $resource
   */
  protected function addAccessRight($resource_id, $type, $resource)
  {
    foreach ($resource['roles'] as $mode => $role_ids) {
      foreach ($role_ids as $role_id) {
        if (!is_numeric($role_id)) {
          $role_id = $this->_lookupRole($role_id);
        }
        if (!$this->checkRights($role_id, $resource_id, $type)) {
          $this->insert("{$type}_access_rights",
            array(
              "{$type}_resource_id" => $resource_id,
              'acl_role_id' => $role_id,
              'mode' => $mode == 'allow' ? 'TRUE' : 'FALSE'
            )
          );
        }
      }
    }
  }


  /**
   * Удаление ресурсов
   * @param array $resources массив ресурсов, если не задано будет использовано свойство _resources для получения ресурсов
   * @throws Exception
   */
  protected function deleteResources($resources = null) {
    foreach($this->getResources($resources) as $r) {
      $resource_id = $this->_getResourceId($r);
      $type = strtolower($r['type']);

      if(empty($resource_id)) {
        return;
        //throw new Exception('Не достали ид ресурса');
      }
      $this->delete("{$type}_access_rights",
        "{$type}_resource_id={$resource_id}"
      );

      $this->delete("{$type}_resources",
        "id={$resource_id}"
      );
    }
  }

  /**
   * Удаление прав у ресурса
   * @param array $resources массив ресурсов, если не задано будет использовано свойство _resources для получения ресурсов
   * @throws Exception
   */
  protected function deleteAccessRights($resources = null) {
    foreach($this->getResources($resources) as $r) {
      $resource_id = $this->_getResourceId($r);
      $type = strtolower($r['type']);

      if(empty($resource_id)) {
        //throw new Exception('Не достали ид ресурса');
        return;
      }
      foreach($r['roles'] as $role_ids) {
        foreach($role_ids as $role_id) {
          if (!is_numeric($role_id)) {
            $role_id = $this->_lookupRole($role_id);
          }
          $this->delete("{$type}_access_rights",
            "{$type}_resource_id={$resource_id}
                     AND acl_role_id= {$role_id}"
          );
        }
      }
    }

  }

  protected function addMenues(array $menues = null) {
    if (null === $menues) {
      $menues = $this->_menues;
    }
    foreach ($menues as $menu) {
      $this->addMenu($menu);
    }
  }

  protected function deleteMenues(array $menues = null) {
    if (null === $menues) {
      $menues = $this->_menues;
    }
    foreach ($menues as $menu) {
      $this->deleteMenu($menu);
    }
  }

  protected function addMenu(array $item) {
    $id     = isSet($item['currentPath']) && !empty($item['currentPath']) ? $this->getMenuId($item['currentPath']) : null;
    $values = array(
      'url'      => $item['url'],
      'weight'   => $item['weight'],
      'icon'     => $item['icon'],
      'menupath' => $item['path'],
      'actual'   => true,
    );
    if (empty($id)) {
      $this->insert('menues', $values);
    } else {
      $this->update('menues', $values, 'id = ' . $id);
    }
  }

  protected function deleteMenu(array $item) {
    $id = $this->getMenuId($item['path']);
    if (empty($id)) {
      throw new Exception("Пункт меню \"{$item['path']}\" не найден");
    }

    $this->delete('menues', 'id=' . $id);
  }

  protected function getMenuId($path) {
    $criteria = new \CDbCriteria(array(
      'select'    => 'id',
      'condition' => "menupath='{$path}'"
    ));
    $row = $this->dbConnection->getCommandBuilder()->createFindCommand('menues', $criteria)->queryRow();
    return $row['id'];
  }

  /**
   * Получить id роли по коду
   * @param string $role
   * @throws Exception
   * @return integer id
   */
  protected function _lookupRole($role) {
    $criteria = new \CDbCriteria(array(
      'select'    => 'id',
      'condition' => "code='{$role}'"
    ));
    $row = $this->dbConnection->getCommandBuilder()->createFindCommand('acl_roles', $criteria)->queryRow();
    if (!$row) {
      throw new Exception("Role $role is not found");
    }
    return $row['id'];
  }

  /**
   * @return MongoDB
   *
   * @throws Exception
   */
  protected function mongo() {
    if (null === $this->mongoDb) {
      $config = $this->loadMongoConfig();
      $url = 'mongodb://' . $config['host'];
      if (isSet($config['port'])) {
        $url .= ':' . $config['port'];
      }
      $url .= '/' . $config['dbname'];
      $options = array(
        'connect' => true,
        'db' => $config['dbname'],
      );
      if (isSet($config['params'])) {
        $options = array_merge($config['params'], $options);
      }
      $client = new \MongoClient($url, $options);
      $this->mongoDb = $client->selectDB(trim($options['db']));
    }
    return $this->mongoDb;
  }

  /**
   * @return \MongoCollection
   * @param string $name
   * @throws Exception
   */
  protected function mongoCollection($name) {
    return $this->mongo()->selectCollection($name);
  }

  protected function _setArrayValue(&$array, $key, $value) {
    if (is_array($key) && count($key)>1) {
      $top = array_shift($key);
      if (!isset($array[$top])) {
        $array[$top] = array();
      } elseif (!is_array($array[$top])) {
        $array[$top] = array($array[$top]);
      }
      $this->_setArrayValue($array[$top], $key, $value);
    } else {
      if (is_array($key)) {
        $key = array_shift($key);
      }
      if (preg_match('@\[\]$@', $key)) {
        if (!isset($array[$key])) {
          $array[$key] = array();
        } elseif (!is_array($array[$key])) {
          $array[$key] = array($array[$key]);
        }
        $array[$key][] = $value;
      } else {
        $array[$key] = $value;
      }
    }
  }

  protected function loadMongoConfig() {
    $file   = dirName(dirName(dirName(__DIR__))) . '/application/configs/config.d/100-mongo.ini';
    $data   = parse_ini_file($file);
    $result = array();
    foreach ($data as $name => $value) {
      $name = explode('.', preg_replace('/^resources\.mongo\./', '', $name));
      $this->_setArrayValue($result, $name, $value);
    }
    return $result;
  }

}