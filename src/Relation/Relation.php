<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @see http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */

namespace Pulsar\Relation;

use Pulsar\Model;
use Pulsar\Query;

/**
 * Class Relation.
 */
abstract class Relation
{
    /**
     * @var string
     */
    protected $model;

    /**
     * @var string
     */
    protected $foreignKey;

    /**
     * @var string
     */
    protected $localKey;

    /**
     * @var Query
     */
    protected $query;

    /**
     * @var Model
     */
    protected $relation;

    public function __construct($model, $foreignKey, $localKey, Model $relation)
    {
        $this->model = new $model;

        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;

        $this->relation = $relation;

        $this->query = new Query($this->model);
        $this->initQuery();
    }

    /**
     * Gets the model class this relation retrieves.
     *
     * @return string
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Gets the name of the foreign key of the foreign model.
     *
     * @return string
     */
    public function getForeignKey()
    {
        return $this->foreignKey;
    }

    /**
     * Gets the name of the foreign key of the relation model.
     *
     * @return string
     */
    public function getLocalKey()
    {
        return $this->localKey;
    }

    /**
     * Gets the relation model.
     *
     * @return Model
     */
    public function getRelation()
    {
        return $this->relation;
    }

    /**
     * Returns the query instance for this relation.
     *
     * @return Query
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Called to initialize the query.
     */
    abstract protected function initQuery();

    /**
     * Called to get the results of the relation query.
     *
     * @return mixed
     */
    abstract public function getResults();

    public function __call($method, $arguments)
    {
        // try calling any unknown methods on the query
        return call_user_func_array([$this->query, $method], $arguments);
    }
}
