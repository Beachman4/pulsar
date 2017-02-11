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
use Pulsar\Iterator;
use Pulsar\Query;

class QueryTest extends PHPUnit_Framework_TestCase
{
    public function testGetModel()
    {
        $model = new TestModel();
        $query = new Query($model);
        $this->assertEquals($model, $query->getModel());
    }

    public function testLimit()
    {
        $query = new Query();

        $this->assertEquals(100, $query->getLimit());
        $this->assertEquals($query, $query->limit(500));
        $this->assertEquals(500, $query->getLimit());
    }

    public function testStart()
    {
        $query = new Query();

        $this->assertEquals(0, $query->getStart());
        $this->assertEquals($query, $query->start(10));
        $this->assertEquals(10, $query->getStart());
    }

    public function testSort()
    {
        $query = new Query();

        $this->assertEquals([], $query->getSort());
        $this->assertEquals($query, $query->sort('name asc, id DESC,invalid,wrong direction'));
        $this->assertEquals([['name', 'asc'], ['id', 'desc']], $query->getSort());
    }

    public function testWhere()
    {
        $query = new Query();

        $this->assertEquals([], $query->getWhere());
        $this->assertEquals($query, $query->where(['test' => true]));
        $this->assertEquals(['test' => true], $query->getWhere());

        $query->where('test', false);
        $this->assertEquals(['test' => false], $query->getWhere());

        $query->where('some condition');
        $this->assertEquals(['test' => false, 'some condition'], $query->getWhere());

        $query->where('balance', 100, '>=');
        $this->assertEquals(['test' => false, 'some condition', ['balance', 100, '>=']], $query->getWhere());
    }

    public function testJoin()
    {
        $query = new Query();

        $this->assertEquals([], $query->getJoins());

        $this->assertEquals($query, $query->join('Person', 'author', 'id'));
        $this->assertEquals([['Person', 'author', 'id']], $query->getJoins());
    }

    public function testExecute()
    {
        $query = new Query(new Person());

        $adapter = Mockery::mock(AdapterInterface::class);

        $data = [
            [
                'id' => 100,
                'name' => 'Sherlock',
                'email' => 'sherlock@example.com',
            ],
            [
                'id' => 102,
                'name' => 'John',
                'email' => 'john@example.com',
            ],
        ];

        $adapter->shouldReceive('queryModels')
                ->withArgs([$query])
                ->andReturn($data);

        Person::setAdapter($adapter);

        $result = $query->execute();

        $this->assertCount(2, $result);
        foreach ($result as $model) {
            $this->assertInstanceOf(Person::class, $model);
        }

        $this->assertEquals(100, $result[0]->id());
        $this->assertEquals(102, $result[1]->id());

        $this->assertEquals('Sherlock', $result[0]->name);
        $this->assertEquals('John', $result[1]->name);
    }

    public function testExecuteMultipleIds()
    {
        $query = new Query(new TestModel2());

        $adapter = Mockery::mock(AdapterInterface::class);

        $data = [
            [
                'id' => 100,
                'id2' => 101,
            ],
            [
                'id' => 102,
                'id2' => 103,
            ],
        ];

        $adapter->shouldReceive('queryModels')
                ->withArgs([$query])
                ->andReturn($data);

        TestModel2::setAdapter($adapter);

        $result = $query->execute();

        $this->assertCount(2, $result);
        foreach ($result as $model) {
            $this->assertInstanceOf(TestModel2::class, $model);
        }

        $this->assertEquals('100,101', $result[0]->id());
        $this->assertEquals('102,103', $result[1]->id());
    }

    public function testAll()
    {
        $query = new Query(new TestModel());

        $all = $query->all();
        $this->assertInstanceOf(Iterator::class, $all);
    }

    public function testFirst()
    {
        $query = new Query(new Person());

        $adapter = Mockery::mock(AdapterInterface::class);

        $data = [
            [
                'id' => 100,
                'name' => 'Sherlock',
                'email' => 'sherlock@example.com',
            ],
        ];

        $adapter->shouldReceive('queryModels')
                ->withArgs([$query])
                ->andReturn($data);

        Person::setAdapter($adapter);

        $result = $query->first();

        $this->assertInstanceOf(Person::class, $result);
        $this->assertEquals(100, $result->id());
        $this->assertEquals('Sherlock', $result->name);
    }

    public function testFirstLimit()
    {
        $query = new Query(new Person());

        $adapter = Mockery::mock(AdapterInterface::class);

        $data = [
            [
                'id' => 100,
                'name' => 'Sherlock',
                'email' => 'sherlock@example.com',
            ],
            [
                'id' => 102,
                'name' => 'John',
                'email' => 'john@example.com',
            ],
        ];

        $adapter->shouldReceive('queryModels')
                ->withArgs([$query])
                ->andReturn($data);

        Person::setAdapter($adapter);

        $result = $query->first(2);

        $this->assertEquals(2, $query->getLimit());

        $this->assertCount(2, $result);
        foreach ($result as $model) {
            $this->assertInstanceOf(Person::class, $model);
        }

        $this->assertEquals(100, $result[0]->id());
        $this->assertEquals(102, $result[1]->id());

        $this->assertEquals('Sherlock', $result[0]->name);
        $this->assertEquals('John', $result[1]->name);
    }

    public function testSet()
    {
        $model = Mockery::mock();
        $model->shouldReceive('set')
              ->withArgs([['test' => true]])
              ->once();

        $model2 = Mockery::mock();
        $model2->shouldReceive('set')
               ->withArgs([['test' => true]])
               ->once();

        $query = Mockery::mock('Pulsar\Query[all]', [new TestModel()]);
        $query->shouldReceive('all')
              ->andReturn([$model, $model2]);

        $this->assertEquals(2, $query->set(['test' => true]));
    }

    public function testDelete()
    {
        $model = Mockery::mock();
        $model->shouldReceive('delete')->once();

        $model2 = Mockery::mock();
        $model2->shouldReceive('delete')->once();

        $query = Mockery::mock('Pulsar\Query[all]', [new TestModel()]);
        $query->shouldReceive('all')
              ->andReturn([$model, $model2]);

        $this->assertEquals(2, $query->delete());
    }
}
