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
use Infuse\Locale;
use Pulsar\Adapter\AdapterInterface;
use Pulsar\Exception\AdapterMissingException;
use Pulsar\Exception\MassAssignmentException;
use Pulsar\Exception\NotFoundException;
use Pulsar\Model;
use Pulsar\ModelEvent;
use Pulsar\Query;

require_once 'test_models.php';

class ModelTest extends PHPUnit_Framework_TestCase
{
    protected function tearDown()
    {
        // discard the cached dispatcher to
        // remove any event listeners
        TestModel::getDispatcher(true);

        Model::clearAdapter();
        Model::clearLocale();

        date_default_timezone_set('UTC');
    }

    public function testAdapterMissing()
    {
        $this->setExpectedException(AdapterMissingException::class);
        Model::clearAdapter();
        Model::getAdapter();
    }

    public function testAdapter()
    {
        $adapter = Mockery::mock(AdapterInterface::class);
        Model::setAdapter($adapter);

        $this->assertEquals($adapter, TestModel::getAdapter());

        // setting the adapter for a single model sets
        // the adapter for all models
        $this->assertEquals($adapter, TestModel2::getAdapter());
    }

    public function testModelName()
    {
        $this->assertEquals('TestModel', TestModel::modelName());

        $adapter = Mockery::mock(AdapterInterface::class);
        $adapter->shouldReceive('getTablename')
                ->withArgs(['TestModel'])
                ->andReturn('TestModels');
        Model::setAdapter($adapter);
    }

    public function testGetTablename()
    {
        $model = new TestModel();
        $this->assertEquals('TestModels', $model->getTablename());

        $model = new TestModel2();
        $this->assertEquals('TestModel2s', $model->getTablename());
    }

    public function testGetIdProperties()
    {
        $this->assertEquals(['id'], TestModel::getIdProperties());

        $this->assertEquals(['id', 'id2'], TestModel2::getIdProperties());
    }

    public function testBuildFromId()
    {
        $model = TestModel::buildFromId(100);
        $this->assertInstanceOf(TestModel::class, $model);
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

    public function testGetDateFormat()
    {
        $this->assertEquals('U', TestModel::getDateFormat('date'));
        $this->assertEquals('Y-m-d H:i:s', TestModel2::getDateFormat('created_at'));
        $this->assertEquals('U', TestModel2::getDateFormat('updated_at'));
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

    public function testGetPropertyType()
    {
        $this->assertNull(TestModel::getPropertyType('nonexistent_property'));
        $this->assertEquals(Model::TYPE_INTEGER, TestModel::getPropertyType('relation'));
        $this->assertEquals(Model::TYPE_INTEGER, TestModel::getPropertyType('id'));
        $this->assertEquals(Model::TYPE_DATE, TestModel2::getPropertyType('created_at'));
        $this->assertEquals(Model::TYPE_DATE, TestModel2::getPropertyType('updated_at'));
    }

    public function testCast()
    {
        $this->assertNull(TestModel::cast(Model::TYPE_STRING, null));

        $this->assertEquals('string', TestModel::cast(Model::TYPE_STRING, 'string'));
    }

    public function testCastDate()
    {
        $date = TestModel2::cast(Model::TYPE_DATE, '2016-01-20 00:00:00', 'created_at');
        $this->assertInstanceOf(Carbon::class, $date);
        $this->assertEquals(1453248000, $date->timestamp);
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
        $this->setExpectedException(InvalidArgumentException::class);

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

        $values = $model->getValues(['id', 'relation', 'answer']);
        $this->assertEquals($expected, $values);

        $values = $model->get(['id', 'relation', 'answer']);
        $this->assertEquals($expected, $values);
    }

    public function testGetWithDefaultValue()
    {
        $model = new TestModel2();
        $this->assertEquals('some default value', $model->default);
    }

    public function testIgnoreUnsaved()
    {
        $model = new TestModel(['test' => true]);
        $model->test = false;

        // test get values
        $this->assertTrue($model->ignoreUnsaved()->getValues(['test'])['test']);
        $this->assertFalse($model->getValues(['test'])['test']);

        // test magic getter
        $this->assertTrue($model->ignoreUnsaved()->test);
        $this->assertFalse($model->test);
    }

    public function testGetProperties()
    {
        $newModel = new TestModel();
        $this->assertEquals(['id'], $newModel->getProperties());

        $newModel = new TestModel2();
        $this->assertEquals(['id', 'id2', 'default', 'hidden', 'person_id', 'array'], $newModel->getProperties());

        $model = new TestModel(['property' => true]);
        $this->assertEquals(['id', 'property'], $model->getProperties());
    }

    public function testHasProperty()
    {
        $newModel = new TestModel();
        $this->assertTrue($newModel->hasProperty('id'));

        $model = new TestModel(['property' => true]);
        $this->assertTrue($model->hasProperty('property'));
    }

    public function testToArray()
    {
        $model = new TestModel([
            'id' => 5,
            'relation' => 100,
            'answer' => 42,
            // hidden
            'mutator' => 'blah',
            'accessor' => 'blah',
            'test_model2_id' => 123,
        ]);

        $expected = [
            'id' => 5,
            'relation' => 100,
            'answer' => 42,
            'appended' => true,
        ];

        $this->assertEquals($expected, $model->toArray());
    }

    public function testToArrayWithRelationship()
    {
        $model = new TestModel2([
            'id' => 1,
            'id2' => 2,
            'person_id' => 3,
            'validate' => null,
            'unique' => null,
            'required' => null,
            // hidden
            'validate2' => null,
        ]);

        $adapter = Mockery::mock(AdapterInterface::class);
        $adapter->shouldReceive('queryModels')
                ->andReturn([['id' => 3, 'name' => 'Bob', 'email' => 'bob@example.com']]);

        Model::setAdapter($adapter);

        $expected = [
            'id' => 1,
            'id2' => 2,
            'default' => 'some default value',
            'validate' => null,
            'unique' => null,
            'required' => null,
            'person' => [
                'id' => 3,
                'name' => 'Bob',
                'email' => 'bob@example.com',
            ],
        ];

        $this->assertEquals($expected, $model->toArray());
    }

    public function testToArrayWithDates()
    {
        $model = new TestModel2([
            'id' => 1,
            'id2' => 2,
            'person_id' => null,
            'created_at' => '2016-01-20 00:00:00',
            'updated_at' => 5,
        ]);

        $expected = [
            'id' => 1,
            'id2' => 2,
            'person' => null,
            'default' => 'some default value',
            'created_at' => '2016-01-20 00:00:00',
            'updated_at' => '5',
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

        $adapter = Mockery::mock(AdapterInterface::class);

        $adapter->shouldReceive('createModel')
                ->withArgs([$newModel, [
                    'mutator' => 'BLAH',
                    'relation' => 0,
                    'answer' => 42,
                ]])
                ->andReturn(true)
                ->once();

        $adapter->shouldReceive('getCreatedID')
                ->withArgs([$newModel, 'id'])
                ->andReturn(1);

        Model::setAdapter($adapter);

        $params = [
            'relation' => '',
            'answer' => 42,
            'mutator' => 'blah',
        ];

        $this->assertTrue($newModel->create($params));
        $this->assertEquals(1, $newModel->id());
        $this->assertEquals(1, $newModel->id);
        $this->assertEquals(42, $newModel->answer);
    }

    public function testCreateFromSave()
    {
        $newModel = new TestModel();

        $adapter = Mockery::mock(AdapterInterface::class);

        $adapter->shouldReceive('createModel')
                ->withArgs([$newModel, [
                    'mutator' => 'BLAH',
                    'relation' => 0,
                    'answer' => 42,
                    'extra' => true,
                    'array' => [],
                    'object' => new stdClass(),
                ]])
                ->andReturn(true)
                ->once();

        $adapter->shouldReceive('getCreatedID')
                ->andReturn(1);

        Model::setAdapter($adapter);

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
        $adapter = Mockery::mock(AdapterInterface::class);

        $adapter->shouldReceive('createModel')
                ->andReturn(true)
                ->once();

        Model::setAdapter($adapter);

        $newModel = new TestModel2();
        $newModel->id = 1;
        $newModel->id2 = 2;
        $newModel->required = 25;
        $this->assertTrue($newModel->create());
        $this->assertEquals('1,2', $newModel->id());
    }

    public function testCreateAutoTimestamps()
    {
        $adapter = Mockery::mock(AdapterInterface::class);

        $adapter->shouldReceive('createModel')
                ->andReturn(true);

        Model::setAdapter($adapter);

        $newModel = new TestModel2();
        $newModel->id = 1;
        $newModel->id2 = 2;
        $newModel->required = 25;
        $this->assertTrue($newModel->create());
        $this->assertEquals(time(), $newModel->created_at->timestamp);
        $this->assertEquals(time(), $newModel->updated_at->timestamp);
    }

    public function testCreateMassAssignment()
    {
        $newModel = new TestModel2();

        $object = new stdClass();
        $object->test = true;

        $input = [
            'id' => 1,
            'id2' => 2,
            'required' => 'on',
            'validate' => '  SHOULDTRIMWS@EXAMPLE.COM ',
            'object' => $object,
        ];

        $adapter = Mockery::mock(AdapterInterface::class);
        $adapter->shouldReceive('createModel')
                ->andReturnUsing(function ($model, $args) use ($newModel, $object) {
                    $this->assertEquals($newModel, $model);

                    $this->assertTrue(isset($args['created_at']));
                    $this->assertTrue(isset($args['updated_at']));
                    unset($args['updated_at']);
                    unset($args['created_at']);

                    $expected = [
                        'id' => 1,
                        'id2' => 2,
                        'required' => true,
                        'validate' => 'shouldtrimws@example.com',
                        'default' => 'some default value',
                        'hidden' => false,
                        'array' => [
                            'tax' => '%',
                            'discounts' => false,
                            'shipping' => false,
                        ],
                        'object' => $object,
                        'person_id' => 20,
                     ];
                    $this->assertEquals($expected, $args);

                    return true;
                })
                ->once();

        Model::setAdapter($adapter);

        $this->assertTrue($newModel->create($input));
    }

    public function testCreateMassAssignmentFail()
    {
        $this->setExpectedException(MassAssignmentException::class);

        $input = [
            'array' => 'test',
        ];

        $newModel = new TestModel();
        $newModel->create($input);
    }

    public function testCreateWithAssignedId()
    {
        $newModel = new TestModel();

        $adapter = Mockery::mock(AdapterInterface::class);

        $adapter->shouldReceive('createModel')
                ->andReturn(true);

        Model::setAdapter($adapter);

        $newModel->id = 100;
        $this->assertTrue($newModel->create());
        $this->assertEquals(100, $newModel->id());
    }

    public function testCreateFailWithId()
    {
        $this->setExpectedException(BadMethodCallException::class);

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
        $adapter = Mockery::mock(AdapterInterface::class);

        $adapter->shouldReceive('createModel')
                ->andReturn(true);

        $adapter->shouldReceive('getCreatedID')
                ->andReturn(1);

        Model::setAdapter($adapter);

        TestModel::created(function (ModelEvent $event) {
            $event->stopPropagation();
        });

        $newModel = new TestModel();
        $this->assertFalse($newModel->create());
    }

    public function testCreateSavingListenerFail()
    {
        TestModel::saving(function (ModelEvent $event) {
            $event->stopPropagation();
        });

        $newModel = new TestModel();
        $this->assertFalse($newModel->create());
    }

    public function testCreateSavedListenerFail()
    {
        $adapter = Mockery::mock(AdapterInterface::class);

        $adapter->shouldReceive('createModel')
                ->andReturn(true);

        $adapter->shouldReceive('getCreatedID')
                ->andReturn(1);

        Model::setAdapter($adapter);

        TestModel::saved(function (ModelEvent $event) {
            $event->stopPropagation();
        });

        $newModel = new TestModel();
        $this->assertFalse($newModel->create());
    }

    public function testCreateNotUnique()
    {
        $query = TestModel2::query();
        TestModel2::setQuery($query);

        $adapter = Mockery::mock(AdapterInterface::class);

        $adapter->shouldReceive('totalRecords')
                ->andReturn(1);

        Model::setAdapter($adapter);

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
        $adapter = Mockery::mock(AdapterInterface::class);

        $adapter->shouldReceive('createModel')
                ->andReturn(false);

        Model::setAdapter($adapter);

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

        $adapter = Mockery::mock(AdapterInterface::class);

        $adapter->shouldReceive('updateModel')
                ->withArgs([$model, ['answer' => 42]])
                ->andReturn(true);

        Model::setAdapter($adapter);

        $model->answer = 42;

        $this->assertTrue($model->set());
        $this->assertEquals(42, $model->answer);
    }

    public function testSetFromSave()
    {
        $model = new TestModel();
        $model->refreshWith(['id' => 10]);

        $adapter = Mockery::mock(AdapterInterface::class);

        $adapter->shouldReceive('updateModel')
                ->withArgs([$model, [
                    'answer' => 42,
                    'extra' => true, ]])
                ->andReturn(true);

        Model::setAdapter($adapter);

        $model->answer = 42;
        $model->extra = true;
        $this->assertTrue($model->save());
    }

    public function testSetAutoTimestamps()
    {
        $model = new TestModel2();
        $model->refreshWith(['id' => 10]);

        $adapter = Mockery::mock(AdapterInterface::class);

        $adapter->shouldReceive('updateModel')
                ->andReturn(true);

        Model::setAdapter($adapter);

        $model->required = true;
        $this->assertTrue($model->set());
        $this->assertEquals(time(), $model->updated_at->timestamp);
    }

    public function testSetMassAssignment()
    {
        $model = new TestModel();
        $model->refreshWith(['id' => 11]);

        $adapter = Mockery::mock(AdapterInterface::class);

        $adapter->shouldReceive('updateModel')
                ->withArgs([$model, ['answer' => 'hello', 'mutator' => 'BLAH', 'relation' => 0]])
                ->andReturn(true)
                ->once();

        Model::setAdapter($adapter);

        $this->assertTrue($model->set([
            'answer' => ['hello', 'hello'],
            'relation' => '',
            'mutator' => 'blah',
        ]));
    }

    public function testSetMassAssignmentFail()
    {
        $this->setExpectedException(MassAssignmentException::class);

        $input = [
            'protected' => 'test',
        ];

        $model = new TestModel2();
        $model->refreshWith(['id' => 11]);
        $model->set($input);
    }

    public function testSetFail()
    {
        $model = new TestModel();
        $model->refreshWith(['id' => 10]);

        $adapter = Mockery::mock(AdapterInterface::class);
        $adapter->shouldReceive('updateModel')
                ->andReturn(false);

        Model::setAdapter($adapter);

        $model->answer = 42;

        $this->assertFalse($model->set());
    }

    public function testSetFailWithNoId()
    {
        $this->setExpectedException(BadMethodCallException::class);

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
        $adapter = Mockery::mock(AdapterInterface::class);

        $adapter->shouldReceive('updateModel')
                ->andReturn(true);

        Model::setAdapter($adapter);

        TestModel::updated(function (ModelEvent $event) {
            $event->stopPropagation();
        });

        $model = new TestModel();
        $model->refreshWith(['id' => 100]);
        $model->answer = 42;
        $this->assertFalse($model->set());
    }

    public function testUpdateSavingListenerFail()
    {
        TestModel::saving(function (ModelEvent $event) {
            $event->stopPropagation();
        });

        $model = new TestModel();
        $model->refreshWith(['id' => 100]);
        $model->answer = 42;
        $this->assertFalse($model->set());
    }

    public function testUpdateSavedListenerFail()
    {
        $adapter = Mockery::mock(AdapterInterface::class);

        $adapter->shouldReceive('updateModel')
                ->andReturn(true);

        Model::setAdapter($adapter);

        TestModel::saved(function (ModelEvent $event) {
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

        $adapter = Mockery::mock(AdapterInterface::class);

        $adapter->shouldReceive('totalRecords')
                ->andReturn(0);

        $adapter->shouldReceive('updateModel')
                ->andReturn(true);

        Model::setAdapter($adapter);

        $model = new TestModel2();
        $model->refreshWith(['id' => 12, 'unique' => 'different']);
        $model->unique = 'works';
        $model->required = 'required';
        $this->assertTrue($model->set());

        // validate query where statement
        $this->assertEquals(['unique' => 'works'], $query->getWhere());
    }

    public function testSetUniqueSkip()
    {
        $adapter = Mockery::mock(AdapterInterface::class);

        $adapter->shouldReceive('updateModel')
                ->andReturn(true);

        Model::setAdapter($adapter);

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

        $adapter = Mockery::mock(AdapterInterface::class);
        $adapter->shouldReceive('deleteModel')
                ->withArgs([$model])
                ->andReturn(true);
        Model::setAdapter($adapter);

        $this->assertTrue($model->delete());
        $this->assertFalse($model->persisted());
    }

    public function testDeleteWithNoId()
    {
        $this->setExpectedException(BadMethodCallException::class);

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
        $adapter = Mockery::mock(AdapterInterface::class);

        $adapter->shouldReceive('deleteModel')
                ->andReturn(true);

        Model::setAdapter($adapter);

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

        $adapter = Mockery::mock(AdapterInterface::class);
        $adapter->shouldReceive('deleteModel')
                ->withArgs([$model])
                ->andReturn(false);
        Model::setAdapter($adapter);

        $this->assertFalse($model->delete());
        $this->assertTrue($model->persisted());
    }

    /////////////////////////////
    // Queries
    /////////////////////////////

    public function testQuery()
    {
        $query = TestModel::query();

        $this->assertInstanceOf(Query::class, $query);
        $this->assertInstanceOf(TestModel::class, $query->getModel());
    }

    public function testQueryStatic()
    {
        $query = TestModel::where(['name' => 'Bob']);

        $this->assertInstanceOf(Query::class, $query);
    }

    public function testFind()
    {
        $adapter = Mockery::mock(AdapterInterface::class);

        $adapter->shouldReceive('queryModels')
                ->andReturnUsing(function ($query) {
                    $this->assertEquals(['id' => 100], $query->getWhere());

                    return [['id' => 100, 'answer' => 42]];
                })
                ->once();

        Model::setAdapter($adapter);

        $model = TestModel::find(100);
        $this->assertInstanceOf('TestModel', $model);
        $this->assertEquals(100, $model->id());
        $this->assertEquals(42, $model->answer);
    }

    public function testFindFail()
    {
        $adapter = Mockery::mock(AdapterInterface::class);

        $adapter->shouldReceive('queryModels')
                ->andReturn([])
                ->once();

        Model::setAdapter($adapter);

        $this->assertNull(TestModel::find(101));
    }

    public function testFindOrFail()
    {
        $adapter = Mockery::mock(AdapterInterface::class);

        $adapter->shouldReceive('queryModels')
                ->andReturn([['id' => 100, 'answer' => 42]])
                ->once();

        Model::setAdapter($adapter);

        $model = TestModel::findOrFail(100);
        $this->assertInstanceOf('TestModel', $model);
        $this->assertEquals(100, $model->id());
        $this->assertEquals(42, $model->answer);
    }

    public function testFindOrFailNotFound()
    {
        $this->setExpectedException(NotFoundException::class);

        $adapter = Mockery::mock(AdapterInterface::class);

        $adapter->shouldReceive('queryModels')
                ->andReturn([])
                ->once();

        Model::setAdapter($adapter);

        $this->assertNull(TestModel::findOrFail(101));
    }

    public function testTotalRecords()
    {
        $query = TestModel2::query();
        TestModel2::setQuery($query);

        $adapter = Mockery::mock(AdapterInterface::class);

        $adapter->shouldReceive('totalRecords')
                ->andReturn(1);

        Model::setAdapter($adapter);

        $this->assertEquals(1, TestModel2::totalRecords(['name' => 'John']));

        $this->assertEquals(['name' => 'John'], $query->getWhere());
    }

    public function testTotalRecordsNoCriteria()
    {
        $query = TestModel2::query();
        TestModel2::setQuery($query);

        $adapter = Mockery::mock(AdapterInterface::class);

        $adapter->shouldReceive('totalRecords')
                ->andReturn(2);

        Model::setAdapter($adapter);

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
        $this->assertEquals('TestModel2', $relation->getForeignModel());
        $this->assertEquals('test_model_id', $relation->getForeignKey());
        $this->assertEquals('id', $relation->getLocalKey());
        $this->assertEquals($model, $relation->getLocalModel());
    }

    public function testBelongsTo()
    {
        $model = new TestModel(['test_model2_id' => 1]);

        $relation = $model->belongsTo('TestModel2');

        $this->assertInstanceOf('Pulsar\Relation\BelongsTo', $relation);
        $this->assertEquals('TestModel2', $relation->getForeignModel());
        $this->assertEquals('id', $relation->getForeignKey());
        $this->assertEquals('test_model2_id', $relation->getLocalKey());
        $this->assertEquals($model, $relation->getLocalModel());
    }

    public function testHasMany()
    {
        $model = new TestModel();

        $relation = $model->hasMany('TestModel2');

        $this->assertInstanceOf('Pulsar\Relation\HasMany', $relation);
        $this->assertEquals('TestModel2', $relation->getForeignModel());
        $this->assertEquals('test_model_id', $relation->getForeignKey());
        $this->assertEquals('id', $relation->getLocalKey());
        $this->assertEquals($model, $relation->getLocalModel());
    }

    public function testBelongsToMany()
    {
        $model = new TestModel(['test_model2_id' => 1]);

        $relation = $model->belongsToMany('TestModel2');

        $this->assertInstanceOf('Pulsar\Relation\BelongsToMany', $relation);
        $this->assertEquals('TestModel2', $relation->getForeignModel());
        $this->assertEquals('id', $relation->getForeignKey());
        $this->assertEquals('test_model2_id', $relation->getLocalKey());
        $this->assertEquals($model, $relation->getLocalModel());
        $this->assertEquals('TestModelTestModel2', $relation->getTablename());
    }

    public function testIsRelationship()
    {
        $this->assertTrue(TestModel2::isRelationship('person'));
        $this->assertFalse(TestModel2::isRelationship('id'));
        $this->assertFalse(TestModel2::isRelationship('person_id'));
    }

    public function testGetRelationship()
    {
        $adapter = Mockery::mock(AdapterInterface::class);

        $person = new Person();
        $adapter->shouldReceive('queryModels')
                ->andReturnUsing(function ($query) {
                    return [['id' => $query->getWhere()['id']]];
                });

        Model::setAdapter($adapter);

        $model = new TestModel2();
        $model->person_id = '10';
        $person = $model->person;
        $this->assertInstanceOf('Person', $person);
        $this->assertEquals('10', $person->id);
    }

    public function testSetRelationship()
    {
        $this->setExpectedException(BadMethodCallException::class);

        $model = new TestModel2();
        $model->person = 'test';
    }

    public function testUnsetRelationship()
    {
        $this->setExpectedException(BadMethodCallException::class);

        $model = new TestModel2();
        unset($model->person);
    }

    /////////////////////////////
    // STORAGE
    /////////////////////////////

    public function testRefreshNotPersisted()
    {
        $this->setExpectedException(NotFoundException::class);

        $model = new TestModel2();
        $model->refresh();
    }

    public function testRefresh()
    {
        $model = new TestModel2();
        $model->refreshWith(['id' => 12, 'id2' => 13]);

        $adapter = Mockery::mock(AdapterInterface::class);

        $adapter->shouldReceive('queryModels')
                ->andReturnUsing(function ($query) {
                    $this->assertEquals(['id' => 12, 'id2' => 13], $query->getWhere());

                    return [['unique' => 'value']];
                })
                ->once();

        Model::setAdapter($adapter);

        $this->assertEquals($model, $model->refresh());
        $this->assertEquals('value', $model->unique);
    }

    public function testRefreshFail()
    {
        $adapter = Mockery::mock(AdapterInterface::class);

        $adapter->shouldReceive('queryModels')
               ->andReturn([]);

        Model::setAdapter($adapter);

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
