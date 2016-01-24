<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
use Stash\Pool;

require_once 'tests/test_models.php';

class CacheablelTest extends PHPUnit_Framework_TestCase
{
    protected function tearDown()
    {
        CacheableModel::setCachePool(null);
    }

    public function testGetCachePool()
    {
        $cache = Mockery::mock('Stash\Pool');

        CacheableModel::setCachePool($cache);
        for ($i = 0; $i < 5; ++$i) {
            $model = new CacheableModel();
            $this->assertEquals($cache, $model->getCachePool());
        }
    }

    public function testNoPool()
    {
        CacheableModel::setCachePool(null);
        $model = new CacheableModel();
        $model->refreshWith(['id' => 5]);
        $this->assertNull($model->getCachePool());
        $this->assertNull($model->getCacheItem());
        $this->assertEquals($model, $model->refreshWith(['id' => 5, 'answer' => 42]));
    }

    public function testGetCacheTTL()
    {
        $model = new CacheableModel();
        $this->assertEquals(10, $model->getCacheTTL());
    }

    public function testGetCacheKey()
    {
        $model = new CacheableModel();
        $model->refreshWith(['id' => 5]);
        $this->assertEquals('models/cacheablemodel/5', $model->getCacheKey());
    }

    public function testGetCacheItem()
    {
        $cache = new Pool();
        CacheableModel::setCachePool($cache);

        $model = new CacheableModel();
        $model->refreshWith(['id' => 5]);
        $item = $model->getCacheItem();
        $this->assertInstanceOf('Stash\Item', $item);
        $this->assertEquals('models/cacheablemodel/5', $item->getKey());

        $model = new CacheableModel();
        $model->refreshWith(['id' => 6]);
        $item = $model->getCacheItem();
        $this->assertInstanceOf('Stash\Item', $item);
        $this->assertEquals('models/cacheablemodel/6', $item->getKey());
    }

    public function testFind()
    {
        $cache = new Pool();
        CacheableModel::setCachePool($cache);

        $driver = Mockery::mock('Pulsar\Driver\DriverInterface');

        $driver->shouldReceive('queryModels')
               ->andReturn([['id' => 100, 'answer' => 42]])
               ->once();

        CacheableModel::setDriver($driver);

        // the first find() call should be a miss
        // this triggers a load from the data layer
        $model = CacheableModel::find(100);
        $this->assertInstanceOf('CacheableModel', $model);
        $this->assertEquals(100, $model->id());

        // value should now be cached
        $item = $cache->getItem($model->getCacheKey());
        $value = $item->get();
        $this->assertFalse($item->isMiss());
        $expected = ['id' => 100, 'answer' => 42];
        $this->assertEquals($expected, $value);

        // the next find() call should be a hit from the cache
        $model = CacheableModel::find(100);
        $this->assertInstanceOf('CacheableModel', $model);
        $this->assertEquals(100, $model->id());
        $this->assertEquals(42, $model->answer);
    }

    public function testCache()
    {
        $cache = new Pool();
        CacheableModel::setCachePool($cache);

        $model = new CacheableModel(['id' => 102, 'answer' => 42]);

        // cache
        $this->assertEquals($model, $model->cache());
        $item = $cache->getItem($model->getCacheKey());
        $value = $item->get();
        $this->assertFalse($item->isMiss());

        // clear the cache
        $this->assertEquals($model, $model->clearCache());
        $value = $item->get();
        $this->assertTrue($item->isMiss());
    }
}
