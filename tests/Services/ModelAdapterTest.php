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
use Pulsar\Services\ModelAdapter;
use Pulsar\Model;

class ModelAdapterTest extends PHPUnit_Framework_TestCase
{
    public function testInvoke()
    {
        $config = [
            'models' => [
                'adapter' => 'Pulsar\Adapter\DatabaseAdapter',
            ],
        ];
        $app = new Application($config);
        $service = new ModelAdapter($app);
        $this->assertInstanceOf('Pulsar\Adapter\DatabaseAdapter', Model::getAdapter());

        $adapter = $service($app);
        $this->assertInstanceOf('Pulsar\Adapter\DatabaseAdapter', $adapter);

        $locale = Model::getLocale();
        $this->assertEquals($app['locale'], $locale);
    }
}
