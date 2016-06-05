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

class HasMany extends Relation
{
    /**
     * @param Model  $localModel
     * @param string $localKey     identifying key on local model
     * @param string $foreignModel foreign model class
     * @param string $foreignKey   identifying key on foreign model
     */
    public function __construct(Model $localModel, $localKey, $foreignModel, $foreignKey)
    {
        // the default foreign key would look like
        // `user_id` for a model named User
        if (!$foreignKey) {
            $inflector = Inflector::get();
            $foreignKey = strtolower($inflector->underscore($localModel::modelName())).'_id';
        }

        if (!$localKey) {
            $localKey = Model::DEFAULT_ID_PROPERTY;
        }

        parent::__construct($localModel, $localKey, $foreignModel, $foreignKey);
    }

    protected function initQuery()
    {
        $localKey = $this->localKey;
        $value = $this->localModel->$localKey;

        if ($value === null) {
            $this->empty = true;
        }

        $this->query->where($this->foreignKey, $value);
    }

    public function getResults()
    {
        if ($this->empty) {
            return;
        }

        return $this->query->execute();
    }

    public function save(Model $model)
    {
        $model->{$this->foreignKey} = $this->localModel->{$this->localKey};
        $model->save();

        return $model;
    }

    public function create(array $values = [])
    {
        $class = $this->foreignModel;
        $model = new $class();
        $model->{$this->foreignKey} = $this->localModel->{$this->localKey};
        $model->create($values);

        return $model;
    }

    /**
     * Attaches a child model from this model.
     *
     * @param Model $model child model
     *
     * @return self
     */
    public function attach(Model $model)
    {
        $model->{$this->foreignKey} = $this->localModel->{$this->localKey};
        $model->save();

        return $this;
    }

    /**
     * Detaches a child model from this model.
     *
     * @param Model $model child model
     *
     * @return self
     */
    public function detach(Model $model)
    {
        $model->{$this->foreignKey} = null;
        $model->save();

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
        $class = $this->foreignModel;
        $model = new $class();
        $query = $model::query();

        if (count($ids) > 0) {
            $in = implode(',', $ids);
            $query->where("{$this->foreignKey} NOT IN ($in)");
        }

        $query->delete();

        return $this;
    }
}
