<?php
/**
 * Created by PhpStorm.
 * User: patris
 * Date: 30.09.14
 * Time: 18:08
 */

namespace Engine\Db\Adapter\Pdo;

use \Library\Tools\String;


class Postgresql extends \Phalcon\Db\Adapter\Pdo\Postgresql {
	/**
     * Quotes a value and places into a piece of text at a placeholder.
     *
     * The placeholder is a question-mark; all placeholders will be replaced
     * with the quoted value.   For example:
     *
     * <code>
     * $text = "WHERE date < ?";
     * $date = "2005-01-02";
     * $safe = $sql->quoteInto($text, $date);
     * // $safe = "WHERE date < '2005-01-02'"
     * </code>
     *
     * @param string  $text  The text with a placeholder.
     * @param mixed   $value The value to quote.
     * @param string  $type  OPTIONAL SQL datatype
     * @param integer $count OPTIONAL count of placeholders to replace
     * @return string An SQL-safe quoted value placed into the original text.
     */
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
     * Quotes an identifier.
     *
     * Accepts a string representing a qualified indentifier. For Example:
     * <code>
     * $adapter->quoteIdentifier('myschema.mytable')
     * </code>
     * Returns: "myschema"."mytable"
     *
     * Or, an array of one or more identifiers that may form a qualified identifier:
     * <code>
     * $adapter->quoteIdentifier(array('myschema','my.table'))
     * </code>
     * Returns: "myschema"."my.table"
     *
     * The actual quote character surrounding the identifiers may vary depending on
     * the adapter.
     *
     * @param string|array|\Engine\Db\Expr $ident The identifier.
     * @param boolean $auto If true, heed the AUTO_QUOTE_IDENTIFIERS config option.
     * @return string The quoted identifier.
     */
    public function quoteIdentifier($ident, $auto=false)
    {
        return $this->_quoteIdentifierAs($ident, null, $auto);
    }

    /**
     * Quote a column identifier and alias.
     *
     * @param string|array|\Engine\Db\Expr $ident The identifier or expression.
     * @param string $alias An alias for the column.
     * @param boolean $auto If true, heed the AUTO_QUOTE_IDENTIFIERS config option.
     * @return string The quoted identifier and alias.
     */
    public function quoteColumnAs($ident, $alias, $auto=false)
    {
        return $this->_quoteIdentifierAs($ident, $alias, $auto);
    }

    /**
     * Quote a table identifier and alias.
     *
     * @param string|array|\Engine\Db\Expr $ident The identifier or expression.
     * @param string $alias An alias for the table.
     * @param boolean $auto If true, heed the AUTO_QUOTE_IDENTIFIERS config option.
     * @return string The quoted identifier and alias.
     */
    public function quoteTableAs($ident, $alias = null, $auto = false)
    {
        return $this->_quoteIdentifierAs($ident, $alias, $auto);
    }

    /**
     * Quote an identifier and an optional alias.
     *
     * @param string|array|\Engine\Db\Expr $ident The identifier or expression.
     * @param string $alias An optional alias.
     * @param boolean $auto If true, heed the AUTO_QUOTE_IDENTIFIERS config option.
     * @param string $as The string to add between the identifier/expression and the alias.
     * @return string The quoted identifier and alias.
     */
    protected function _quoteIdentifierAs($ident, $alias = null, $auto = false, $as = ' AS ')
    {
        if ($ident instanceof \Engine\Db\Expr) {
            $quoted = $ident->__toString();
        } elseif ($ident instanceof \Engine\Db\Select) {
            $quoted = '(' . $ident->assemble() . ')';
        } else {
            if (is_string($ident)) {
                $ident = explode('.', $ident);
            }
            if (is_array($ident)) {
                $segments = array();
                foreach ($ident as $segment) {
                    if ($segment instanceof \Engine\Db\Expr) {
                        $segments[] = $segment->__toString();
                    } else {
                        $segments[] = String::_quoteIdentifier($segment, $auto);
                    }
                }
                if ($alias !== null && end($ident) == $alias) {
                    $alias = null;
                }
                $quoted = implode('.', $segments);
            } else {
                $quoted = String::_quoteIdentifier($ident, $auto);
            }
        }
        if ($alias !== null) {
            $quoted .= $as . String::_quoteIdentifier($alias, $auto);
        }
        return $quoted;
    }

    public function fetchCol($column_name, $query_string, $bind=array()) {
        $result = $this->fetchAll($query_string, \Phalcon\Db::FETCH_ASSOC, $bind);


        if(!$result || empty($result)) {
            return array();
        }

        $column = array();
        foreach($result as $r) {
            $column[] = $r[$column_name];
        }
        return $column;
    }

    public function fetchPairs($key_field, $value_field, $query_string, $bind=array()) {
        $result = $this->fetchAll($query_string, \Phalcon\Db::FETCH_ASSOC, $bind);


        if(!$result || empty($result)) {
            return array();
        }

        $pairs = array();

        foreach($result as $r) {
            if(!array_key_exists($key_field,$r) || !array_key_exists($value_field, $r)) {
                throw new \Engine\Exception('FIELD_NOT_FOUND');
            }
            $pairs[$r[$key_field]] = $r[$value_field];
        }
        return $pairs;
    }

		public function select() {
			return new \Engine\Db\Select($this);
		}
} 