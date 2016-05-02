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

class BelongsTo extends Relation
{
    protected function initQuery()
    {
        $value = $this->localModel->{$this->localKey};

        if ($value === null) {
            $this->empty = true;
        }

        $this->query->where($this->foreignKey, $value)
                    ->limit(1);
    }

    public function getResults()
    {
        if ($this->empty) {
            return;
        }

        return $this->query->first();
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
     * Attaches this model to an owning model.
     *
     * @param Model $model owning model
     *
     * @return self
     */
    public function attach(Model $model)
    {
        $this->localModel->{$this->localKey} = $model->{$this->foreignKey};
        $this->localModel->save();

        return $this;
    }

    /**
     * Detaches this model from the owning model.
     *
     * @return self
     */
    public function detach()
    {
        $this->localModel->{$this->localKey} = null;
        $this->localModel->save();

        return $this;
    }
}
