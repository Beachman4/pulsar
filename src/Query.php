<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @see http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */

namespace Pulsar;

class Query
{
    const DEFAULT_LIMIT = 100;
    const MAX_LIMIT = 1000;

    /**
     * @var string
     */
    private $model;

    /**
     * @var array
     */
    private $joins;

    /**
     * @var array
     */
    private $where;

    private $withs;

    /**
     * @var int
     */
    private $limit;

    /**
     * @var int
     */
    private $start;

    /**
     * @var array
     */
    private $sort;

    /**
     * @param string $model model class
     */
    public function __construct(Model $model = null)
    {
        $this->model = $model;
        $this->joins = [];
        $this->where = [];
        $this->withs = [];
        $this->start = 0;
        $this->limit = self::DEFAULT_LIMIT;
        $this->sort = [];
    }

    /**
     * Gets the model class associated with this query.
     *
     * @return string
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Sets the limit for this query.
     *
     * @param int $limit
     *
     * @return self
     */
    public function limit($limit)
    {
        $this->limit = min($limit, self::MAX_LIMIT);

        return $this;
    }

    /**
     * Gets the limit for this query.
     *
     * @return int
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * Sets the start offset.
     *
     * @param int $start
     *
     * @return self
     */
    public function start($start)
    {
        $this->start = max($start, 0);

        return $this;
    }

    /**
     * Gets the start offset.
     *
     * @return int
     */
    public function getStart()
    {
        return $this->start;
    }

    /**
     * Sets the sort pattern for the query.
     *
     * @param array|string $sort
     *
     * @return self
     */
    public function sort($sort)
    {
        $columns = explode(',', $sort);

        $sortParams = [];
        foreach ($columns as $column) {
            $c = explode(' ', trim($column));

            if (count($c) != 2) {
                continue;
            }

            // validate direction
            $direction = strtolower($c[1]);
            if (!in_array($direction, ['asc', 'desc'])) {
                continue;
            }

            $sortParams[] = [$c[0], $direction];
        }

        $this->sort = $sortParams;

        return $this;
    }

    /**
     * Gets the sort parameters.
     *
     * @return array
     */
    public function getSort()
    {
        return $this->sort;
    }

    /**
     * Sets the where parameters.
     * Accepts the following forms:
     *   i)   where(['name' => 'Bob'])
     *   ii)  where('name', 'Bob')
     *   iii) where('balance', 100, '>')
     *   iv)  where('balance > 100').
     *
     * @param array|string $where
     * @param mixed        $value     optional value
     * @param string|null  $condition optional condition
     *
     * @return self
     */
    public function where($where, $value = null, $condition = null)
    {
        // handles i.
        if (is_array($where)) {
            $this->where = array_merge($this->where, $where);
        } else {
            // handles iii.
            $args = func_num_args();
            if ($args > 2) {
                $this->where[] = [$where, $value, $condition];
            // handles ii.
            } elseif ($args == 2) {
                $this->where[$where] = $value;
            // handles iv.
            } else {
                $this->where[] = $where;
            }
        }

        return $this;
    }

    /**
     * Gets the where parameters.
     *
     * @return array
     */
    public function getWhere()
    {
        return $this->where;
    }

    /**
     * Adds a join to the query. Matches a property on this model
     * to the ID of the model we are joining.
     *
     * @param string $model      model being joined
     * @param string $column     name of local property
     * @param string $foreignKey
     *
     * @return self
     */
    public function join($model, $column, $foreignKey)
    {
        $this->joins[] = [$model, $column, $foreignKey];

        return $this;
    }

    /**
     * Gets the joins.
     *
     * @return array
     */
    public function getJoins()
    {
        return $this->joins;
    }

    /**
     * Executes the query against the model's driver.
     *
     * @return array results
     */
    public function execute()
    {
        $model = $this->model;

        $driver = $model::getDriver();

        $models = [];
        foreach ($driver->queryModels($this) as $row) {
            // get the model's ID
            $id = [];
            foreach ($model::getIDProperties() as $k) {
                $id[] = $row[$k];
            }

            $newModel = new $model($id, $row);

            if (count($this->withs)) {

                foreach($this->withs as $with) {
                    $relationship = $this->getRelationship($newModel, $with);

                    $newModel->{$with} = $relationship->getResults();

                    $newModel->addRelationship($with);
                }
            }


            // create the model and cache the loaded values
            $models[] = $newModel;
        }

        return $models;
    }

    /**
     * Creates an iterator for a search.
     *
     * @return Iterator
     */
    public function all()
    {
        return new Iterator($this);
    }

    /**
     * Executes the query against the model's driver and returns the first result.
     *
     * @param int $limit
     *
     * @return array|Model|null when $limit = 1, returns a single model or null, otherwise returns an array
     */
    public function first($limit = 1)
    {
        $models = $this->limit($limit)->execute();

        if ($limit == 1) {
            return (count($models) == 1) ? $models[0] : null;
        }

        return $models;
    }

    public function with($column)
    {
        if (is_array($column)) {
            foreach($column as $item) {
                $this->with($item);
            }
        }

        array_push($this->withs, $column);

        return $this;
    }

    public function getWiths()
    {
        return $this->withs;
    }

    public function getRelationship($model, $with)
    {
        return call_user_func([$model, $with]);
    }
}
