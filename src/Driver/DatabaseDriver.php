<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
namespace Pulsar\Driver;

use Carbon\Carbon;
use ICanBoogie\Inflector;
use Pulsar\Exception\DriverException;
use Pulsar\Model;
use Pulsar\Query;
use PDOException;
use PDOStatement;
use Pimple\Container;

class DatabaseDriver implements DriverInterface
{
    /**
     * @var \Pimple\Container
     */
    private $app;

    /**
     * @param \Pimple\Container $app
     */
    public function __construct(Container $app = null)
    {
        $this->app = $app;
    }

    public function createModel(Model $model, array $parameters)
    {
        $values = $this->serialize($model, $parameters);
        $tablename = $this->getTablename($model);

        try {
            return $this->app['db']->insert($values)
                ->into($tablename)
                ->execute() instanceof PDOStatement;
        } catch (PDOException $original) {
            $e = new DriverException('An error occurred in the database driver when creating the '.$model::modelName());
            $e->setException($original);
            throw $e;
        }
    }

    public function getCreatedID(Model $model, $propertyName)
    {
        try {
            $id = $this->app['db']->getPDO()->lastInsertId();
        } catch (PDOException $original) {
            $e = new DriverException('An error occurred in the database driver when getting the ID of the new '.$model::modelName());
            $e->setException($original);
            throw $e;
        }

        return $id;
    }

    public function updateModel(Model $model, array $parameters)
    {
        if (count($parameters) == 0) {
            return true;
        }

        $values = $this->serialize($model, $parameters);
        $tablename = $this->getTablename($model);

        try {
            return $this->app['db']->update($tablename)
                ->values($values)
                ->where($model->ids())
                ->execute() instanceof PDOStatement;
        } catch (PDOException $original) {
            $e = new DriverException('An error occurred in the database driver when updating the '.$model::modelName());
            $e->setException($original);
            throw $e;
        }
    }

    public function deleteModel(Model $model)
    {
        $tablename = $this->getTablename($model);

        try {
            return $this->app['db']->delete($tablename)
                ->where($model->ids())
                ->execute() instanceof PDOStatement;
        } catch (PDOException $original) {
            $e = new DriverException('An error occurred in the database driver while deleting the '.$model::modelName());
            $e->setException($original);
            throw $e;
        }
    }

    public function queryModels(Query $query)
    {
        $model = $query->getModel();
        $tablename = $this->getTablename($model);

        // build a DB query from the model query
        $dbQuery = $this->app['db']
            ->select($this->prefixSelect('*', $tablename))
            ->from($tablename)
            ->where($this->prefixWhere($query->getWhere(), $tablename))
            ->limit($query->getLimit(), $query->getStart())
            ->orderBy($this->prefixSort($query->getSort(), $tablename));

        // join conditions
        foreach ($query->getJoins() as $join) {
            list($foreignModel, $column, $foreignKey) = $join;

            $foreignTablename = $this->getTablename($foreignModel);
            $condition = $this->prefixColumn($column, $tablename).'='.$this->prefixColumn($foreignKey, $foreignTablename);

            $dbQuery->join($foreignTablename, $condition);
        }

        try {
            $data = $dbQuery->all();
        } catch (PDOException $original) {
            $e = new DriverException('An error occurred in the database driver while performing the '.$model::modelName().' query');
            $e->setException($original);
            throw $e;
        }

        return $data;
    }

    public function totalRecords(Query $query)
    {
        $model = $query->getModel();
        $tablename = $this->getTablename($model);

        try {
            return (int) $this->app['db']->select('count(*)')
                ->from($tablename)
                ->where($query->getWhere())
                ->scalar();
        } catch (PDOException $original) {
            $e = new DriverException('An error occurred in the database driver while getting the number of '.$model::modelName().' objects');
            $e->setException($original);
            throw $e;
        }
    }

    /**
     * Generates the tablename for the model.
     *
     * @param string|Model $model
     *
     * @return string
     */
    public function getTablename($model)
    {
        $inflector = Inflector::get();

        return $inflector->camelize($inflector->pluralize($model::modelName()));
    }

    /**
     * Marshals a value to storage.
     *
     * @param mixed $value
     * @param Model|string optional model class
     * @param string $property optional property name
     *
     * @return mixed serialized value
     */
    public function serializeValue($value, $model = null, $property = null)
    {
        // convert dates back to their string representation
        if ($value instanceof Carbon) {
            if (!$model) {
                $model = 'Pulsar\Model';
            }

            $format = $model::getDateFormat($property);

            return $value->format($format);
        }

        // encode arrays/objects as JSON
        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }

        return $value;
    }

    /**
     * Serializes an array of values.
     *
     * @param Model|string model class
     * @param array $values
     *
     * @return array
     */
    private function serialize($model, array $values)
    {
        foreach ($values as $k => &$value) {
            $value = $this->serializeValue($value, $model, $k);
        }

        return $values;
    }

    /**
     * Returns a prefixed select statement.
     *
     * @param string $columns
     * @param string $tablename
     *
     * @return string
     */
    private function prefixSelect($columns, $tablename)
    {
        $prefixed = [];
        foreach (explode(',', $columns) as $column) {
            $prefixed[] = $this->prefixColumn($column, $tablename);
        }

        return implode(',', $prefixed);
    }

    /**
     * Returns a prefixed where statement.
     *
     * @param string $columns
     * @param string $tablename
     *
     * @return string
     */
    private function prefixWhere(array $where, $tablename)
    {
        $return = [];
        foreach ($where as $key => $condition) {
            // handles $where[property] = value
            if (!is_numeric($key)) {
                $return[$this->prefixColumn($key, $tablename)] = $condition;
            // handles $where[] = [property, value, '=']
            } elseif (is_array($condition)) {
                $condition[0] = $this->prefixColumn($condition[0], $tablename);
                $return[] = $condition;
            // handles raw SQL - do nothing
            } else {
                $return[] = $condition;
            }
        }

        return $return;
    }

    /**
     * Returns a prefixed sort statement.
     *
     * @param string $columns
     * @param string $tablename
     *
     * @return string
     */
    private function prefixSort(array $sort, $tablename)
    {
        foreach ($sort as &$condition) {
            $condition[0] = $this->prefixColumn($condition[0], $tablename);
        }

        return $sort;
    }

    /**
     * Prefix columns with tablename that contains only
     * alphanumeric/underscores/*.
     *
     * @param string $column
     * @param string $tablename
     *
     * @return string prefixed column
     */
    private function prefixColumn($column, $tablename)
    {
        if ($column === '*' || preg_match('/^[a-z0-9_]+$/i', $column)) {
            return "$tablename.$column";
        }

        return $column;
    }
}
