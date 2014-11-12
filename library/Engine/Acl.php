<?php
/**
 * Created by PhpStorm.
 * User: patris
 * Date: 03.10.14
 * Time: 14:08
 */

namespace Engine;


use Phalcon\DI\InjectionAwareInterface, \Engine\Db\Select, \Library\Tools\String;

class Acl implements InjectionAwareInterface {
  const ROLE_ADMIN = 'OPERATOR_BASIC';
  const AUTHORIZED_USER = 'AUTHORISED';
  const GUEST = 'GUEST';

  /**
   * @var \Phalcon\Cache\Backend\File
   */
  protected $_cache;

  /**
   * @var \Engine\Db\Adapter\Pdo\Postgresql
   */
  protected $_db;

  use \Library\Tools\Traits\DIaware, \Library\Tools\Traits\DbResource;

  public function __construct(\Phalcon\DiInterface $di=null) {
    if(null == $di) {
      $di = \Phalcon\DI::getDefault();
    }

    $this->setDi($di);

    $this->_cache = $this->_di->get('cacheData');

    $this->_db = $this->_di->get('db');
  }

  public function getUserRoles($user_id) {
    /**
     * @var $cache \Phalcon\Cache\Backend\File
     */
    $cache = $this->_cache;
    $user_roles = $cache->get("user_roles_$user_id");
    if (!$user_roles) {
      $all_roles = $cache->get('acl_roles');
      if (!$all_roles) {
        $all_roles = $this->_db->fetchAll("SELECT * FROM acl_roles WHERE actual=true", \Phalcon\Db::FETCH_ASSOC, array());
        $cache->save($all_roles, 'acl_roles', array('acl'), PURGEABLE_CACHE_TTL);
      }
      /*$roles_inheritance = $cache->get('acl_roles_inheritance');
      if (!$roles_inheritance) {
        $roles_inheritance = $db->fetchAll("SELECT child_role_id, parent_role_id FROM roles_inheritance", \Phalcon\Db::FETCH_ASSOC, array());
        $cache->save($roles_inheritance, 'acl_roles_inheritance', array('acl'), PURGEABLE_CACHE_TTL);
      }*/
      $roles = array();
      if ($user_id) {
        $builder = new Select($this->_db);
        $roles_sel = $builder->from(array('ur'=>'user_roles'), array('acl_role_id'))
          ->where('actual=true')
          ->where('user_id=?');
        $roles = $this->_db->fetchCol('acl_role_id', $roles_sel->__toString(), array($user_id));

      } else {
        $roles[] = self::GUEST;
      }
      $user_roles = array();
      while (!empty($roles)) {
        $role = array_pop($roles);
	      if(!is_numeric($role)) {
		      $user_roles[] = $role;
	      } else {
		      $code = '';
		      foreach ( $all_roles as $r ) {
			      if ( $r['id'] == $role ) {
				      $code = $r['code'];
			      }
		      }
		      if ( empty( $code ) ) {
			      continue;
		      }
		      if ( in_array( $code, $user_roles, true ) ) {
			      continue;
		      }
		      $user_roles[] = $code;
	      }
      }
	    $unique_roles = array_unique($user_roles);
      $user_roles = $unique_roles;
      asort($user_roles);
      $cache->save($user_roles, "user_roles_$user_id", array('acl'), PURGEABLE_CACHE_TTL);
    }
    return $user_roles;
  }

  public function checkAccessAllowed($uid, $controller, $action, $module) {
    $controller = String::strtolower($controller);
    $action = String::strtolower($action);
    $module = String::strtolower($module);
    $uid = intval($uid);

    $user_roles = $this->getUserRoles($uid);
    if (!$user_roles) {
      return false;
    }
    $builder = new Select($this->_db);
    $sel = $builder
              ->from(array('r'=>'api_resources'), array('id'))
              ->join(array('ar'=>'api_access_rights'), 'ar.api_resource_id=r.id', array())
              ->where('lower(r.controller)=? AND lower(r.action)=? AND lower(r.module)=?')
              ->where('ar.acl_role_id IN (?)', $user_roles)
              ->where('ar.mode=true')
              ->where("(select count(1) from api_access_rights AS dr where dr.mode=false AND dr.api_resource_id=r.id AND dr.acl_role_id=ar.acl_role_id)=0");
     $sel->limit(1);
    //logVar($sel->__toString(), "ACL SELECT");
    $allowed = $this->_db->fetchOne($sel->__toString(), \Phalcon\Db::FETCH_ASSOC, array($controller, $action, $module));
    if (null===$allowed['id'] || false===$allowed['id']) {
      return false;
    }
    return true;
  }


  public function getRoles() {
    $current_roles = $this->_db->fetchAll("SELECT * FROM acl_roles", \Phalcon\Db::FETCH_ASSOC);
    $roles = array();
    foreach ($current_roles as $r) {
      $roles[$r['code']] = $r['id'];
    }
    $roles = array_unique($roles);

    return $roles;
  }

  public function getRolesArray() {
    return $this->_db->fetchPairs('code','name',"SELECT code,name FROM acl_roles");
  }
  public function updateRoles($roles) {
    return $this->updateResource('acl_roles', $roles, array('name'));
  }

  public function deleteRoles($roles) {
    $result = $this->deleteResources('acl_roles', $roles);
    return $result;
  }

  public function getRoleAPIAccess($role_id) {
    $builder = new Select($this->_db);
    $sel = $builder
              ->from(array('r' => 'api_resources'), array('*'))
              ->join(array('ar' => 'api_access_rights'), "ar.api_resource_id=r.id", array('mode'))
              ->where("ar.acl_role_id=:id");
    return $this->_db->fetchAll($sel, \Phalcon\Db::FETCH_ASSOC, array('id'=>$role_id));
  }

  public function getRoleGUIAccess($role_id) {
    $builder = new Select($this->_db);
    $sel = $builder
              ->from(array('r' => 'gui_resources'), array('*'))
              ->join(array('ar' => 'gui_access_rights'), "ar.gui_resource_id=r.id", array('mode'))
              ->where("ar.acl_role_id=:id");
    return $this->_db->fetchAll($sel, \Phalcon\Db::FETCH_ASSOC, array('id'=>$role_id) );
  }

  public function getGUIResources() {
    return $this->_db->fetchAll("SELECT * FROM gui_resources", \Phalcon\Db::FETCH_ASSOC);
  }

  public function getAPIResources() {
    return $this->_db->fetchAll("SELECT * FROM api_resources", \Phalcon\Db::FETCH_ASSOC);
  }

  /**
   * Получает свойства ресурса API
   * @param int|array $resource Если int — id ресурса, если массив то ресурс определяется
   * по полям module, controller и action этого массива
   * @return array ресурс или null если нету
   */
  public function getAPIResource($resource) {
    if (is_array($resource)) {
      $r = $this->_db->fetchAll("SELECT * FROM api_resources WHERE lower(controller)=? AND lower(action)=? AND lower(module)=?",\Phalcon\Db::FETCH_ASSOC,
                         array(String::strtolower($resource['controller']), String::strtolower($resource['action']), String::strtolower($resource['module'])) );
    } else {
      $r = $this->_db->fetchAll("SELECT * FROM api_resources WHERE id=?", \Phalcon\Db::FETCH_ASSOC, array(intval($resource)));
    }
    if (!$r) {
      return null;
    } else {
      return array_shift($r);
    }
  }


  public function updateAPIResources($resources) {
    return $this->updateResource('api_resources', $resources, array('module','controller', 'action'));
  }

  public function updateGUIResources($resources) {
    $this->updateResource('gui_resources', $resources, array('url'));
  }

  public function deleteGUIResources($resources) {
    return $this->deleteResources('gui_resources', $resources);
  }

  public function deleteAPIResources($resources) {
    return $this->deleteResources('api_resources', $resources);
  }

  public function updateRoleAPIAccess($role, $access) {
    if (!isset($access[0])) {
      $access = array($access);
    }

    foreach ($access as $a) {
      $id = intval($a['id']);
      $found = $this->_db->fetchOne("SELECT acl_role_id FROM api_access_rights WHERE acl_role_id=? AND api_resource_id=?", array($role, $id));
      if (false===$found || empty($found)) {
        $this->_db->insert('api_access_rights', array($role, $id, $a['mode']?1:0), array('acl_role_id', 'api_resource_id', 'mode'));
      } else {
        $this->_db->update('api_access_rights', array('mode'), array($a['mode']?1:0), $this->_db->quoteInto("acl_role_id=?", $role) ." AND ". $this->_db->quoteInto("api_resource_id=?", $id));
      }
    }
    return $access;
  }

  public function updateRoleGUIAccess($role, $access) {
    if (!isset($access[0])) {
      $access = array($access);
    }

    foreach ($access as $a) {
      $id = intval($a['id']);
      $found = $this->_db->fetchOne("SELECT acl_role_id FROM gui_access_rights WHERE acl_role_id=? AND gui_resource_id=?", array($role, $id));
      if (false===$found || empty($found)) {
        $this->_db->insert('gui_access_rights', array($role, $id, $a['mode']?1:0), array('acl_role_id', 'gui_resource_id', 'mode'));
      } else {
        $this->_db->update('gui_access_rights', array('mode'), array($a['mode']?1:0), $this->_db->quoteInto("acl_role_id=?", $role) ." AND ". $this->_db->quoteInto("gui_resource_id=?", $id));
      }
    }
    return $access;
  }

  public function deleteRoleAPIAccess($role, $access) {
    if (!is_array($access)) {
      $access = array($access);
    }
    $role = intval($role);
    foreach ($access as $id) {
      $id = intval($id);
      $this->_db->delete('api_access_rights', $this->_db->quoteInto("acl_role_id=?", $role) ." AND ". $this->_db->quoteInto("api_resource_id=?", $id));
    }
    return $access;
  }

  public function deleteRoleGUIAccess($role, $access) {
    if (!is_array($access)) {
      $access = array($access);
    }
    $role = intval($role);
    foreach ($access as $id) {
      $id = intval($id);
      $this->_db->delete('gui_access_rights', $this->_db->quoteInto("acl_role_id=?", $role) ." AND ". $this->_db->quoteInto("gui_resource_id=?", $id));
    }
    return $access;
  }

  public function getRoleInheritances($role, $type='child') {
    $builder = new Select($this->_db);
    $select = $builder->from(array('ri'=>'roles_inheritance'));
    if ($role) {
      if ('parent'==$type) {
        $select->where('ri.child_role_id=?', $role)
               ->join(array('r'=>'acl_roles'), 'r.id=ri.parent_role_id', array('name'));
      } else {
        $select->where('ri.parent_role_id=?', $role)
               ->join(array('r'=>'acl_roles'), 'r.id=ri.child_role_id', array('name'));
      }
    }
    return $this->_db->fetchAll($select->__toString(), \Phalcon\Db::FETCH_ASSOC);
  }

  public function updateRoleInheritances($inheritances) {
    return $this->updateResource('roles_inheritance', $inheritances, array());
  }

  public function deleteRoleInheritances($inheritances) {
    $this->deleteResources('roles_inheritance', $inheritances, array());
  }

  public function build(\Phalcon\Acl\Adapter\Memory $acl = null) {
    if(null === $acl) {
      $acl = new \Phalcon\Acl\Adapter\Memory();
      $acl->setDefaultAction(\Phalcon\Acl::DENY);
    }

    if(!$acls=$this->_cache->get('acl')) {

      $roles = $this->getRoles() ;

      $controllers = array();

      foreach ( $roles as $code=>$r ) {
        $acl->addRole( $code );
        $api_resources = $this->getRoleAPIAccess( $r );

        if ( ! empty( $api_resources ) ) {
          foreach ( $api_resources as $res ) {
            if ( ! isset( $controllers[ $res['module'] ] ) ) {
              $controllers[ $res['module'] ] = array();
            }

            if ( ! isset( $controllers[ $res['module'] ][ $res['controller'] ] ) ) {
              $resource                                            = new \Phalcon\Acl\Resource( $res['controller'] );
              $controllers[ $res['module'] ][ $res['controller'] ] = $resource;
            }

            $acl->addResource( $controllers[ $res['module'] ][ $res['controller'] ], $res['action'] );

            if ( intval($res['mode']) === 1 ) {
              $acl->allow( $code, $res['controller'], $res['action'] );
            } else {
              $acl->deny( $code, $res['controller'], $res['action'] );
            }
          }
        }
      }
      $this->_cache->save('acl', serialize($acl));
      return $acl;
    }
    return unserialize($acls);
  }

} 