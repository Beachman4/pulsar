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
    public static function setUpBeforeClass()
    {
        $adapter = Mockery::mock('Pulsar\Adapter\AdapterInterface');

        $adapter->shouldReceive('queryModels')
                ->andReturn([['id' => 11], ['id' => 12]]);

        Model::setAdapter($adapter);
    }

    public function testInitQuery()
    {
        $model = new TestModel2();
        $model->test_model_id = 10;

        $relation = new BelongsToMany('TestModel', 'id', 'test_model_id', $model);

        $this->assertEquals(['id' => 10], $relation->getQuery()->getWhere());
    }

    public function testGetResults()
    {
        $model = new TestModel2();
        $model->test_model_id = 10;

        $relation = new BelongsToMany('TestModel', 'id', 'test_model_id', $model);

        $result = $relation->getResults();

        $this->assertCount(2, $result);

        foreach ($result as $m) {
            $this->assertInstanceOf('TestModel', $m);
        }

        $this->assertEquals(11, $result[0]->id());
        $this->assertEquals(12, $result[1]->id());
    }

    public function testEmpty()
    {
        $model = new TestModel2(['test_model_id' => null]);

        $relation = new BelongsToMany('TestModel', 'id', 'test_model_id', $model);

        $this->assertNull($relation->getResults());
    }
}
