<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
namespace Pulsar\Relation;

use Pulsar\Model;

class Pivot extends Model
{
    /**
     * @var string
     */
    protected $_tablename;

    public function setTablename($tablename)
    {
        $this->_tablename = $tablename;
    }

    public function getTablename()
    {
        return $this->_tablename;
    }
}
