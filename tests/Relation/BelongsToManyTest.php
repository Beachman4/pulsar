<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @see http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
use Pulsar\Driver\DriverInterface;
use Pulsar\Model;
use Pulsar\Relation\BelongsToMany;

class BelongsToManyTest extends PHPUnit_Framework_TestCase
{
    public static function setUpBeforeClass()
    {
        $driver = Mockery::mock(DriverInterface::class);

        $driver->shouldReceive('queryModels')
               ->andReturn([['id' => 'result'], ['id' => 'result2']]);

        Model::setDriver($driver);
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
            $this->assertInstanceOf(TestModel::class, $m);
        }

        $this->assertEquals('result', $result[0]->id());
        $this->assertEquals('result2', $result[1]->id());
    }
}
