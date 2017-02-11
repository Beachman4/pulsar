<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
use Pulsar\Adapter\AdapterInterface;
use Pulsar\Model;
use Pulsar\Relation\BelongsToMany;
use Pulsar\Relation\Pivot;

class BelongsToManyTest extends PHPUnit_Framework_TestCase
{
    public static $adapter;

    public static function setUpBeforeClass()
    {
        self::$adapter = Mockery::mock(AdapterInterface::class);
        Model::setAdapter(self::$adapter);
    }

    public function testInitQuery()
    {
        $person = new Person(['id' => 10]);

        $relation = new BelongsToMany($person, 'person_id', 'group_person', 'Group', 'group_id');

        $this->assertEquals('group_person', $relation->getTablename());

        $query = $relation->getQuery();
        $this->assertInstanceOf(Group::class, $query->getModel());
        $joins = $query->getJoins();
        $this->assertCount(1, $joins);
        $this->assertInstanceOf(Pivot::class, $joins[0][0]);
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
            $this->assertInstanceOf(Group::class, $m);
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
        $this->assertInstanceOf(Pivot::class, $pivot);
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

        $this->assertInstanceOf(Group::class, $group);
        $this->assertEquals(true, $group->test);
        $this->assertTrue($group->persisted());

        // verify pivot
        $pivot = $group->pivot;
        $this->assertInstanceOf(Pivot::class, $pivot);
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
        $this->assertInstanceOf(Pivot::class, $pivot);
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

    public function testSync()
    {
        $person = new Person(['id' => 2]);

        $relation = new BelongsToMany($person, 'person_id', 'group_person', 'Group', 'group_id');

        self::$adapter = Mockery::mock(AdapterInterface::class);

        self::$adapter->shouldReceive('totalRecords')
                      ->andReturn(3);

        self::$adapter->shouldReceive('queryModels')
                      ->andReturnUsing(function ($query) {
                          $this->assertInstanceOf(Pivot::class, $query->getModel());
                          $this->assertEquals('group_person', $query->getModel()->getTablename());
                          $this->assertEquals(['group_id NOT IN (1,2,3)', 'person_id' => 2], $query->getWhere());

                          return [['id' => 3], ['id' => 4], ['id' => 5]];
                      });

        self::$adapter->shouldReceive('deleteModel')
                      ->andReturn(true)
                      ->times(3);

        Model::setAdapter(self::$adapter);

        $this->assertEquals($relation, $relation->sync([1, 2, 3]));
    }
}
