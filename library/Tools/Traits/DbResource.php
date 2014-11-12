<?php
/**
 * @namespace
 */
namespace Library\Tools\Traits;

/**
 * Trait database resoures update.
 *
 * @category   Microapp
 * @package    Tools
 */
trait DbResource
{

	public function updateResource($table, $data, $check_tables) {
	  if (!isset($data[0])) {
	    $data = array($data);
	  }
	  $updated = array();
		/**
		 * @var $db \Engine\Db\Adapter\Pdo\Postgresql
		 */
		$db = $this->_di->get('db');

	  $columns = $db->describeColumns($table);
		$cols = array();
	  foreach($columns as $c) {
		  $cols[] = $c->getName();
	  }
	  foreach ($data as $r) {
	    if (isset($r['id']) && intval($r['id'])>0) {
	      $skip = false;
	      foreach ($check_tables as $t) {
	        if (isset($r[$t]) && ('' ===$r[$t]||null===$r[$t])) {
	          $skip = true;
	          break;
	        }
	      }
	      if ($skip) {
	        continue;
	      }
	      $rowdata = array();
	      foreach ($r as $k=>$v) {
	        if ('id'==$k || !isset($cols[$k])) {
	          continue;
	        }
	        if (is_bool($v)) {
	          $v = $v?1:0;
	        }
	        $rowdata[$k] = $v;
	      }
	      $db->update($table, array_keys($rowdata), array_values($rowdata), $db->quoteInto("id=?", intval($r['id'])));
	      $updated[] = array_merge($r, array('id'=>$r['id']));
	    } elseif (!isset($r['id']) || 0==$r['id']) {
	      $skip = false;
	      foreach ($check_tables as $t) {
	        if (!isset($r[$t]) || ('' ===$r[$t]||null===$r[$t])) {
	          $skip = true;
	          break;
	        }
	      }
	      if ($skip) {
	        continue;
	      }
	      $rowdata = array();
	      foreach ($r as $k=>$v) {
	        if ('id'==$k || !isset($cols[$k])) {
	          continue;
	        }
	        if (is_bool($v)) {
	          $v = $v?1:0;
	        }
	        $rowdata[$k] = $v;
	      }
	      $db->insert($table, array_values($rowdata), array_keys($rowdata));
	      $id = $db->lastInsertId($table.'_id_seq');
	      $updated[] = array_merge($r, array('id'=>$id));
	    }
	  }
	  return $updated;
	}

	public function deleteResources($table, $resources) {
	  /**
		 * @var $db \Engine\Db\Adapter\Pdo\Postgresql
		 */
		$db = $this->_di->get('db');
	  if (!is_array($resources)) {
	    $resources = array($resources);
	  }
	  foreach ($resources as $k=>$v) {
	    $resources[$k] = intval($v);
	  }
	  $ids = join(',', $resources);
	  $db->delete($table, "id in ($ids)");
	  return $resources;
	}
	
}