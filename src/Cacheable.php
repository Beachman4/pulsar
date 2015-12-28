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

use Stash\Item;

trait Cacheable
{
    /**
     * @staticvar \Stash\Pool
     */
    private static $cachePool;

    /**
     * @staticvar array
     */
    private static $cachePrefix = [];

    /**
     * @var \Stash\Item
     */
    private $_cacheItem;

    public static function find($id)
    {
        if (self::$cachePool) {
            // Attempt to load the model from the caching layer first.
            // If that fails, then fall through to the data layer.
            $model = static::buildFromId($id);
            $item = $model->getCacheItem();
            $values = $item->get();

            if (!$item->isMiss()) {
                // load the values directly instead of using
                // refreshWith() to prevent triggering another
                // cache call
                $model->_exists = true;
                $model->_values = $values;

                return $model;
            }

            // If the cache was a miss, then lock down the
            // cache item, attempt to load the model from
            // the database, and then update the cache.
            // Stash calls this Stampede Protection.

            // NOTE Currently disabling Stampede Protection
            // because there is no way to unlock the item
            // if we fail to load the model, whether
            // due to a DB failure or non-existent record.
            // This is problematic with the Redis driver
            // because it will attempt to unlock the cache
            // item once the script shuts down and the
            // redis connection has closed.
            // $item->lock();
        }

        return parent::find($id);
    }

    public function refreshWith(array $values)
    {
        return parent::refreshWith($values)->cache();
    }

    public function clearCache()
    {
        if (self::$cachePool) {
            $this->getCacheItem()->clear();
        }

        return parent::clearCache();
    }

    /**
     * Sets the default cache instance used by new models.
     *
     * @param \Stash\Pool $pool
     */
    public static function setCachePool($pool)
    {
        self::$cachePool = $pool;
    }

    /**
     * Returns the cache instance.
     *
     * @return \Stash\Pool|false
     */
    public function getCachePool()
    {
        return self::$cachePool;
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
            self::$cachePrefix[$k] = 'models/'.strtolower(static::modelName());
        }

        return self::$cachePrefix[$k].'/'.$this->id();
    }

    /**
     * Returns the cache item for this model.
     *
     * @return \Stash\Item|null
     */
    public function getCacheItem()
    {
        if (!self::$cachePool) {
            return;
        }

        if (!$this->_cacheItem) {
            $this->_cacheItem = self::$cachePool->getItem($this->getCacheKey());
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
        $this->getCacheItem()
             ->set($this->_values, $this->getCacheTTL());

        return $this;
    }
}
