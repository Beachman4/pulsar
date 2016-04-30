<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
use Pulsar\Relation\Relation;

class RelationTest extends PHPUnit_Framework_TestCase
{
    public function testConstruct()
    {
        $model = Mockery::mock('Pulsar\Model');
        $relation = new DistantRelation($model, 'user_id', 'TestModel', 'id');

        $this->assertTrue($relation->initQuery);
    }

    public function testGetLocalModel()
    {
        $model = Mockery::mock('Pulsar\Model');
        $relation = new DistantRelation($model, 'user_id', 'TestModel', 'id');

        $this->assertEquals($model, $relation->getLocalModel());
    }

    public function testGetLocalKey()
    {
        $model = Mockery::mock('Pulsar\Model');
        $relation = new DistantRelation($model, 'user_id', 'TestModel', 'id');

        $this->assertEquals('user_id', $relation->getLocalKey());
    }

    public function testGetForeignModel()
    {
        $model = Mockery::mock('Pulsar\Model');
        $relation = new DistantRelation($model, 'user_id', 'TestModel', 'id');

        $this->assertEquals('TestModel', $relation->getForeignModel());
    }

    public function testGetForeignKey()
    {
        $model = Mockery::mock('Pulsar\Model');
        $relation = new DistantRelation($model, 'user_id', 'TestModel', 'id');

        $this->assertEquals('id', $relation->getForeignKey());
    }

    public function testGetQuery()
    {
        $model = Mockery::mock('Pulsar\Model');
        $relation = new DistantRelation($model, 'user_id', 'TestModel', 'id');

        $query = $relation->getQuery();
        $this->assertInstanceOf('Pulsar\Query', $query);
    }

    public function testCallOnQuery()
    {
        $model = Mockery::mock('Pulsar\Model');
        $relation = new DistantRelation($model, 'user_id', 'TestModel', 'id');

        $relation->where(['name' => 'Bob']);

        $this->assertEquals(['name' => 'Bob'], $relation->getQuery()->getWhere());
    }
}

class DistantRelation extends Relation
{
    public $initQuery;

    protected function initQuery()
    {
        $this->initQuery = true;
    }

    public function getResults()
    {
        // do nothing
    }

    public function create(array $values = [])
    {
        // do nothing
    }
}
