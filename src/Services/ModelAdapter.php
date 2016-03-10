<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
namespace Pulsar\Services;

use Pulsar\Model;
use Pulsar\Validator;

class ModelAdapter
{
    /**
     * @var Pulsar\Adapter\AdapterInterface
     */
    private $adapter;

    public function __construct($app)
    {
        // make the locale available to models
        Model::setLocale($app['locale']);

        // set up the model adapter
        $config = $app['config'];
        $class = $config->get('models.adapter');
        $this->adapter = new $class($app);
        Model::setAdapter($this->adapter);

        // used for password hasing
        Validator::configure(['salt' => $config->get('app.salt')]);
    }

    public function __invoke()
    {
        return $this->adapter;
    }
}
