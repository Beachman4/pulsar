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
use Pulsar\Relation\BelongsTo;

class BelongsToTest extends PHPUnit_Framework_TestCase
{
    public static $adapter;

    public static function setUpBeforeClass()
    {
        self::$adapter = Mockery::mock(AdapterInterface::class);
        Model::setAdapter(self::$adapter);
    }

    public function testInitQuery()
    {
        $post = new Post(['category_id' => 10]);

        $relation = new BelongsTo($post, 'category_id', 'Category', 'id');

        $query = $relation->getQuery();
        $this->assertInstanceOf(Category::class, $query->getModel());
        $this->assertEquals(['id' => 10], $query->getWhere());
        $this->assertEquals(1, $query->getLimit());
    }

    public function testGetResults()
    {
        $post = new Post(['category_id' => 10]);

        $relation = new BelongsTo($post, 'category_id', 'Category', 'id');

        self::$adapter->shouldReceive('queryModels')
                      ->andReturn([['id' => 11]]);

        $result = $relation->getResults();
        $this->assertInstanceOf(Category::class, $result);
        $this->assertEquals(11, $result->id());
    }

    public function testEmpty()
    {
        $post = new Post(['category_id' => null]);

        $relation = new BelongsTo($post, 'category_id', 'Category', 'id');

        $this->assertNull($relation->getResults());
    }

    public function testSave()
    {
        $post = new Post(['category_id' => null]);

        $relation = new BelongsTo($post, 'category_id', 'Category', 'id');

        self::$adapter->shouldReceive('createModel')
                      ->andReturn(true);

        self::$adapter->shouldReceive('getCreatedID')
                      ->andReturn(1);

        $category = new Category(['test' => true]);

        $this->assertEquals($category, $relation->save($category));

        $this->assertEquals(true, $category->test);
        $this->assertTrue($category->persisted());

        $this->assertEquals(1, $post->category_id);
        $this->assertTrue($post->persisted());
    }

    public function testCreate()
    {
        $post = new Post(['category_id' => null]);

        $relation = new BelongsTo($post, 'category_id', 'Category', 'id');

        self::$adapter->shouldReceive('createModel')
                      ->andReturn(true);

        self::$adapter->shouldReceive('getCreatedID')
                      ->andReturn(1);

        $category = $relation->create(['test' => true]);

        $this->assertInstanceOf(Category::class, $category);
        $this->assertEquals(true, $category->test);
        $this->assertTrue($category->persisted());

        $this->assertEquals(1, $post->category_id);
        $this->assertTrue($post->persisted());
    }

    public function testAttach()
    {
        $post = new Post(['category_id' => null]);

        $relation = new BelongsTo($post, 'category_id', 'Category', 'id');

        $category = new Category(['id' => 10]);

        $this->assertEquals($relation, $relation->attach($category));
        $this->assertEquals(10, $post->category_id);
        $this->assertTrue($post->persisted());
    }

    public function testDetach()
    {
        $post = new Post(['category_id' => 10]);

        $relation = new BelongsTo($post, 'category_id', 'Category', 'id');

        $this->assertEquals($relation, $relation->detach());
        $this->assertNull($post->category_id);
        $this->assertTrue($post->persisted());
    }
}
