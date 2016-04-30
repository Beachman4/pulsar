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

    public function create(array $values = [])
    {
        $class = $this->foreignModel;
        $model = new $class($values);
        $model->save();

        // create pivot relation
        $pivot = new Pivot();
        $pivot->setTablename($this->tablename);

        $ids = $model->ids();
        foreach ($ids as $property => $id) {
            $pivot->{$this->foreignKey} = $id;
        }

        $ids = $this->localModel->ids();
        foreach ($ids as $property => $id) {
            $pivot->{$this->localKey} = $id;
        }

        $pivot->save();
        $model->pivot = $pivot;

        return $model;
    }
}
