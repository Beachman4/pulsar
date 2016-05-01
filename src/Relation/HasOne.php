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

use Pulsar\Query;

class HasOne extends Relation
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

    public function create(array $values = [])
    {
        $class = $this->foreignModel;
        $model = new $class();
        $model->{$this->foreignKey} = $this->localModel->{$this->localKey};
        $model->create($values);

        return $model;
    }
}
