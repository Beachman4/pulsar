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
use Pulsar\Relation\HasMany;

class HasManyTest extends PHPUnit_Framework_TestCase
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

        $relation = new HasMany($person, 'id', 'Car', 'person_id');

        $this->assertEquals(['person_id' => 10], $relation->getQuery()->getWhere());
    }

    public function testGetResults()
    {
        $person = new Person(['id' => 10]);

        $relation = new HasMany($person, 'id', 'Car', 'person_id');

        self::$adapter->shouldReceive('queryModels')
                      ->andReturn([['id' => 11], ['id' => 12]]);

        $result = $relation->getResults();

        $this->assertCount(2, $result);

        foreach ($result as $m) {
            $this->assertInstanceOf('Car', $m);
        }

        $this->assertEquals(11, $result[0]->id());
        $this->assertEquals(12, $result[1]->id());
    }

    public function testEmpty()
    {
        $person = new Person(['person_id' => null]);

        $relation = new HasMany($person, 'id', 'Car', 'person_id');

        $this->assertNull($relation->getResults());
    }

    public function testCreate()
    {
        $person = new Person(['id' => 100]);

        $relation = new HasMany($person, 'id', 'Car', 'person_id');

        self::$adapter->shouldReceive('createModel')
                      ->andReturn(true);

        self::$adapter->shouldReceive('getCreatedID')
                     ->andReturn(1);

        $car = $relation->create(['test' => true]);

        $this->assertInstanceOf('Car', $car);
        $this->assertEquals(100, $car->person_id);
        $this->assertEquals(true, $car->test);
        $this->assertTrue($car->persisted());
    }
}
