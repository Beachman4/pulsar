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
use Pulsar\Driver\DatabaseDriver;
use Pulsar\Query;
use Pimple\Container;

class DatabaseDriverTest extends PHPUnit_Framework_TestCase
{
    public static $app;

    public static function setUpBeforeClass()
    {
        self::$app = new Container();
    }

    public function testTablename()
    {
        $driver = new DatabaseDriver(self::$app);

        $this->assertEquals('TestModels', $driver->getTablename('TestModel'));

        $model = new TestModel();
        $this->assertEquals('TestModels', $driver->getTablename($model));
    }

    public function testSerializeValue()
    {
        $driver = new DatabaseDriver(self::$app);

        $this->assertEquals('string', $driver->serializeValue('string'));

        $arr = ['test' => true];
        $this->assertEquals('{"test":true}', $driver->serializeValue($arr));

        $obj = new stdClass();
        $obj->test = true;
        $this->assertEquals('{"test":true}', $driver->serializeValue($obj));
    }

    public function testUnserializeValue()
    {
        $driver = new DatabaseDriver(self::$app);

        $property = ['type' => Model::TYPE_STRING];
        $this->assertEquals('string', $driver->unserializeValue($property, 'string'));

        $property = ['type' => Model::TYPE_BOOLEAN];
        $this->assertTrue($driver->unserializeValue($property, true));
        $this->assertTrue($driver->unserializeValue($property, '1'));
        $this->assertFalse($driver->unserializeValue($property, false));

        $property = ['type' => Model::TYPE_NUMBER];
        $this->assertEquals(123, $driver->unserializeValue($property, 123));
        $this->assertEquals(123, $driver->unserializeValue($property, '123'));

        $property = ['type' => Model::TYPE_DATE];
        $this->assertEquals(123, $driver->unserializeValue($property, 123));
        $this->assertEquals(123, $driver->unserializeValue($property, '123'));
        $this->assertEquals(mktime(0, 0, 0, 8, 20, 2015), $driver->unserializeValue($property, 'Aug-20-2015'));

        $property = ['type' => Model::TYPE_ARRAY];
        $this->assertEquals(['test' => true], $driver->unserializeValue($property, '{"test":true}'));
        $this->assertEquals(['test' => true], $driver->unserializeValue($property, ['test' => true]));

        $property = ['type' => Model::TYPE_OBJECT];
        $expected = new stdClass();
        $expected->test = true;
        $this->assertEquals($expected, $driver->unserializeValue($property, '{"test":true}'));
        $this->assertEquals($expected, $driver->unserializeValue($property, $expected));
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

        $driver = new DatabaseDriver(self::$app);
        Person::setDriver($driver);

        $model = new Person();
        $this->assertTrue($driver->createModel($model, ['answer' => 42, 'array' => ['test' => true]]));
    }

    public function testCreateModelFail()
    {
        $this->setExpectedException('Pulsar\Exception\DriverException', 'An error occurred in the database driver when creating the Person');

        $db = Mockery::mock();
        $db->shouldReceive('insert')
           ->andThrow(new PDOException('error'));

        self::$app['db'] = $db;

        $driver = new DatabaseDriver(self::$app);
        Person::setDriver($driver);

        $model = new Person();
        $driver->createModel($model, []);
    }

    public function testGetCreatedID()
    {
        $db = Mockery::mock();
        $db->shouldReceive('getPDO->lastInsertId')
            ->andReturn('1');

        self::$app['db'] = $db;

        $driver = new DatabaseDriver(self::$app);

        $model = new Person();
        $this->assertEquals(1, $driver->getCreatedID($model, 'id'));
    }

    public function testGetCreatedIDFail()
    {
        $this->setExpectedException('Pulsar\Exception\DriverException', 'An error occurred in the database driver when getting the ID of the new Person');

        $db = Mockery::mock();
        $db->shouldReceive('getPDO->lastInsertId')
            ->andThrow(new PDOException('error'));

        self::$app['db'] = $db;

        $driver = new DatabaseDriver(self::$app);

        $model = new Person();
        $driver->getCreatedID($model, 'id');
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

        $driver = new DatabaseDriver(self::$app);
        Person::setDriver($driver);

        $model = Person::buildFromId(11);

        $this->assertTrue($driver->updateModel($model, []));

        $parameters = ['name' => 'John', 'array' => ['test' => true]];
        $this->assertTrue($driver->updateModel($model, $parameters));
    }

    public function testUpdateModelFail()
    {
        $this->setExpectedException('Pulsar\Exception\DriverException', 'An error occurred in the database driver when updating the Person');

        // update query mock
        $db = Mockery::mock();
        $db->shouldReceive('update')
           ->andThrow(new PDOException('error'));

        self::$app['db'] = $db;

        $driver = new DatabaseDriver(self::$app);
        Person::setDriver($driver);

        $model = Person::buildFromId(11);

        $driver->updateModel($model, ['name' => 'John']);
    }

    public function testDeleteModel()
    {
        $stmt = Mockery::mock('PDOStatement');
        $db = Mockery::mock();
        $db->shouldReceive('delete->where->execute')
           ->andReturn($stmt);

        self::$app['db'] = $db;

        $driver = new DatabaseDriver(self::$app);
        Person::setDriver($driver);

        $model = Person::buildFromId(10);
        $this->assertTrue($driver->deleteModel($model));
    }

    public function testDeleteModelFail()
    {
        $this->setExpectedException('Pulsar\Exception\DriverException', 'An error occurred in the database driver while deleting the Person');

        $stmt = Mockery::mock('PDOStatement');
        $db = Mockery::mock();
        $db->shouldReceive('delete->where->execute')
           ->andThrow(new PDOException('error'));

        self::$app['db'] = $db;

        $driver = new DatabaseDriver(self::$app);
        Person::setDriver($driver);

        $model = Person::buildFromId(10);
        $driver->deleteModel($model);
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

        $driver = new DatabaseDriver(self::$app);
        Person::setDriver($driver);

        $this->assertEquals(1, $driver->totalRecords($query));
    }

    public function testTotalRecordsFail()
    {
        $this->setExpectedException('Pulsar\Exception\DriverException', 'An error occurred in the database driver while getting the number of Person objects');

        $query = new Query('Person');

        // select query mock
        $db = Mockery::mock();
        $db->shouldReceive('select')
           ->andThrow(new PDOException('error'));

        self::$app['db'] = $db;

        $driver = new DatabaseDriver(self::$app);
        Person::setDriver($driver);

        $driver->totalRecords($query);
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

        $driver = new DatabaseDriver(self::$app);
        Person::setDriver($driver);

        $this->assertEquals([['name' => 'Bob']], $driver->queryModels($query));
    }

    public function testQueryModelsFail()
    {
        $this->setExpectedException('Pulsar\Exception\DriverException', 'An error occurred in the database driver while performing the Person query');

        $query = new Query('Person');

        // select query mock
        $db = Mockery::mock('JAQB\Query\SelectQuery[all]');
        $db->shouldReceive('all')
           ->andThrow(new PDOException('error'));

        self::$app['db'] = $db;

        $driver = new DatabaseDriver(self::$app);
        Person::setDriver($driver);

        $driver->queryModels($query);
    }
}
