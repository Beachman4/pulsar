<?php

/**
 * @package Pulsar
 * @author Jared King <j@jaredtking.com>
 * @link http://jaredtking.com
 * @copyright 2015 Jared King
 * @license MIT
 */

use Infuse\ErrorStack;
use Infuse\Locale;
use Pulsar\Model;
use Pulsar\ModelEvent;
use Pimple\Container;

require_once 'test_models.php';

class ModelTest extends PHPUnit_Framework_TestCase
{
    public static $app;

    public static function setUpBeforeClass()
    {
        // set up DI
        self::$app = new Container();
        self::$app['locale'] = function () {
            return new Locale();
        };
        self::$app['errors'] = function ($app) {
            return new ErrorStack($app);
        };

        Model::inject(self::$app);
    }

    protected function tearDown()
    {
        Model::inject(self::$app);

        // discard the cached dispatcher to
        // remove any event listeners
        TestModel::getDispatcher(true);
    }

    public function testInjectContainer()
    {
        $c = new \Pimple\Container();
        Model::inject($c);

        $model = new TestModel();
        $this->assertEquals($c, $model->getApp());
    }

    public function testDriver()
    {
        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');
        TestModel::setDriver($driver);

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
        TestModel::setDriver($driver);
    }

    public function testGetProperties()
    {
        $expected = [
            'id' => [
                'type' => Model::TYPE_NUMBER,
                'mutable' => Model::IMMUTABLE,
                'null' => false,
                'unique' => false,
                'required' => false,
            ],
            'relation' => [
                'type' => Model::TYPE_NUMBER,
                'relation' => 'TestModel2',
                'null' => true,
                'unique' => false,
                'required' => false,
                'mutable' => Model::MUTABLE,
            ],
            'answer' => [
                'type' => Model::TYPE_STRING,
                'mutable' => Model::MUTABLE,
                'null' => false,
                'unique' => false,
                'required' => false,
            ],
            'test_hook' => [
                'type' => Model::TYPE_STRING,
                'null' => true,
                'mutable' => Model::MUTABLE,
                'unique' => false,
                'required' => false,
            ],
            'mutator' => [
                'type' => Model::TYPE_STRING,
                'null' => false,
                'mutable' => Model::MUTABLE,
                'unique' => false,
                'required' => false,
            ],
            'accessor' => [
                'type' => Model::TYPE_STRING,
                'null' => false,
                'mutable' => Model::MUTABLE,
                'unique' => false,
                'required' => false,
            ],
            'test_model2_id' => [
                'type' => Model::TYPE_STRING,
                'null' => false,
                'mutable' => Model::MUTABLE,
                'unique' => false,
                'required' => false,
            ],            
        ];

        $this->assertEquals($expected, TestModel::getProperties());
    }

    public function testPropertiesIdOverwrite()
    {
        $expected = [
            'type' => Model::TYPE_STRING,
            'mutable' => Model::MUTABLE,
            'null' => false,
            'unique' => false,
            'required' => false,
        ];

        $this->assertEquals($expected, Person::getProperty('id'));
    }

    public function testGetProperty()
    {
        $expected = [
            'type' => Model::TYPE_NUMBER,
            'mutable' => Model::IMMUTABLE,
            'null' => false,
            'unique' => false,
            'required' => false,
        ];
        $this->assertEquals($expected, TestModel::getProperty('id'));

        $expected = [
            'type' => Model::TYPE_NUMBER,
            'relation' => 'TestModel2',
            'null' => true,
            'unique' => false,
            'required' => false,
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
                'null' => false,
                'unique' => false,
                'required' => false,
            ],
            'id2' => [
                'type' => Model::TYPE_NUMBER,
                'mutable' => Model::MUTABLE,
                'null' => false,
                'unique' => false,
                'required' => false,
            ],
            'default' => [
                'type' => Model::TYPE_STRING,
                'default' => 'some default value',
                'mutable' => Model::MUTABLE,
                'null' => false,
                'unique' => false,
                'required' => false,
            ],
            'validate' => [
                'type' => Model::TYPE_STRING,
                'validate' => 'email',
                'null' => true,
                'mutable' => Model::MUTABLE,
                'unique' => false,
                'required' => false,
            ],
            'validate2' => [
                'type' => Model::TYPE_STRING,
                'validate' => 'validate',
                'null' => true,
                'mutable' => Model::MUTABLE,
                'unique' => false,
                'required' => false,
            ],
            'unique' => [
                'type' => Model::TYPE_STRING,
                'unique' => true,
                'mutable' => Model::MUTABLE,
                'null' => false,
                'required' => false,
            ],
            'required' => [
                'type' => Model::TYPE_NUMBER,
                'required' => true,
                'mutable' => Model::MUTABLE,
                'null' => false,
                'unique' => false,
            ],
            'hidden' => [
                'type' => Model::TYPE_BOOLEAN,
                'default' => false,
                'mutable' => Model::MUTABLE,
                'null' => false,
                'unique' => false,
                'required' => false,
            ],
            'person' => [
                'type' => Model::TYPE_NUMBER,
                'relation' => 'Person',
                'default' => 20,
                'mutable' => Model::MUTABLE,
                'null' => false,
                'unique' => false,
                'required' => false,
            ],
            'array' => [
                'type' => Model::TYPE_ARRAY,
                'mutable' => Model::MUTABLE,
                'null' => false,
                'default' => [
                    'tax' => '%',
                    'discounts' => false,
                    'shipping' => false,
                ],
                'unique' => false,
                'required' => false,
            ],
            'object' => [
                'type' => Model::TYPE_OBJECT,
                'mutable' => Model::MUTABLE,
                'null' => false,
                'unique' => false,
                'required' => false,
            ],
            'mutable_create_only' => [
                'type' => Model::TYPE_STRING,
                'mutable' => Model::MUTABLE_CREATE_ONLY,
                'null' => false,
                'unique' => false,
                'required' => false,
            ],
            'created_at' => [
                'type' => Model::TYPE_DATE,
                'default' => null,
                'mutable' => Model::MUTABLE,
                'null' => true,
                'unique' => false,
                'required' => false,
                'validate' => 'timestamp|db_timestamp',
            ],
            'updated_at' => [
                'type' => Model::TYPE_DATE,
                'mutable' => Model::MUTABLE,
                'null' => false,
                'unique' => false,
                'required' => false,
                'validate' => 'timestamp|db_timestamp',
            ],
        ];

        $model = new TestModel2; // forces initialize()
        $this->assertEquals($expected, TestModel2::getProperties());
    }

    public function testGetIDProperties()
    {
        $this->assertEquals(['id'], TestModel::getIDProperties());

        $this->assertEquals(['id', 'id2'], TestModel2::getIDProperties());
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

    public function testId()
    {
        $model = new TestModel(5);

        $this->assertEquals(5, $model->id());

        $model2 = new TestModel($model);
        $this->assertEquals(5, $model2->id());
    }

    public function testMultipleIds()
    {
        $model = new TestModel2([5, 2]);

        $this->assertEquals('5,2', $model->id());

        $model2 = new TestModel(5);
        $model3 = new TestModel2([$model2, 2]);
        $this->assertEquals('5,2', $model3->id());
    }

    public function testIds()
    {
        $model = new TestModel(3);
        $this->assertEquals(['id' => 3], $model->ids());

        $model = new TestModel2([5, 2]);
        $this->assertEquals(['id' => 5, 'id2' => 2], $model->ids());
    }

    public function testToString()
    {
        $model = new TestModel(1);
        $this->assertEquals('TestModel(1)', (string) $model);
    }

    public function testSetAndGetUnsaved()
    {
        $model = new TestModel(2);

        $model->test = 12345;
        $this->assertEquals(12345, $model->test);

        $model->null = null;
        $this->assertEquals(null, $model->null);

        $model->mutator = 'test';
        $this->assertEquals('TEST', $model->mutator);

        $model->accessor = 'TEST';
        $this->assertEquals('test', $model->accessor);
    }

    public function testIsset()
    {
        $model = new TestModel(1);

        $this->assertFalse(isset($model->test2));

        $model->test = 12345;
        $this->assertTrue(isset($model->test));

        $model->null = null;
        $this->assertTrue(isset($model->null));
    }

    public function testUnset()
    {
        $model = new TestModel(1);

        $model->test = 12345;
        unset($model->test);
        $this->assertFalse(isset($model->test));
    }

    public function testHasNoId()
    {
        $model = new TestModel();
        $this->assertFalse($model->id());
    }

    public function testGetMultipleProperties()
    {
        $model = new TestModel(3);
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

    public function testGetFromDb()
    {
        $model = new TestModel(12);

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('loadModel')
               ->withArgs([$model])
               ->andReturn(['answer' => 42])
               ->once();

        TestModel::setDriver($driver);

        $this->assertEquals(42, $model->answer);
    }

    public function testGetDefaultValue()
    {
        $model = new TestModel2(12);

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('loadModel')
               ->andReturn([]);

        TestModel2::setDriver($driver);

        $this->assertEquals('some default value', $model->default);
    }

    public function testToArray()
    {
        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('loadModel')
               ->andReturn([]);

        TestModel::setDriver($driver);

        $model = new TestModel(5);

        $expected = [
            'id' => 5,
            'relation' => null,
            'answer' => null,
            'test_hook' => null,
            'appended' => true,
            // this is tacked on in toArrayHook() below
            'toArray' => true,
        ];

        $this->assertEquals($expected, $model->toArray());
    }

    public function testToArrayDeprecated()
    {
        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('loadModel')
               ->andReturn([]);

        TestModel::setDriver($driver);

        $model = new TestModel(5);

        $expected = [
            'id' => 5,
            'relation' => null,
            'answer' => null,
            'test_hook' => null,
            'appended' => true,
            // this is tacked on in toArrayHook() below
            'toArray' => true,
        ];

        $this->assertEquals($expected, $model->toArrayDeprecated([], [], ['relation']));
    }

    public function testToArrayDeprecatedExcluded()
    {
        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('loadModel')
               ->andReturn([]);

        TestModel::setDriver($driver);

        $model = new TestModel(5);
        $model->relation = 100;

        $expected = [
            'relation' => 100,
        ];

        $this->assertEquals($expected, $model->toArrayDeprecated(['id', 'answer', 'toArray', 'test_hook', 'appended']));
    }

    public function testToArrayDeprecatedAutoTimestamps()
    {
        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('loadModel')
               ->andReturn([]);

        TestModel2::setDriver($driver);

        $model = new TestModel2(5);
        $model->created_at = 100;
        $model->updated_at = 102;

        $expected = [
            'created_at' => 100,
            'updated_at' => '102',
        ];

        $this->assertEquals($expected, $model->toArrayDeprecated(['id', 'id2', 'default', 'validate', 'unique', 'required']));

        $model->created_at = '-1';
        $this->assertEquals(-1, $model->created_at);
    }

    public function testToArrayDeprecatedIncluded()
    {
        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('loadModel')
               ->andReturn([]);

        TestModel::setDriver($driver);

        $model = new TestModel2(5);
        $model->hidden = true;
        $model->object = new stdClass();

        $expected = [
            'hidden' => true,
            'array' => [
                'tax' => '%',
                'discounts' => false,
                'shipping' => false,
            ],
            'object' => new stdClass(),
            'toArrayHook' => true,
        ];

        $this->assertEquals($expected, $model->toArrayDeprecated(['id', 'id2', 'default', 'validate', 'unique', 'required', 'created_at', 'updated_at'], ['hidden', 'toArrayHook', 'array', 'object']));
    }

    public function testToArrayDeprecatedExpand()
    {
        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('loadModel')
               ->andReturn([]);

        TestModel::setDriver($driver);

        $model = new TestModel(10);
        $model->relation = 100;
        $model->answer = 42;

        $result = $model->toArrayDeprecated(
            [
                'id',
                'toArray',
                'test_hook',
                'appended',
                'relation.created_at',
                'relation.updated_at',
                'relation.validate',
                'relation.unique',
                'relation.person.email',
            ],
            [
                'relation.hidden',
                'relation.person',
            ],
            [
                'relation.person',
            ]);

        $expected = [
            'answer' => 42,
            'relation' => [
                'id' => 100,
                'id2' => 0,
                'required' => null,
                'default' => 'some default value',
                'hidden' => false,
                'person' => [
                    'id' => 20,
                    'name' => 'Jared',
                ],
            ],
        ];

        $this->assertEquals($expected, $result);
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
    // CREATE
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

        TestModel::setDriver($driver);

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

        TestModel::setDriver($driver);

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

        TestModel2::setDriver($driver);

        $newModel = new TestModel2();
        $this->assertTrue($newModel->create(['id' => 1, 'id2' => 2, 'required' => 25]));
        $this->assertEquals('1,2', $newModel->id());
    }

    public function testCreateImmutable()
    {
        $newModel = new TestModel2();

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $object = new stdClass();
        $object->test = true;

        $driver->shouldReceive('createModel')
               ->withArgs([$newModel, [
                    'id' => 1,
                    'id2' => 2,
                    'required' => 25,
                    'mutable_create_only' => 'test',
                    'default' => 'some default value',
                    'hidden' => false,
                    'created_at' => null,
                    'array' => [
                        'tax' => '%',
                        'discounts' => false,
                        'shipping' => false,
                    ],
                    'object' => $object,
                    'person' => 20,
                 ]])
               ->andReturn(true);

        TestModel2::setDriver($driver);

        $this->assertTrue($newModel->create(['id' => 1, 'id2' => 2, 'required' => 25, 'mutable_create_only' => 'test', 'object' => $object]));
    }

    public function testCreateImmutableId()
    {
        $newModel = new TestModel();

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('createModel')
               ->andReturn(true);

        $driver->shouldReceive('getCreatedID')
               ->andReturn(1);

        TestModel::setDriver($driver);

        $this->assertTrue($newModel->create(['id' => 100]));
        $this->assertNotEquals(100, $newModel->id());
    }

    public function testCreateWithId()
    {
        $model = new TestModel(5);
        $this->assertFalse($model->create(['relation' => '', 'answer' => 42]));
    }

    public function testCreatingListenerFail()
    {
        TestModel::creating(function (ModelEvent $event) {
            $event->stopPropagation();
        });

        $newModel = new TestModel();
        $this->assertFalse($newModel->create([]));
    }

    public function testCreatedListenerFail()
    {
        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('createModel')
               ->andReturn(true);

        $driver->shouldReceive('getCreatedID')
               ->andReturn(1);

        TestModel::setDriver($driver);

        TestModel::created(function (ModelEvent $event) {
            $event->stopPropagation();
        });

        $newModel = new TestModel();
        $this->assertFalse($newModel->create([]));
    }

    public function testCreateHookFail()
    {
        $newModel = new TestModelHookFail();
        $this->assertFalse($newModel->create([]));
    }

    public function testCreateNotUnique()
    {
        $errorStack = self::$app['errors']->clear();

        $query = TestModel2::query();
        TestModel2::setQuery($query);

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('totalRecords')
               ->andReturn(1);

        TestModel2::setDriver($driver);

        $model = new TestModel2();

        $create = [
            'id' => 2,
            'id2' => 4,
            'required' => 25,
            'unique' => 'fail',
        ];
        $this->assertFalse($model->create($create));

        // verify error
        $this->assertCount(1, $errorStack->errors());

        $this->assertEquals(['unique' => 'fail'], $query->getWhere());
    }

    public function testCreateInvalid()
    {
        $errorStack = self::$app['errors']->clear();

        $newModel = new TestModel2();
        $this->assertFalse($newModel->create(['id' => 10, 'id2' => 1, 'validate' => 'notanemail', 'required' => true]));
        $this->assertCount(1, $errorStack->errors());
    }

    public function testCreateMissingRequired()
    {
        $errorStack = self::$app['errors']->clear();

        $newModel = new TestModel2();
        $this->assertFalse($newModel->create(['id' => 10, 'id2' => 1]));
        $this->assertCount(1, $errorStack->errors());
    }

    public function testCreateFail()
    {
        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('createModel')
               ->andReturn(false);

        TestModel::setDriver($driver);

        $newModel = new TestModel();
        $this->assertFalse($newModel->create(['relation' => '', 'answer' => 42]));
    }

    /////////////////////////////
    // SET
    /////////////////////////////

    public function testSet()
    {
        $model = new TestModel(10);

        $this->assertTrue($model->set([]));

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('updateModel')
               ->withArgs([$model, ['answer' => 42]])
               ->andReturn(true);

        TestModel::setDriver($driver);

        $this->assertTrue($model->set(['answer' => 42]));
    }

    public function testSetWithSave()
    {
        $model = new TestModel(10);

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('updateModel')
               ->withArgs([$model, ['answer' => 42]])
               ->andReturn(true);

        TestModel::setDriver($driver);

        $model->answer = 42;
        $this->assertTrue($model->save());
    }

    public function testSetMultiple()
    {
        $model = new TestModel(11);

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('updateModel')
               ->withArgs([$model, ['answer' => 'hello', 'mutator' => 'BLAH', 'relation' => null]])
               ->andReturn(true);

        TestModel::setDriver($driver);

        $this->assertTrue($model->set([
            'answer' => 'hello',
            'relation' => '',
            'mutator' => 'blah',
            'nonexistent_property' => 'whatever',
        ]));
    }

    public function testSetImmutableProperties()
    {
        $model = new TestModel(10);

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('updateModel')
               ->withArgs([$model, []])
               ->andReturn(true)
               ->once();

        TestModel::setDriver($driver);

        $this->assertTrue($model->set([
            'id' => 432,
            'mutable_create_only' => 'blah',
        ]));
        $this->assertEquals(10, $model->id);
    }

    public function testSetFailWithNoId()
    {
        $model = new TestModel();
        $this->assertFalse($model->set(['answer' => 42]));
    }

    public function testUpdatingListenerFail()
    {
        TestModel::updating(function (ModelEvent $event) {
            $event->stopPropagation();
        });

        $model = new TestModel(100);
        $this->assertFalse($model->set(['answer' => 42]));
    }

    public function testUpdatedListenerFail()
    {
        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('updateModel')
               ->andReturn(true);

        TestModel::setDriver($driver);

        TestModel::updated(function (ModelEvent $event) {
            $event->stopPropagation();
        });

        $model = new TestModel(100);
        $this->assertFalse($model->set(['answer' => 42]));
    }

    public function testSetHookFail()
    {
        $model = new TestModelHookFail(5);
        $this->assertFalse($model->set(['answer' => 42]));
    }

    public function testSetUnique()
    {
        $query = TestModel2::query();
        TestModel2::setQuery($query);

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('totalRecords')
               ->andReturn(0);

        $driver->shouldReceive('loadModel');

        $driver->shouldReceive('updateModel')
               ->andReturn(true);

        TestModel2::setDriver($driver);

        $model = new TestModel2(12);
        $this->assertTrue($model->set(['unique' => 'works']));

        // validate query where statement
        $this->assertEquals(['unique' => 'works'], $query->getWhere());
    }

    public function testSetUniqueSkip()
    {
        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('loadModel')
               ->andReturn(['unique' => 'works']);

        $driver->shouldReceive('updateModel')
               ->andReturn(true);

        TestModel2::setDriver($driver);

        $model = new TestModel2(12);
        $this->assertTrue($model->set(['unique' => 'works']));
    }

    public function testSetInvalid()
    {
        $errorStack = self::$app['errors']->clear();

        $model = new TestModel2(15);

        $this->assertFalse($model->set(['validate2' => 'invalid']));
        $this->assertCount(1, $errorStack->errors());
    }

    /////////////////////////////
    // DELETE
    /////////////////////////////

    public function testDelete()
    {
        $model = new TestModel2(1);

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');
        $driver->shouldReceive('deleteModel')
               ->withArgs([$model])
               ->andReturn(true);
        TestModel2::setDriver($driver);

        $this->assertTrue($model->delete());
    }

    public function testDeleteWithNoId()
    {
        $model = new TestModel();
        $this->assertFalse($model->delete());
    }

    public function testDeleteWithHook()
    {
        $model = new TestModel(100);

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');
        $driver->shouldReceive('deleteModel')
               ->withArgs([$model])
               ->andReturn(true);
        TestModel2::setDriver($driver);

        $this->assertTrue($model->delete());
        $this->assertTrue($model->preDelete);
        $this->assertTrue($model->postDelete);
    }

    public function testDeletingListenerFail()
    {
        TestModel::deleting(function (ModelEvent $event) {
            $event->stopPropagation();
        });

        $model = new TestModel(100);
        $this->assertFalse($model->delete());
    }

    public function testDeletedListenerFail()
    {
        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('deleteModel')
               ->andReturn(true);

        TestModel::setDriver($driver);

        TestModel::deleted(function (ModelEvent $event) {
            $event->stopPropagation();
        });

        $model = new TestModel(100);
        $this->assertFalse($model->delete());
    }

    public function testDeleteHookFail()
    {
        $model = new TestModelHookFail(5);
        $this->assertFalse($model->delete());
    }

    public function testDeleteFail()
    {
        $model = new TestModel2(1);

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');
        $driver->shouldReceive('deleteModel')
               ->withArgs([$model])
               ->andReturn(false);
        TestModel2::setDriver($driver);

        $this->assertFalse($model->delete());
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

    public function testTotalRecords()
    {
        $query = TestModel2::query();
        TestModel2::setQuery($query);

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('totalRecords')
               ->andReturn(1);

        TestModel2::setDriver($driver);

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

        TestModel2::setDriver($driver);

        $this->assertEquals(2, TestModel2::totalRecords());

        $this->assertEquals([], $query->getWhere());
    }

    public function testExists()
    {
        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('totalRecords')
               ->andReturn(1);

        TestModel2::setDriver($driver);

        $model = new TestModel2(12);
        $this->assertTrue($model->exists());
    }

    public function testNotExists()
    {
        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('totalRecords')
               ->andReturn(0);

        TestModel2::setDriver($driver);

        $model = new TestModel2(12);
        $this->assertFalse($model->exists());
    }

    /////////////////////////////
    // Relationships
    /////////////////////////////

    public function testRelation()
    {
        $model = new TestModel();
        $model->relation = 2;

        $relation = $model->relation('relation');
        $this->assertInstanceOf('TestModel2', $relation);
        $this->assertEquals(2, $relation->id());

        // test if relation model is cached
        $relation->test = 'hello';
        $relation2 = $model->relation('relation');
        $this->assertEquals('hello', $relation2->test);

        // reset the relation
        $model->relation = 3;
        $this->assertEquals(3, $model->relation('relation')->id());

        // check other methods for thorougness...
        unset($model->relation);
        $model->relation = 4;
        $this->assertEquals(4, $model->relation('relation')->id());
    }

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

    /////////////////////////////
    // STORAGE
    /////////////////////////////

    public function testRefresh()
    {
        $model = new TestModel2();
        $this->assertEquals($model, $model->refresh());
        $this->assertEquals($model, $model->load());

        $model = new TestModel2(12);

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('loadModel')
               ->withArgs([$model])
               ->andReturn([])
               ->once();

        TestModel2::setDriver($driver);

        $this->assertEquals($model, $model->refresh());
    }

    public function testRefreshFail()
    {
        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('loadModel')
               ->andReturn(false);

        TestModel2::setDriver($driver);

        $model = new TestModel2(12);
        $this->assertEquals($model, $model->refresh());
    }
}
