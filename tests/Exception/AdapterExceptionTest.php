<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
use Pulsar\Exception\AdapterException;

class AdapterExceptionTest extends PHPUnit_Framework_TestCase
{
    public function testException()
    {
        $e = new Exception();
        $ex = new AdapterException();
        $ex->setException($e);
        $this->assertEquals($e, $ex->getException());
    }
}
