<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
use Carbon\Carbon;
use Pulsar\Adapter\DatabaseAdapter;
use Pulsar\Query;
use Pimple\Container;

class DatabaseAdapterTest extends PHPUnit_Framework_TestCase
{
    public static $app;

    public static function setUpBeforeClass()
    {
        self::$app = new Container();
    }

    public function testSerializeValue()
    {
        $adapter = new DatabaseAdapter(self::$app);

        $this->assertEquals('string', $adapter->serializeValue('string'));

        $arr = ['test' => true];
        $this->assertEquals('{"test":true}', $adapter->serializeValue($arr));

        $obj = new stdClass();
        $obj->test = true;
        $this->assertEquals('{"test":true}', $adapter->serializeValue($obj));

        $this->assertEquals(time(), $adapter->serializeValue(Carbon::now()));

        $this->assertEquals('2016-01-20 00:00:00', $adapter->serializeValue(Carbon::create(2016, 1, 20, 0, 0, 0), 'TestModel2', 'created_at'));
    }

    public function testCreateModel()
    {
        $db = Mockery::mock();

        // insert query mock
        $stmt = Mockery::mock('PDOStatement');
        $execute = Mockery::mock();
        $execute->shouldReceive('execute')
                ->andReturn($stmt);
        $into = Mockery::mock();
        $into->shouldReceive('into')
             ->withArgs(['People'])
             ->andReturn($execute);
        $db->shouldReceive('insert')
           ->withArgs([['answer' => 42, 'array' => '{"test":true}']])
           ->andReturn($into)
           ->once();

        self::$app['db'] = $db;

        $adapter = new DatabaseAdapter(self::$app);
        Person::setAdapter($adapter);

        $model = new Person();
        $this->assertTrue($adapter->createModel($model, ['answer' => 42, 'array' => ['test' => true]]));
    }

    public function testCreateModelFail()
    {
        $this->setExpectedException('Pulsar\Exception\AdapterException', 'An error occurred in the database adapter when creating the Person');

        $db = Mockery::mock();
        $db->shouldReceive('insert')
           ->andThrow(new PDOException('error'));

        self::$app['db'] = $db;

        $adapter = new DatabaseAdapter(self::$app);
        Person::setAdapter($adapter);

        $model = new Person();
        $adapter->createModel($model, []);
    }

    public function testGetCreatedID()
    {
        $db = Mockery::mock();
        $db->shouldReceive('getPDO->lastInsertId')
            ->andReturn('1');

        self::$app['db'] = $db;

        $adapter = new DatabaseAdapter(self::$app);

        $model = new Person();
        $this->assertEquals(1, $adapter->getCreatedID($model, 'id'));
    }

    public function testGetCreatedIDFail()
    {
        $this->setExpectedException('Pulsar\Exception\AdapterException', 'An error occurred in the database adapter when getting the ID of the new Person');

        $db = Mockery::mock();
        $db->shouldReceive('getPDO->lastInsertId')
            ->andThrow(new PDOException('error'));

        self::$app['db'] = $db;

        $adapter = new DatabaseAdapter(self::$app);

        $model = new Person();
        $adapter->getCreatedID($model, 'id');
    }

    public function testUpdateModel()
    {
        // update query mock
        $stmt = Mockery::mock('PDOStatement');
        $execute = Mockery::mock();
        $execute->shouldReceive('execute')->andReturn($stmt);
        $where = Mockery::mock();
        $where->shouldReceive('where')
              ->withArgs([['id' => 11]])
              ->andReturn($execute);
        $values = Mockery::mock();
        $values->shouldReceive('values')
               ->withArgs([['name' => 'John', 'array' => '{"test":true}']])
               ->andReturn($where);
        $db = Mockery::mock();
        $db->shouldReceive('update')
           ->withArgs(['People'])
           ->andReturn($values);

        self::$app['db'] = $db;

        $adapter = new DatabaseAdapter(self::$app);
        Person::setAdapter($adapter);

        $model = Person::buildFromId(11);

        $this->assertTrue($adapter->updateModel($model, []));

        $parameters = ['name' => 'John', 'array' => ['test' => true]];
        $this->assertTrue($adapter->updateModel($model, $parameters));
    }

    public function testUpdateModelFail()
    {
        $this->setExpectedException('Pulsar\Exception\AdapterException', 'An error occurred in the database adapter when updating the Person');

        // update query mock
        $db = Mockery::mock();
        $db->shouldReceive('update')
           ->andThrow(new PDOException('error'));

        self::$app['db'] = $db;

        $adapter = new DatabaseAdapter(self::$app);
        Person::setAdapter($adapter);

        $model = Person::buildFromId(11);

        $adapter->updateModel($model, ['name' => 'John']);
    }

    public function testDeleteModel()
    {
        $stmt = Mockery::mock('PDOStatement');
        $db = Mockery::mock();
        $db->shouldReceive('delete->where->execute')
           ->andReturn($stmt);

        self::$app['db'] = $db;

        $adapter = new DatabaseAdapter(self::$app);
        Person::setAdapter($adapter);

        $model = Person::buildFromId(10);
        $this->assertTrue($adapter->deleteModel($model));
    }

    public function testDeleteModelFail()
    {
        $this->setExpectedException('Pulsar\Exception\AdapterException', 'An error occurred in the database adapter while deleting the Person');

        $stmt = Mockery::mock('PDOStatement');
        $db = Mockery::mock();
        $db->shouldReceive('delete->where->execute')
           ->andThrow(new PDOException('error'));

        self::$app['db'] = $db;

        $adapter = new DatabaseAdapter(self::$app);
        Person::setAdapter($adapter);

        $model = Person::buildFromId(10);
        $adapter->deleteModel($model);
    }

    public function testTotalRecords()
    {
        $query = new Query('Person');

        // select query mock
        $scalar = Mockery::mock();
        $scalar->shouldReceive('scalar')
               ->andReturn(1);
        $where = Mockery::mock();
        $where->shouldReceive('where')
              ->withArgs([[]])
              ->andReturn($scalar);
        $from = Mockery::mock();
        $from->shouldReceive('from')
             ->withArgs(['People'])
             ->andReturn($where);
        $db = Mockery::mock();
        $db->shouldReceive('select')
           ->withArgs(['count(*)'])
           ->andReturn($from);

        self::$app['db'] = $db;

        $adapter = new DatabaseAdapter(self::$app);
        Person::setAdapter($adapter);

        $this->assertEquals(1, $adapter->totalRecords($query));
    }

    public function testTotalRecordsFail()
    {
        $this->setExpectedException('Pulsar\Exception\AdapterException', 'An error occurred in the database adapter while getting the number of Person objects');

        $query = new Query('Person');

        // select query mock
        $db = Mockery::mock();
        $db->shouldReceive('select')
           ->andThrow(new PDOException('error'));

        self::$app['db'] = $db;

        $adapter = new DatabaseAdapter(self::$app);
        Person::setAdapter($adapter);

        $adapter->totalRecords($query);
    }

    public function testQueryModels()
    {
        $query = new Query('Person');
        $query->where('id', 50, '>')
              ->where(['city' => 'Austin'])
              ->where('RAW SQL')
              ->where('People.alreadyDotted', true)
              ->join('Group', 'group', 'id')
              ->sort('name asc')
              ->limit(5)
              ->start(10);

        // select query mock
        $all = Mockery::mock();
        $all->shouldReceive('all')
            ->andReturn([['name' => 'Bob']]);
        $all->shouldReceive('join')
             ->withArgs(['Groups', 'People.group=Groups.id'])
             ->once();
        $orderBy = Mockery::mock();
        $orderBy->shouldReceive('orderBy')
                ->withArgs([[['People.name', 'asc']]])
                ->andReturn($all);
        $limit = Mockery::mock();
        $limit->shouldReceive('limit')
             ->withArgs([5, 10])
             ->andReturn($orderBy);
        $where = Mockery::mock();
        $where->shouldReceive('where')
              ->withArgs([[['People.id', 50, '>'], 'People.city' => 'Austin', 'RAW SQL', 'People.alreadyDotted' => true]])
              ->andReturn($limit);
        $from = Mockery::mock();
        $from->shouldReceive('from')
             ->withArgs(['People'])
             ->andReturn($where);
        $db = Mockery::mock();
        $db->shouldReceive('select')
           ->withArgs(['People.*'])
           ->andReturn($from);

        self::$app['db'] = $db;

        $adapter = new DatabaseAdapter(self::$app);
        Person::setAdapter($adapter);

        $this->assertEquals([['name' => 'Bob']], $adapter->queryModels($query));
    }

    public function testQueryModelsFail()
    {
        $this->setExpectedException('Pulsar\Exception\AdapterException', 'An error occurred in the database adapter while performing the Person query');

        $query = new Query('Person');

        // select query mock
        $db = Mockery::mock('JAQB\Query\SelectQuery[all]');
        $db->shouldReceive('all')
           ->andThrow(new PDOException('error'));

        self::$app['db'] = $db;

        $adapter = new DatabaseAdapter(self::$app);
        Person::setAdapter($adapter);

        $adapter->queryModels($query);
    }
}
