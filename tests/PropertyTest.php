<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
use Carbon\Carbon;
use Pulsar\Property;

class PropertyTest extends PHPUnit_Framework_TestCase
{
    public function testToString()
    {
        $this->assertEquals('string', Property::to_string('string'));
        $this->assertEquals('123', Property::to_string(123));
    }

    public function testToInteger()
    {
        $this->assertEquals(123, Property::to_integer(123));
        $this->assertEquals(123, Property::to_integer('123'));
    }

    public function testToFloat()
    {
        $this->assertEquals(1.23, Property::to_float(1.23));
        $this->assertEquals(123.0, Property::to_float('123'));
    }

    public function testToBoolean()
    {
        $this->assertTrue(Property::to_boolean(true));
        $this->assertTrue(Property::to_boolean('1'));
        $this->assertFalse(Property::to_boolean(false));
    }

    public function testToDate()
    {
        $date = Property::to_date(123, 'U');
        $this->assertInstanceOf('Carbon\Carbon', $date);
        $this->assertEquals(123, $date->timestamp);

        $date = new Carbon();
        $this->assertEquals($date, Property::to_date($date, 'U'));

        $date = Property::to_date('2016-01-20 00:00:00', 'Y-m-d H:i:s');
        $this->assertInstanceOf('Carbon\Carbon', $date);
        $this->assertEquals(1453248000, $date->timestamp);
    }

    public function testToArray()
    {
        $this->assertEquals(['test' => true], Property::to_array('{"test":true}'));
        $this->assertEquals(['test' => true], Property::to_array(['test' => true]));
    }

    public function testToObject()
    {
        $expected = new stdClass();
        $expected->test = true;
        $this->assertEquals($expected, Property::to_object('{"test":true}'));
        $this->assertEquals($expected, Property::to_object($expected));
    }
}
