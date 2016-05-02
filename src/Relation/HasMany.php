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

class HasMany extends Relation
{
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
}
