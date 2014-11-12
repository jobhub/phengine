<?php
/**
 * Created by PhpStorm.
 * User: patris
 * Date: 03.10.14
 * Time: 15:02
 */

namespace Engine;

use Phalcon\DI\InjectionAwareInterface, \Engine\Db\Select, \Library\Tools\String;

class AclRole implements InjectionAwareInterface {

  const GUEST = 'GUEST';  //Анонимный пользователь (гость)
	const AUTHORISED           = 'AUTHORISED';         // Базовая роль авторизации
  const PARTNER_BASIC        = 'PARTNER_BASIC';      //Авторизированный пользователь

  const OPERATOR_BASIC       = 'OPERATOR_BASIC';     //Авторизированный оператор

  const PARTNER_USER         = 'PARTNER_USER';        //Пользователь магазина-партнера

  const PARTNER_ADMIN        = 'PARTNER_ADMIN';       //Пользователь магазина-партнера с правами администратора

  const OPERATOR_SUPERADMIN  = 'OPERATOR_SUPERADMIN'; //Администратор системы
  const OPERATOR_ADMIN       = 'OPERATOR_ADMIN';      //Администратор оператора системы
  const OPERATOR_USER        = 'OPERATOR_USER';       //Сотрудник оператора системы

  use \Library\Tools\Traits\DIaware;

	/**
	 * @var \Engine\Db\Adapter\Pdo\Postgresql
	 */
	protected $_db;

  public function __construct(\Phalcon\DiInterface $di=null) {
    if(null == $di) {
      $di = \Phalcon\DI::getDefault();
    }

    $this->setDi($di);

    $this->_db = $this->_di->get('db');
  }

  /**
   * @param $code
   * @return null|array
   */
  public function loockupRole($code) {
    $query = "SELECT * FROM acl_roles WHERE code=:code";
    $result = $this->_db->fetchAll($query, \Phalcon\Db::FETCH_ASSOC, array('code' => $code));
    return !empty($result) ? $result[0] : null;
  }

	/**
   * @param $code
   * @return null|array
   */
  public function getRoleCodeById($id) {
    $query = "SELECT code FROM acl_roles WHERE id=:id";
    $result = $this->_db->fetchCol('code', $query, array('id' => $id));
    return !empty($result) ? $result[0] : null;
  }
} 