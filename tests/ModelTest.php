<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
use Infuse\Locale;
use Pulsar\Model;
use Pulsar\ModelEvent;

require_once 'test_models.php';

class ModelTest extends PHPUnit_Framework_TestCase
{
    protected function tearDown()
    {
        // discard the cached dispatcher to
        // remove any event listeners
        TestModel::getDispatcher(true);

        Model::clearDriver();
        Model::clearLocale();
    }

    public function testInjectContainer()
    {
        $c = new \Pimple\Container();
        Model::inject($c);

        $model = new TestModel();
        $this->assertEquals($c, $model->getApp());
    }

    public function testDriverMissing()
    {
        $this->setExpectedException('Pulsar\Exception\DriverMissingException');
        Model::clearDriver();
        Model::getDriver();
    }

    public function testDriver()
    {
        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');
        Model::setDriver($driver);

        $this->assertEquals($driver, TestModel::getDriver());

        // setting the driver for a single model sets
        // the driver for all models
        $this->assertEquals($driver, TestModel2::getDriver());
    }

    public function testModelName()
    {
        $this->assertEquals('TestModel', TestModel::modelName());

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');
        $driver->shouldReceive('getTablename')
               ->withArgs(['TestModel'])
               ->andReturn('TestModels');
        Model::setDriver($driver);
    }

    public function testGetProperties()
    {
        $expected = [
            'id' => [
                'type' => Model::TYPE_NUMBER,
                'mutable' => Model::IMMUTABLE,
            ],
            'relation' => [
                'type' => Model::TYPE_NUMBER,
                'mutable' => Model::MUTABLE,
            ],
            'answer' => [
                'type' => Model::TYPE_STRING,
                'mutable' => Model::MUTABLE,
            ],
            'test_hook' => [
                'type' => Model::TYPE_STRING,
                'mutable' => Model::MUTABLE,
            ],
            'mutator' => [
                'type' => Model::TYPE_STRING,
                'mutable' => Model::MUTABLE,
            ],
            'accessor' => [
                'type' => Model::TYPE_STRING,
                'mutable' => Model::MUTABLE,
            ],
            'test_model2_id' => [
                'type' => Model::TYPE_STRING,
                'mutable' => Model::MUTABLE,
            ],
        ];

        $this->assertEquals($expected, TestModel::getProperties());
    }

    public function testPropertiesIdOverwrite()
    {
        $expected = [
            'type' => Model::TYPE_STRING,
            'mutable' => Model::MUTABLE,
        ];

        $this->assertEquals($expected, Person::getProperty('id'));
    }

    public function testGetProperty()
    {
        $expected = [
            'type' => Model::TYPE_NUMBER,
            'mutable' => Model::IMMUTABLE,
        ];
        $this->assertEquals($expected, TestModel::getProperty('id'));

        $expected = [
            'type' => Model::TYPE_NUMBER,
            'mutable' => Model::MUTABLE,
        ];
        $this->assertEquals($expected, TestModel::getProperty('relation'));
    }

    public function testPropertiesAutoTimestamps()
    {
        $expected = [
            'id' => [
                'type' => Model::TYPE_NUMBER,
                'mutable' => Model::MUTABLE,
            ],
            'id2' => [
                'type' => Model::TYPE_NUMBER,
                'mutable' => Model::MUTABLE,
            ],
            'default' => [
                'type' => Model::TYPE_STRING,
                'mutable' => Model::MUTABLE,
            ],
            'validate' => [
                'type' => Model::TYPE_STRING,
                'mutable' => Model::MUTABLE,
            ],
            'validate2' => [
                'type' => Model::TYPE_STRING,
                'mutable' => Model::MUTABLE,
            ],
            'unique' => [
                'type' => Model::TYPE_STRING,
                'mutable' => Model::MUTABLE,
            ],
            'required' => [
                'type' => Model::TYPE_NUMBER,
                'mutable' => Model::MUTABLE,
            ],
            'hidden' => [
                'type' => Model::TYPE_BOOLEAN,
                'mutable' => Model::MUTABLE,
            ],
            'person_id' => [
                'type' => Model::TYPE_NUMBER,
                'mutable' => Model::MUTABLE,
            ],
            'array' => [
                'type' => Model::TYPE_ARRAY,
                'mutable' => Model::MUTABLE,
            ],
            'object' => [
                'type' => Model::TYPE_OBJECT,
                'mutable' => Model::MUTABLE,
            ],
            'mutable_create_only' => [
                'type' => Model::TYPE_STRING,
                'mutable' => Model::MUTABLE_CREATE_ONLY,
            ],
            'created_at' => [
                'type' => Model::TYPE_DATE,
                'mutable' => Model::MUTABLE,
            ],
            'updated_at' => [
                'type' => Model::TYPE_DATE,
                'mutable' => Model::MUTABLE,
            ],
        ];

        $model = new TestModel2(); // forces initialize()
        $this->assertEquals($expected, TestModel2::getProperties());
    }

    public function testGetIdProperties()
    {
        $this->assertEquals(['id'], TestModel::getIdProperties());

        $this->assertEquals(['id', 'id2'], TestModel2::getIdProperties());
    }

    public function testBuildFromId()
    {
        $model = TestModel::buildFromId(100);
        $this->assertInstanceOf('TestModel', $model);
        $this->assertEquals(100, $model->id());

        $model = TestModel2::buildFromId([101, 102]);
        $this->assertEquals('101,102', $model->id());
    }

    public function testGetMutator()
    {
        $this->assertFalse(TestModel::getMutator('id'));
        $this->assertFalse(TestModel2::getMutator('id'));
        $this->assertEquals('setMutatorValue', TestModel::getMutator('mutator'));
    }

    public function testGetAccessor()
    {
        $this->assertFalse(TestModel::getAccessor('id'));
        $this->assertFalse(TestModel2::getAccessor('id'));
        $this->assertEquals('getAccessorValue', TestModel::getAccessor('accessor'));
    }

    public function testGetPropertyTitle()
    {
        $this->assertEquals('Answer', TestModel::getPropertyTitle('answer'));

        $locale = new Locale();
        $locale->setLocaleDataDir(__DIR__.'/locales');
        Model::setLocale($locale);

        $this->assertEquals('Email address', TestModel2::getPropertyTitle('validate'));
        $this->assertEquals('Some property', Model::getPropertyTitle('some_property'));
    }

    /////////////////////////////
    // (R) Read
    /////////////////////////////

    public function testId()
    {
        $model = new TestModel(['id' => 5]);
        $this->assertEquals(5, $model->id());
    }

    public function testMultipleIds()
    {
        $model = new TestModel2(['id' => 5, 'id2' => 2]);
        $this->assertEquals('5,2', $model->id());
    }

    public function testIds()
    {
        $model = new TestModel(['id' => 3]);
        $this->assertEquals(['id' => 3], $model->ids());

        $model = new TestModel2(['id' => 5, 'id2' => 2]);
        $this->assertEquals(['id' => 5, 'id2' => 2], $model->ids());
    }

    public function testToString()
    {
        $model = new TestModel(['id' => 1]);
        $this->assertEquals('TestModel(1)', (string) $model);
    }

    public function testSetAndGetUnsaved()
    {
        $model = new TestModel();

        $model->test = 12345;
        $this->assertEquals(12345, $model->test);

        $model->null = null;
        $this->assertEquals(null, $model->null);

        $model->mutator = 'test';
        $this->assertEquals('TEST', $model->mutator);

        $model->accessor = 'TEST';
        $this->assertEquals('test', $model->accessor);
    }

    public function testGetNonExisting()
    {
        $this->setExpectedException('InvalidArgumentException');

        $model = new TestModel();
        $model->nonexistent_property;
    }

    public function testIsset()
    {
        $model = new TestModel();

        $this->assertFalse(isset($model->test2));

        $model->test = 12345;
        $this->assertTrue(isset($model->test));

        $model->null = null;
        $this->assertTrue(isset($model->null));
    }

    public function testUnset()
    {
        $model = new TestModel();

        $model->test = 12345;
        unset($model->test);
        $this->assertFalse(isset($model->test));
    }

    public function testHasNoId()
    {
        $model = new TestModel();
        $this->assertNull($model->id());
    }

    public function testGetMultipleProperties()
    {
        $model = new TestModel(['id' => 3]);
        $model->relation = '10';
        $model->answer = 42;

        $expected = [
            'id' => 3,
            'relation' => 10,
            'answer' => 42,
        ];

        $values = $model->get(['id', 'relation', 'answer']);
        $this->assertEquals($expected, $values);
    }

    public function testGetWithDefaultValue()
    {
        $model = new TestModel2();
        $this->assertEquals('some default value', $model->default);
    }

    public function testToArray()
    {
        $model = new TestModel(['id' => 5]);

        $expected = [
            'id' => 5,
            'relation' => null,
            'answer' => null,
            'test_hook' => null,
            'appended' => true,
        ];

        $this->assertEquals($expected, $model->toArray());
    }

    public function testToArrayWithRelationship()
    {
        $model = new TestModel2(['id' => 1, 'id2' => 2, 'person_id' => 3]);

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');
        $driver->shouldReceive('queryModels')
               ->andReturn([['id' => 3, 'name' => 'Bob', 'email' => 'bob@example.com']]);

        Model::setDriver($driver);

        $expected = [
            'id' => 1,
            'id2' => 2,
            'default' => 'some default value',
            'validate' => null,
            'unique' => null,
            'required' => null,
            'created_at' => null,
            'updated_at' => null,
            'person' => [
                'id' => 3,
                'name' => 'Bob',
                'email' => 'bob@example.com',
            ],
        ];

        $this->assertEquals($expected, $model->toArray());
    }

    public function testArrayAccess()
    {
        $model = new TestModel();

        // test offsetExists
        $this->assertFalse(isset($model['test']));
        $model->test = true;
        $this->assertTrue(isset($model['test']));

        // test offsetGet
        $this->assertEquals(true, $model['test']);

        // test offsetSet
        $model['test'] = 'hello world';
        $this->assertEquals('hello world', $model['test']);

        // test offsetUnset
        unset($model['test']);
        $this->assertFalse(isset($model['test']));
    }

    /////////////////////////////
    // (C) Create
    /////////////////////////////

    public function testCreate()
    {
        $newModel = new TestModel();

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('createModel')
               ->withArgs([$newModel, [
                    'mutator' => 'BLAH',
                    'relation' => null,
                    'answer' => 42,
                ]])
               ->andReturn(true)
               ->once();

        $driver->shouldReceive('getCreatedID')
               ->withArgs([$newModel, 'id'])
               ->andReturn(1);

        Model::setDriver($driver);

        $params = [
            'relation' => '',
            'answer' => 42,
            'extra' => true,
            'mutator' => 'blah',
            'array' => [],
            'object' => new stdClass(),
        ];

        $this->assertTrue($newModel->create($params));
        $this->assertEquals(1, $newModel->id());
        $this->assertEquals(1, $newModel->id);
        $this->assertEquals(42, $newModel->answer);
    }

    public function testCreateWithSave()
    {
        $newModel = new TestModel();

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('createModel')
               ->withArgs([$newModel, [
                    'mutator' => 'BLAH',
                    'relation' => null,
                    'answer' => 42,
                ]])
               ->andReturn(true)
               ->once();

        $driver->shouldReceive('getCreatedID')
               ->andReturn(1);

        Model::setDriver($driver);

        $newModel->relation = '';
        $newModel->answer = 42;
        $newModel->extra = true;
        $newModel->mutator = 'blah';
        $newModel->array = [];
        $newModel->object = new stdClass();

        $this->assertTrue($newModel->save());
    }

    public function testCreateMutable()
    {
        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('createModel')
               ->andReturn(true)
               ->once();

        Model::setDriver($driver);

        $newModel = new TestModel2();
        $newModel->id = 1;
        $newModel->id2 = 2;
        $newModel->required = 25;
        $this->assertTrue($newModel->create());
        $this->assertEquals('1,2', $newModel->id());
    }

    public function testCreateMassAssignment()
    {
        $newModel = new TestModel2();

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $object = new stdClass();
        $object->test = true;

        $driver->shouldReceive('createModel')
               ->withArgs([$newModel, [
                    'id' => 1,
                    'id2' => 2,
                    'required' => true,
                    'validate' => 'shouldtrimws@example.com',
                    'mutable_create_only' => 'test',
                    'default' => 'some default value',
                    'hidden' => false,
                    'array' => [
                        'tax' => '%',
                        'discounts' => false,
                        'shipping' => false,
                    ],
                    'object' => $object,
                    'person_id' => 20,
                 ]])
               ->andReturn(true)
               ->once();

        Model::setDriver($driver);

        $input = [
            'id' => 1,
            'id2' => 2,
            'required' => 'on',
            'validate' => '  SHOULDTRIMWS@EXAMPLE.COM ',
            'mutable_create_only' => 'test',
            'object' => $object,
        ];

        $this->assertTrue($newModel->create($input));
    }

    public function testCreateImmutableId()
    {
        $newModel = new TestModel();

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('createModel')
               ->andReturn(true);

        $driver->shouldReceive('getCreatedID')
               ->andReturn(1);

        Model::setDriver($driver);

        $newModel->id = 100;
        $this->assertTrue($newModel->create());
        $this->assertNotEquals(100, $newModel->id());
    }

    public function testCreateWithId()
    {
        $this->setExpectedException('BadMethodCallException');

        $model = new TestModel();
        $model->refreshWith(['id' => 5]);
        $model->relation = '';
        $model->answer = 42;
        $this->assertFalse($model->create());
    }

    public function testCreatingListenerFail()
    {
        TestModel::creating(function (ModelEvent $event) {
            $event->stopPropagation();
        });

        $newModel = new TestModel();
        $this->assertFalse($newModel->create());
    }

    public function testCreatedListenerFail()
    {
        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('createModel')
               ->andReturn(true);

        $driver->shouldReceive('getCreatedID')
               ->andReturn(1);

        Model::setDriver($driver);

        TestModel::created(function (ModelEvent $event) {
            $event->stopPropagation();
        });

        $newModel = new TestModel();
        $this->assertFalse($newModel->create());
    }

    public function testCreateNotUnique()
    {
        $query = TestModel2::query();
        TestModel2::setQuery($query);

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('totalRecords')
               ->andReturn(1);

        Model::setDriver($driver);

        $model = new TestModel2();

        $create = [
            'id' => 2,
            'id2' => 4,
            'required' => true,
            'unique' => 'fail',
        ];
        $this->assertFalse($model->create($create));

        // verify error
        $this->assertCount(1, $model->errors());
        $this->assertEquals(['pulsar.validation.unique'], $model->errors()['unique']);

        $this->assertEquals(['unique' => 'fail'], $query->getWhere());
    }

    public function testCreateInvalid()
    {
        $newModel = new TestModel2();
        $newModel->id = 10;
        $newModel->id2 = 1;
        $newModel->validate = 'notanemail';
        $newModel->required = true;
        $this->assertFalse($newModel->create());
        $this->assertCount(1, $newModel->errors());
    }

    public function testCreateMissingRequired()
    {
        $newModel = new TestModel2();
        $newModel->id = 10;
        $newModel->id2 = 1;
        $this->assertFalse($newModel->create());
        $this->assertCount(1, $newModel->errors());
    }

    public function testCreateFail()
    {
        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('createModel')
               ->andReturn(false);

        Model::setDriver($driver);

        $newModel = new TestModel();
        $newModel->relation = '';
        $newModel->answer = 42;
        $this->assertFalse($newModel->create());
    }

    /////////////////////////////
    // (U) Update
    /////////////////////////////

    public function testSet()
    {
        $model = new TestModel();
        $model->refreshWith(['id' => 10]);

        $this->assertTrue($model->set());

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('updateModel')
               ->withArgs([$model, ['answer' => 42]])
               ->andReturn(true);

        Model::setDriver($driver);

        $model->answer = 42;

        $this->assertTrue($model->set());
        $this->assertEquals(42, $model->answer);
    }

    public function testSetFromSave()
    {
        $model = new TestModel();
        $model->refreshWith(['id' => 10]);

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('updateModel')
               ->withArgs([$model, ['answer' => 42]])
               ->andReturn(true);

        Model::setDriver($driver);

        $model->answer = 42;
        $this->assertTrue($model->save());
    }

    public function testSetMassAssignment()
    {
        $model = new TestModel();
        $model->refreshWith(['id' => 11]);

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('updateModel')
               ->withArgs([$model, ['answer' => 'hello', 'mutator' => 'BLAH', 'relation' => null]])
               ->andReturn(true)
               ->once();

        Model::setDriver($driver);

        $this->assertTrue($model->set([
            'answer' => ['hello', 'hello'],
            'relation' => '',
            'mutator' => 'blah',
            'nonexistent_property' => 'whatever',
        ]));
    }

    public function testSetImmutableProperties()
    {
        $model = new TestModel();
        $model->refreshWith(['id' => 10]);

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('updateModel')
               ->withArgs([$model, []])
               ->andReturn(true)
               ->once();

        Model::setDriver($driver);

        $model->id = 432;
        $model->mutable_create_only = 'blah';

        $this->assertTrue($model->set());
    }

    public function testSetFail()
    {
        $model = new TestModel();
        $model->refreshWith(['id' => 10]);

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');
        $driver->shouldReceive('updateModel')
               ->andReturn(false);

        Model::setDriver($driver);

        $model->answer = 42;

        $this->assertFalse($model->set());
    }

    public function testSetFailWithNoId()
    {
        $this->setExpectedException('BadMethodCallException');

        $model = new TestModel();
        $this->assertFalse($model->set());
    }

    public function testUpdatingListenerFail()
    {
        TestModel::updating(function (ModelEvent $event) {
            $event->stopPropagation();
        });

        $model = new TestModel();
        $model->refreshWith(['id' => 100]);
        $model->answer = 42;
        $this->assertFalse($model->set());
    }

    public function testUpdatedListenerFail()
    {
        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('updateModel')
               ->andReturn(true);

        Model::setDriver($driver);

        TestModel::updated(function (ModelEvent $event) {
            $event->stopPropagation();
        });

        $model = new TestModel();
        $model->refreshWith(['id' => 100]);
        $model->answer = 42;
        $this->assertFalse($model->set());
    }

    public function testSetUnique()
    {
        $query = TestModel2::query();
        TestModel2::setQuery($query);

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('totalRecords')
               ->andReturn(0);

        $driver->shouldReceive('updateModel')
               ->andReturn(true);

        Model::setDriver($driver);

        $model = new TestModel2();
        $model->refreshWith(['id' => 12]);
        $model->unique = 'works';
        $model->required = 'required';
        $this->assertTrue($model->set());

        // validate query where statement
        $this->assertEquals(['unique' => 'works'], $query->getWhere());
    }

    public function testSetUniqueSkip()
    {
        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('updateModel')
               ->andReturn(true);

        Model::setDriver($driver);

        $model = new TestModel2();
        $model->refreshWith(['id' => 12, 'required' => true, 'unique' => 'works']);
        $model->unique = 'works';
        $this->assertTrue($model->set());
    }

    public function testSetInvalid()
    {
        $model = new TestModel2();
        $model->refreshWith(['id' => 15]);
        $model->required = true;
        $model->validate2 = 'invalid';

        $this->assertFalse($model->set());
    }

    /////////////////////////////
    // (D) Delete
    /////////////////////////////

    public function testDelete()
    {
        $model = new TestModel2();
        $model->refreshWith(['id' => 1]);

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');
        $driver->shouldReceive('deleteModel')
               ->withArgs([$model])
               ->andReturn(true);
        Model::setDriver($driver);

        $this->assertTrue($model->delete());
        $this->assertFalse($model->persisted());
    }

    public function testDeleteWithNoId()
    {
        $this->setExpectedException('BadMethodCallException');

        $model = new TestModel();
        $this->assertFalse($model->delete());
    }

    public function testDeletingListenerFail()
    {
        TestModel::deleting(function (ModelEvent $event) {
            $event->stopPropagation();
        });

        $model = new TestModel();
        $model->refreshWith(['id' => 100]);
        $this->assertFalse($model->delete());
        $this->assertTrue($model->persisted());
    }

    public function testDeletedListenerFail()
    {
        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('deleteModel')
               ->andReturn(true);

        Model::setDriver($driver);

        TestModel::deleted(function (ModelEvent $event) {
            $event->stopPropagation();
        });

        $model = new TestModel();
        $model->refreshWith(['id' => 100]);
        $this->assertFalse($model->delete());
        $this->assertTrue($model->persisted());
    }

    public function testDeleteFail()
    {
        $model = new TestModel2();
        $model->refreshWith(['id' => 1]);

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');
        $driver->shouldReceive('deleteModel')
               ->withArgs([$model])
               ->andReturn(false);
        Model::setDriver($driver);

        $this->assertFalse($model->delete());
        $this->assertTrue($model->persisted());
    }

    /////////////////////////////
    // Queries
    /////////////////////////////

    public function testQuery()
    {
        $query = TestModel::query();

        $this->assertInstanceOf('Pulsar\Query', $query);
        $this->assertInstanceOf('TestModel', $query->getModel());
    }

    public function testQueryStatic()
    {
        $query = TestModel::where(['name' => 'Bob']);

        $this->assertInstanceOf('Pulsar\Query', $query);
    }

    public function testFind()
    {
        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('queryModels')
               ->andReturnUsing(function ($query) {
                    $this->assertEquals(['id' => 100], $query->getWhere());

                    return [['id' => 100, 'answer' => 42]];
               })
               ->once();

        Model::setDriver($driver);

        $model = TestModel::find(100);
        $this->assertInstanceOf('TestModel', $model);
        $this->assertEquals(100, $model->id());
        $this->assertEquals(42, $model->answer);
    }

    public function testFindFail()
    {
        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('queryModels')
               ->andReturn([])
               ->once();

        Model::setDriver($driver);

        $this->assertNull(TestModel::find(101));
    }

    public function testFindOrFail()
    {
        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('queryModels')
               ->andReturn([['id' => 100, 'answer' => 42]])
               ->once();

        Model::setDriver($driver);

        $model = TestModel::findOrFail(100);
        $this->assertInstanceOf('TestModel', $model);
        $this->assertEquals(100, $model->id());
        $this->assertEquals(42, $model->answer);
    }

    public function testFindOrFailNotFound()
    {
        $this->setExpectedException('Pulsar\Exception\NotFoundException');

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('queryModels')
               ->andReturn([])
               ->once();

        Model::setDriver($driver);

        $this->assertNull(TestModel::findOrFail(101));
    }

    public function testTotalRecords()
    {
        $query = TestModel2::query();
        TestModel2::setQuery($query);

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('totalRecords')
               ->andReturn(1);

        Model::setDriver($driver);

        $this->assertEquals(1, TestModel2::totalRecords(['name' => 'John']));

        $this->assertEquals(['name' => 'John'], $query->getWhere());
    }

    public function testTotalRecordsNoCriteria()
    {
        $query = TestModel2::query();
        TestModel2::setQuery($query);

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('totalRecords')
               ->andReturn(2);

        Model::setDriver($driver);

        $this->assertEquals(2, TestModel2::totalRecords());

        $this->assertEquals([], $query->getWhere());
    }

    public function testPersisted()
    {
        $model = new TestModel();
        $this->assertFalse($model->persisted());

        $model = new TestModel();
        $model->refreshWith(['id' => 12]);
        $this->assertTrue($model->persisted());
    }

    /////////////////////////////
    // Relationships
    /////////////////////////////

    public function testHasOne()
    {
        $model = new TestModel();

        $relation = $model->hasOne('TestModel2');

        $this->assertInstanceOf('Pulsar\Relation\HasOne', $relation);
        $this->assertEquals('TestModel2', $relation->getModel());
        $this->assertEquals('test_model_id', $relation->getForeignKey());
        $this->assertEquals('id', $relation->getLocalKey());
        $this->assertEquals($model, $relation->getRelation());
    }

    public function testBelongsTo()
    {
        $model = new TestModel();

        $relation = $model->belongsTo('TestModel2');

        $this->assertInstanceOf('Pulsar\Relation\BelongsTo', $relation);
        $this->assertEquals('TestModel2', $relation->getModel());
        $this->assertEquals('id', $relation->getForeignKey());
        $this->assertEquals('test_model2_id', $relation->getLocalKey());
        $this->assertEquals($model, $relation->getRelation());
    }

    public function testHasMany()
    {
        $model = new TestModel();

        $relation = $model->hasMany('TestModel2');

        $this->assertInstanceOf('Pulsar\Relation\HasMany', $relation);
        $this->assertEquals('TestModel2', $relation->getModel());
        $this->assertEquals('test_model_id', $relation->getForeignKey());
        $this->assertEquals('id', $relation->getLocalKey());
        $this->assertEquals($model, $relation->getRelation());
    }

    public function testBelongsToMany()
    {
        $model = new TestModel();

        $relation = $model->belongsToMany('TestModel2');

        $this->assertInstanceOf('Pulsar\Relation\BelongsToMany', $relation);
        $this->assertEquals('TestModel2', $relation->getModel());
        $this->assertEquals('id', $relation->getForeignKey());
        $this->assertEquals('test_model2_id', $relation->getLocalKey());
        $this->assertEquals($model, $relation->getRelation());
    }

    public function testIsRelationship()
    {
        $this->assertTrue(TestModel2::isRelationship('person'));
        $this->assertFalse(TestModel2::isRelationship('id'));
        $this->assertFalse(TestModel2::isRelationship('person_id'));
    }

    public function testGetRelationship()
    {
        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $person = new Person();
        $driver->shouldReceive('queryModels')
               ->andReturnUsing(function ($query) {
                    return [['id' => $query->getWhere()['id']]];
               });

        Model::setDriver($driver);

        $model = new TestModel2();
        $model->person_id = '10';
        $person = $model->person;
        $this->assertInstanceOf('Person', $person);
        $this->assertEquals('10', $person->id);
    }

    public function testSetRelationship()
    {
        $this->setExpectedException('BadMethodCallException');

        $model = new TestModel2();
        $model->person = 'test';
    }

    public function testUnsetRelationship()
    {
        $this->setExpectedException('BadMethodCallException');

        $model = new TestModel2();
        unset($model->person);
    }

    /////////////////////////////
    // STORAGE
    /////////////////////////////

    public function testRefreshNotPersisted()
    {
        $this->setExpectedException('Pulsar\Exception\NotFoundException');

        $model = new TestModel2();
        $model->refresh();
    }

    public function testRefresh()
    {
        $model = new TestModel2();
        $model->refreshWith(['id' => 12, 'id2' => 13]);

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('queryModels')
               ->andReturnUsing(function ($query) {
                    $this->assertEquals(['id' => 12, 'id2' => 13], $query->getWhere());

                    return [['unique' => 'value']];
               })
               ->once();

        Model::setDriver($driver);

        $this->assertEquals($model, $model->refresh());
        $this->assertEquals('value', $model->unique);
    }

    public function testRefreshFail()
    {
        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('queryModels')
               ->andReturn([]);

        Model::setDriver($driver);

        $model = new TestModel2();
        $model->refreshWith(['id' => 12]);
        $this->assertEquals($model, $model->refresh());
    }

    /////////////////////////////
    // Validation
    /////////////////////////////

    public function testErrors()
    {
        $model = new TestModel();
        $stack = $model->errors();
        $this->assertInstanceOf('Pulsar\Errors', $stack);
        $this->assertEquals($stack, $model->errors());
    }

    public function testErrorsLocale()
    {
        $locale = new Locale();
        Model::setLocale($locale);
        $model = new TestModel();
        $this->assertEquals($locale, $model->errors()->getLocale());
    }

    public function testValidator()
    {
        $model = new TestModel();
        $validator = $model->getValidator();
        $this->assertInstanceOf('Pulsar\Validator', $validator);
    }

    public function testValid()
    {
        $model = new TestModel2();
        $this->assertFalse($model->valid());
        $this->assertCount(1, $model->errors());
        $this->assertEquals(['pulsar.validation.required'], $model->errors()['required']);

        $model->required = true;
        $this->assertTrue($model->valid());

        $model->validate = 'not an email address';
        $this->assertFalse($model->valid());
        $this->assertCount(1, $model->errors());
        $this->assertEquals(['pulsar.validation.email'], $model->errors()['validate']);
    }
}
