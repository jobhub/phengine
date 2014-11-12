<?php
/**
 * Created by PhpStorm.
 * User: patris
 * Date: 30.09.14
 * Time: 15:41
 */

namespace Engine\Db;

/**
 * Zend Framework port Zend_Db_Expr
 */


/**
 * Class for SQL SELECT fragments.
 *
 * This class simply holds a string, so that fragments of SQL statements can be
 * distinguished from identifiers and values that should be implicitly quoted
 * when interpolated into SQL statements.
 *
 * For example, when specifying a primary key value when inserting into a new
 * row, some RDBMS brands may require you to use an expression to generate the
 * new value of a sequence.  If this expression is treated as an identifier,
 * it will be quoted and the expression will not be evaluated.  Another example
 * is that you can use Zend_Db_Expr in the Zend_Db_Select::order() method to
 * order by an expression instead of simply a column name.
 *
 * The way this works is that in each context in which a column name can be
 * specified to methods of Zend_Db classes, if the value is an instance of
 * Zend_Db_Expr instead of a plain string, then the expression is not quoted.
 * If it is a plain string, it is assumed to be a plain column name.
 *
 */
class Expr
{
    /**
     * Storage for the SQL expression.
     *
     * @var string
     */
    protected $_expression;

    /**
     * Instantiate an expression, which is just a string stored as
     * an instance member variable.
     *
     * @param string $expression The string containing a SQL expression.
     */
    public function __construct($expression)
    {
        $this->_expression = (string) $expression;
    }

    /**
     * @return string The string of the SQL expression stored in this object.
     */
    public function __toString()
    {
        return $this->_expression;
    }

}
