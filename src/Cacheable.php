<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */

namespace Pulsar;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

trait Cacheable
{
    /**
     * @staticvar CacheItemPoolInterface
     */
    private static $cachePool;

    /**
     * @staticvar array
     */
    private static $cachePrefix = [];

    /**
     * @var CacheItemInterface
     */
    private $_cacheItem;

    /**
     * Sets the default cache instance for all models.
     *
     * @param CacheItemPoolInterface $pool
     */
    public static function setCachePool(CacheItemPoolInterface $pool)
    {
        self::$cachePool = $pool;
    }

    /**
     * Clears the default cache instance for all models.
     */
    public static function clearCachePool()
    {
        self::$cachePool = null;
    }

    /**
     * Returns the cache instance.
     *
     * @return CacheItemPoolInterface|null
     */
    public function getCachePool()
    {
        return self::$cachePool;
    }

    public static function find($id)
    {
        if (self::$cachePool) {
            // Attempt to load the model from the caching layer first.
            // If that fails, then fall through to the data layer.
            $model = static::buildFromId($id);
            $item = $model->getCacheItem();
            $values = $item->get();

            if ($item->isHit()) {
                // load the values directly instead of using
                // refreshWith() to prevent triggering another
                // cache call
                $model->_persisted = true;
                $model->_values = $values;

                return $model;
            }
        }

        return parent::find($id);
    }

    public function refreshWith(array $values)
    {
        return parent::refreshWith($values)->cache();
    }

    /**
     * Clears the cache for this model.
     *
     * @return self
     */
    public function clearCache()
    {
        if (self::$cachePool) {
            $k = $this->getCacheKey();
            self::$cachePool->deleteItem($k);
        }

        return $this;
    }

    /**
     * Returns the cache TTL.
     *
     * @return int|null
     */
    public function getCacheTTL()
    {
        return (property_exists($this, 'cacheTTL')) ? static::$cacheTTL : 86400; // default = 1 day
    }

    /**
     * Returns the cache key for this model.
     *
     * @return string
     */
    public function getCacheKey()
    {
        $k = get_called_class();
        if (!isset(self::$cachePrefix[$k])) {
            self::$cachePrefix[$k] = 'models.'.strtolower(static::modelName());
        }

        return self::$cachePrefix[$k].'.'.$this->id();
    }

    /**
     * Returns the cache item for this model.
     *
     * @return CacheItemInterface|null
     */
    public function getCacheItem()
    {
        if (!self::$cachePool) {
            return;
        }

        if (!$this->_cacheItem) {
            $k = $this->getCacheKey();
            $this->_cacheItem = self::$cachePool->getItem($k);
        }

        return $this->_cacheItem;
    }

    /**
     * Caches the entire model.
     *
     * @return self
     */
    public function cache()
    {
        if (!self::$cachePool || count($this->_values) == 0) {
            return $this;
        }

        // cache the local properties
        $item = $this->getCacheItem();
        $item->set($this->_values)
             ->expiresAfter($this->getCacheTTL());

        self::$cachePool->save($item);

        return $this;
    }
}
