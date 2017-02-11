<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
use Infuse\Application;
use Pulsar\Adapter\DatabaseAdapter;
use Pulsar\Services\ModelAdapter;
use Pulsar\Model;

class ModelAdapterTest extends PHPUnit_Framework_TestCase
{
    public function testInvoke()
    {
        $config = [
            'models' => [
                'adapter' => DatabaseAdapter::class,
            ],
        ];
        $app = new Application($config);
        $service = new ModelAdapter($app);
        $this->assertInstanceOf(DatabaseAdapter::class, Model::getAdapter());

        $adapter = $service($app);
        $this->assertInstanceOf(DatabaseAdapter::class, $adapter);

        $locale = Model::getLocale();
        $this->assertEquals($app['locale'], $locale);
    }
}
