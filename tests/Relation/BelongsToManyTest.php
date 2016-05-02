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
use Pulsar\Relation\BelongsToMany;

class BelongsToManyTest extends PHPUnit_Framework_TestCase
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

        $relation = new BelongsToMany($person, 'person_id', 'group_person', 'Group', 'group_id');

        $this->assertEquals('group_person', $relation->getTablename());

        $query = $relation->getQuery();
        $this->assertInstanceOf('Group', $query->getModel());
        $joins = $query->getJoins();
        $this->assertCount(1, $joins);
        $this->assertInstanceOf('Pulsar\Relation\Pivot', $joins[0][0]);
        $this->assertEquals('group_person', $joins[0][0]->getTablename());
        $this->assertEquals('group_id', $joins[0][1]);
        $this->assertEquals('id', $joins[0][2]);
        $this->assertEquals(['person_id' => 10], $query->getWhere());
    }

    public function testGetResults()
    {
        $person = new Person(['id' => 10]);

        $relation = new BelongsToMany($person, 'person_id', 'group_person', 'Group', 'group_id');

        self::$adapter->shouldReceive('queryModels')
                      ->andReturn([['id' => 11], ['id' => 12]]);

        $result = $relation->getResults();

        $this->assertCount(2, $result);

        foreach ($result as $m) {
            $this->assertInstanceOf('Group', $m);
        }

        $this->assertEquals(11, $result[0]->id());
        $this->assertEquals(12, $result[1]->id());
    }

    public function testEmpty()
    {
        $person = new Person();

        $relation = new BelongsToMany($person, 'person_id', 'group_person', 'Group', 'group_id');

        $this->assertNull($relation->getResults());
    }

    public function testSave()
    {
        $person = new Person(['id' => 2]);

        $relation = new BelongsToMany($person, 'person_id', 'group_person', 'Group', 'group_id');

        self::$adapter->shouldReceive('createModel')
                      ->andReturn(true);

        self::$adapter->shouldReceive('getCreatedID')
                      ->andReturn(1);

        $group = new Group(['test' => true]);

        $this->assertEquals($group, $relation->save($group));

        $this->assertEquals(true, $group->test);
        $this->assertTrue($group->persisted());

        // verify pivot
        $pivot = $group->pivot;
        $this->assertInstanceOf('Pulsar\Relation\Pivot', $pivot);
        $this->assertEquals('group_person', $pivot->getTablename());
        $this->assertEquals(1, $pivot->group_id);
        $this->assertEquals(2, $pivot->person_id);
        $this->assertTrue($pivot->persisted());
    }

    public function testCreate()
    {
        $person = new Person(['id' => 2]);

        $relation = new BelongsToMany($person, 'person_id', 'group_person', 'Group', 'group_id');

        self::$adapter->shouldReceive('createModel')
                      ->andReturn(true);

        self::$adapter->shouldReceive('getCreatedID')
                      ->andReturn(1);

        $group = $relation->create(['test' => true]);

        $this->assertInstanceOf('Group', $group);
        $this->assertEquals(true, $group->test);
        $this->assertTrue($group->persisted());

        // verify pivot
        $pivot = $group->pivot;
        $this->assertInstanceOf('Pulsar\Relation\Pivot', $pivot);
        $this->assertEquals('group_person', $pivot->getTablename());
        $this->assertEquals(1, $pivot->group_id);
        $this->assertEquals(2, $pivot->person_id);
        $this->assertTrue($pivot->persisted());
    }

    public function testAttach()
    {
        $person = new Person(['id' => 2]);

        $relation = new BelongsToMany($person, 'person_id', 'group_person', 'Group', 'group_id');

        $group = new Group(['id' => 3]);

        $this->assertEquals($relation, $relation->attach($group));

        $pivot = $group->pivot;
        $this->assertInstanceOf('Pulsar\Relation\Pivot', $pivot);
        $this->assertEquals('group_person', $pivot->getTablename());
        $this->assertEquals(2, $pivot->person_id);
        $this->assertEquals(3, $pivot->group_id);
        $this->assertTrue($pivot->persisted());
    }

    public function testDetach()
    {
        $person = new Person(['id' => 2]);

        $relation = new BelongsToMany($person, 'person_id', 'group_person', 'Group', 'group_id');

        $group = new Group(['person_id' => 2]);
        $group->pivot = Mockery::mock();
        $group->pivot->shouldReceive('delete')->once();

        $this->assertEquals($relation, $relation->detach($group));
    }
}
