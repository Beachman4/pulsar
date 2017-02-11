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
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

require_once 'tests/test_models.php';

class CacheableTest extends PHPUnit_Framework_TestCase
{
    protected function tearDown()
    {
        CacheableModel::clearCachePool();
    }

    private function getCache()
    {
        return new ArrayAdapter();
    }

    public function testGetCachePool()
    {
        $cache = $this->getCache();

        CacheableModel::setCachePool($cache);
        for ($i = 0; $i < 5; ++$i) {
            $model = new CacheableModel();
            $this->assertEquals($cache, $model->getCachePool());
        }
    }

    public function testNoPool()
    {
        CacheableModel::clearCachePool();
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
        $this->assertEquals('models.cacheablemodel.5', $model->getCacheKey());
    }

    public function testGetCacheItem()
    {
        $cache = $this->getCache();
        CacheableModel::setCachePool($cache);

        $model = new CacheableModel();
        $model->refreshWith(['id' => 5]);
        $item = $model->getCacheItem();
        $this->assertInstanceOf(CacheItemInterface::class, $item);
        $this->assertEquals('models.cacheablemodel.5', $item->getKey());

        $model = new CacheableModel();
        $model->refreshWith(['id' => 6]);
        $item = $model->getCacheItem();
        $this->assertInstanceOf(CacheItemInterface::class, $item);
        $this->assertEquals('models.cacheablemodel.6', $item->getKey());
    }

    public function testFind()
    {
        $cache = $this->getCache();
        CacheableModel::setCachePool($cache);

        $adapter = Mockery::mock(AdapterInterface::class);

        $adapter->shouldReceive('queryModels')
                ->andReturn([['id' => 100, 'answer' => 42]])
                ->once();

        CacheableModel::setAdapter($adapter);

        // the first find() call should be a miss
        // this triggers a load from the data layer
        $model = CacheableModel::find(100);
        $this->assertInstanceOf(CacheableModel::class, $model);
        $this->assertEquals(100, $model->id());

        // value should now be cached
        $item = $cache->getItem($model->getCacheKey());
        $value = $item->get();
        $this->assertTrue($item->isHit());
        $expected = ['id' => 100, 'answer' => 42];
        $this->assertEquals($expected, $value);

        // the next find() call should be a hit from the cache
        $model = CacheableModel::find(100);
        $this->assertInstanceOf(CacheableModel::class, $model);
        $this->assertEquals(100, $model->id());
        $this->assertEquals(42, $model->answer);
    }

    public function testCache()
    {
        $cache = $this->getCache();
        CacheableModel::setCachePool($cache);

        $model = new CacheableModel(['id' => 102, 'answer' => 42]);

        // cache
        $this->assertEquals($model, $model->cache());
        $item = $cache->getItem($model->getCacheKey());
        $value = $item->get();
        $this->assertTrue($item->isHit());

        // clear the cache
        $this->assertEquals($model, $model->clearCache());
        $item = $cache->getItem($model->getCacheKey());
        $value = $item->get();
        $this->assertFalse($item->isHit());
    }
}
