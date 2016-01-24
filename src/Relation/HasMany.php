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

class HasMany extends Relation
{
    protected function initQuery()
    {
        $localKey = $this->localKey;
        $value = $this->relation->$localKey;

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
}
