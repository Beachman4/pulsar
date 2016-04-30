<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
use Pulsar\Model;
use Pulsar\Relation\HasOne;

class HasOneTest extends PHPUnit_Framework_TestCase
{
    public static $adapter;

    public static function setUpBeforeClass()
    {
        self::$adapter = Mockery::mock('Pulsar\Adapter\AdapterInterface');
        Model::setAdapter(self::$adapter);
    }

    public function testInitQuery()
    {
        $person = new Person(['id' => 10]);

        $relation = new HasOne($person, 'id', 'Balance', 'person_id');

        $query = $relation->getQuery();
        $this->assertInstanceOf('Balance', $query->getModel());
        $this->assertEquals(['person_id' => 10], $query->getWhere());
        $this->assertEquals(1, $query->getLimit());
    }

    public function testGetResults()
    {
        $person = new Person(['id' => 10]);

        $relation = new HasOne($person, 'id', 'Balance', 'person_id');

        self::$adapter->shouldReceive('queryModels')
                      ->andReturn([['id' => 11]]);

        $result = $relation->getResults();
        $this->assertInstanceOf('Balance', $result);
        $this->assertEquals(11, $result->id());
    }

    public function testEmpty()
    {
        $person = new Person(['id' => null]);

        $relation = new HasOne($person, 'id', 'Balance', 'person_id');

        $this->assertNull($relation->getResults());
    }

    public function testCreate()
    {
        $person = new Person(['id' => 100]);

        $relation = new HasOne($person, 'id', 'Balance', 'person_id');

        self::$adapter->shouldReceive('createModel')
                      ->andReturn(true);

        self::$adapter->shouldReceive('getCreatedID')
                     ->andReturn(1);

        $balance = $relation->create(['test' => true]);

        $this->assertInstanceOf('Balance', $balance);
        $this->assertEquals(100, $balance->person_id);
        $this->assertEquals(true, $balance->test);
        $this->assertTrue($balance->persisted());
    }
}
