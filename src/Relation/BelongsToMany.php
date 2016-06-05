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

use ICanBoogie\Inflector;
use Pulsar\Model;
use Pulsar\Query;

class BelongsToMany extends Relation
{
    /**
     * @var string
     */
    protected $tablename;

    /**
     * @param Model  $localModel
     * @param string $localKey     identifying key on local model
     * @param string $tablename    pivot table name
     * @param string $foreignModel foreign model class
     * @param string $foreignKey   identifying key on foreign model
     */
    public function __construct(Model $localModel, $localKey, $tablename, $foreignModel, $foreignKey)
    {
        // the default local key would look like `user_id`
        // for a model named User
        if (!$localKey) {
            $inflector = Inflector::get();
            $localKey = strtolower($inflector->underscore($foreignModel::modelName())).'_id';
        }

        if (!$foreignKey) {
            $foreignKey = Model::DEFAULT_ID_PROPERTY;
        }

        // the default pivot table name looks like
        // RoleUser for models named Role and User.
        // the tablename is built from the model names
        // in alphabetic order.
        if (!$tablename) {
            $names = [
                $localModel::modelName(),
                $foreignModel::modelName(),
            ];
            sort($names);
            $tablename = implode($names);
        }

        $this->tablename = $tablename;

        parent::__construct($localModel, $localKey, $foreignModel, $foreignKey);
    }

    protected function initQuery()
    {
        $pivot = new Pivot();
        $pivot->setTablename($this->tablename);

        $ids = $this->localModel->ids();
        foreach ($ids as $idProperty => $id) {
            if ($id === null) {
                $this->empty = true;
            }

            $this->query->where($this->localKey, $id);
            $this->query->join($pivot, $this->foreignKey, $idProperty);
        }
    }

    public function getResults()
    {
        if ($this->empty) {
            return;
        }

        return $this->query->execute();
    }

    /**
     * Gets the pivot tablename.
     *
     * @return string
     */
    public function getTablename()
    {
        return $this->tablename;
    }

    public function save(Model $model)
    {
        $model->save();
        $this->attach($model);

        return $model;
    }

    public function create(array $values = [])
    {
        $class = $this->foreignModel;
        $model = new $class();
        $model->create($values);

        $this->attach($model);

        return $model;
    }

    /**
     * Attaches a model to the relationship by creating
     * a pivot model.
     *
     * @param Model $model
     *
     * @return self
     */
    public function attach(Model $model)
    {
        // create pivot relation
        $pivot = new Pivot();
        $pivot->setTablename($this->tablename);

        // build the local side
        $ids = $this->localModel->ids();
        foreach ($ids as $property => $id) {
            $pivot->{$this->localKey} = $id;
        }

        // build the foreign side
        $ids = $model->ids();
        foreach ($ids as $property => $id) {
            $pivot->{$this->foreignKey} = $id;
        }

        $pivot->save();
        $model->pivot = $pivot;

        return $this;
    }

    /**
     * Detaches a model from the relationship by deleting
     * the pivot model.
     *
     * @param Model $model
     *
     * @return self
     */
    public function detach(Model $model)
    {
        $model->pivot->delete();
        unset($model->pivot);

        return $this;
    }

    /**
     * Removes any relationships that are not included
     * in the list of IDs.
     * 
     * @param array $ids
     *
     * @return self
     */
    public function sync(array $ids)
    {
        $pivot = new Pivot();
        $pivot->setTablename($this->tablename);
        $query = new Query($pivot);

        $localIds = $this->localModel->ids();
        foreach ($localIds as $property => $id) {
            $query->where($this->localKey, $id);
        }

        if (count($ids) > 0) {
            $in = implode(',', $ids);
            $query->where("{$this->foreignKey} NOT IN ($in)");
        }

        $query->delete();

        return $this;
    }
}
