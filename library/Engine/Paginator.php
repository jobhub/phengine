<?php
/**
 * Created by PhpStorm.
 * User: patris
 * Date: 05.11.14
 * Time: 15:39
 */

namespace Engine;


class Paginator implements \Phalcon\Paginator\AdapterInterface
{
	/**
	 * @var Db\Select $_builder
	 */
		protected $_builder;

		protected $_limitRows;

	  protected $_offset;

		protected $_page;
	
		protected $_countSelect;

		protected $_rowCount;

		const ROWCOUNT_COLUMN = 'paginator_counter';
    /**
     * Конструктор адаптера
     *
     * @param array $config
     */
    public function __construct($config) {
	    $defaults = array(
		    'limit'=>20,
		    'offset'=> 0
	    );
	    $config = array_merge($defaults, $config);

			$this->_limitRows = $config['limit'];
	    $this->_offset = $config['offset'];

	    $this->setCurrentPage(ceil($config['offset']/$this->_limitRows)+1);

	    $this->_builder = $config['builder'];
    }

    /**
     * Установка текущей страницы
     *
     * @param int $page
     */
    public function setCurrentPage($page) {
			$this->_page = $page;
    }

    /**
     * Возвращает срез данных для вывода
     *
     * @return stdClass
     */
    public function getPaginate() {
	    $b = $this->_builder;

			$b->limit($this->_limitRows, $this->_offset);

	    $query = $b->__toString();

	    return $b->getAdapter()->fetchAll($query, \Phalcon\Db::FETCH_ASSOC, $b->getBind());
    }
	
		/**
     * Get the COUNT select object for the provided query
     *
     * TODO: Have a look at queries that have both GROUP BY and DISTINCT specified.
     * In that use-case I'm expecting problems when either GROUP BY or DISTINCT
     * has one column.
     *
     * @return \Engine\Db\Select
     */
    public function getCountSelect()
    {
        /**
         * We only need to generate a COUNT query once. It will not change for
         * this instance.
         */
        if ($this->_countSelect !== null) {
            return $this->_countSelect;
        }
        $rowCount = clone $this->_builder;
        $rowCount->__toString(); // Workaround for ZF-3719 and related
        $db = $rowCount->getAdapter();
        $countColumn = $db->quoteIdentifier(self::ROWCOUNT_COLUMN);
        $countPart   = 'COUNT(1) AS ';
        $groupPart   = null;
        $unionParts  = $rowCount->getPart(\Engine\Db\Select::UNION);
        /**
         * If we're dealing with a UNION query, execute the UNION as a subquery
         * to the COUNT query.
         */
        if (!empty($unionParts)) {
            $expression = new Db\Expr($countPart . $countColumn);
            $rowCount = $db
                            ->select()
                            ->bind($rowCount->getBind())
                            ->from($rowCount, $expression);
        } else {
            $columnParts = $rowCount->getPart(Db\Select::COLUMNS);
            $groupParts  = $rowCount->getPart(Db\Select::GROUP);
            $havingParts = $rowCount->getPart(Db\Select::HAVING);
            $isDistinct  = $rowCount->getPart(Db\Select::DISTINCT);
            /**
             * If there is more than one column AND it's a DISTINCT query, more
             * than one group, or if the query has a HAVING clause, then take
             * the original query and use it as a subquery os the COUNT query.
             */
            if (($isDistinct && ((count($columnParts) == 1 && $columnParts[0][1] == Db\Select::SQL_WILDCARD) 
                 || count($columnParts) > 1)) || count($groupParts) > 1 || !empty($havingParts)) {
                $rowCount->reset(Db\Select::ORDER);
	              
                $rowCount = $db
                               ->select()
                               ->bind($rowCount->getBind())
                               ->from($rowCount);
            } else if ($isDistinct) {
                $part = $columnParts[0];
                if ($part[1] !== Db\Select::SQL_WILDCARD && !($part[1] instanceof Db\Expr)) {
                    $column = $db->quoteIdentifier($part[1], true);
                    if (!empty($part[0])) {
                        $column = $db->quoteIdentifier($part[0], true) . '.' . $column;
                    }
                    $groupPart = $column;
                }
            } else if (!empty($groupParts)) {
                $groupPart = $db->quoteIdentifier($groupParts[0], true);
            }
            /**
             * If the original query had a GROUP BY or a DISTINCT part and only
             * one column was specified, create a COUNT(DISTINCT ) query instead
             * of a regular COUNT query.
             */
            if (!empty($groupPart)) {
                $countPart = 'COUNT(DISTINCT ' . $groupPart . ') AS ';
            }
            /**
             * Create the COUNT part of the query
             */
            $expression = new Db\Expr($countPart . $countColumn);
            $rowCount->reset(Db\Select::COLUMNS)
                     ->reset(Db\Select::ORDER)
                     ->reset(Db\Select::LIMIT_OFFSET)
                     ->reset(Db\Select::GROUP)
                     ->reset(Db\Select::DISTINCT)
                     ->reset(Db\Select::HAVING)
                     ->columns($expression);
        }
        $this->_countSelect = $rowCount;
        return $rowCount;
    }

		public function count()
    {
        if ($this->_rowCount === null) {
            $this->setRowCount(
                $this->getCountSelect()
            );
        }
        return $this->_rowCount;
    }
	
		public function setRowCount($rowCount)
    {
        if ($rowCount instanceof Db\Select) {
            $columns = $rowCount->getPart(Db\Select::COLUMNS);
            $countColumnPart = empty($columns[0][2])
                             ? $columns[0][1]
                             : $columns[0][2];
            if ($countColumnPart instanceof Db\Expr) {
                $countColumnPart = $countColumnPart->__toString();
            }
            $rowCountColumn = self::ROWCOUNT_COLUMN;
            // The select query can contain only one column, which should be the row count column
            if (false === strpos($countColumnPart, $rowCountColumn)) {
                throw new Exception('Row count column not found');
            }
            $result = $rowCount->query(\Phalcon\Db::FETCH_ASSOC)->fetch();
            $this->_rowCount = count($result) > 0 ? $result[$rowCountColumn] : 0;
        } else if (is_integer($rowCount)) {
            $this->_rowCount = $rowCount;
        } else {
            throw new Exception('Invalid row count');
        }
        return $this;
    }

	public function getRowCount() {
		return $this->_rowCount;
	}

}