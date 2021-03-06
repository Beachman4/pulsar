<?php

/**
 * @package Pulsar
 * @author Jared King <j@jaredtking.com>
 * @link http://jaredtking.com
 * @copyright 2015 Jared King
 * @license MIT
 */

use Infuse\Utility;
use Pulsar\Validate;

class ValidateTest extends PHPUnit_Framework_TestCase
{
    public function testAlpha()
    {
        $s = 'abc';
        $this->assertTrue(Validate::is($s, 'alpha'));
        $s = ')S*F#$)S*';
        $this->assertFalse(Validate::is($s, 'alpha'));
        $s = 'abcde';
        $this->assertTrue(Validate::is($s, 'alpha:5'));
        $s = 'abc';
        $this->assertFalse(Validate::is($s, 'alpha:5'));
    }

    public function testAlphaNumeric()
    {
        $s = 'abc1234';
        $this->assertTrue(Validate::is($s, 'alpha_numeric'));
        $s = ')S*F#$)S*';
        $this->assertFalse(Validate::is($s, 'alpha_numeric'));
        $s = 'a2cde';
        $this->assertTrue(Validate::is($s, 'alpha_numeric:5'));
        $s = 'a2c';
        $this->assertFalse(Validate::is($s, 'alpha_numeric:5'));
    }

    public function testAlphaDash()
    {
        $s = 'abc-1234';
        $this->assertTrue(Validate::is($s, 'alpha_dash'));
        $s = ')S*F#$)S*';
        $this->assertFalse(Validate::is($s, 'alpha_dash'));
        $s = 'r2-d2';
        $this->assertTrue(Validate::is($s, 'alpha_dash:5'));
        $this->assertFalse(Validate::is($s, 'alpha_dash:7'));
    }

    public function testBoolean()
    {
        $s = '1';
        $this->assertTrue(Validate::is($s, 'boolean'));
        $this->assertTrue($s);
        $s = '0';
        $this->assertTrue(Validate::is($s, 'boolean'));
        $this->assertFalse($s);
    }

    public function testEmail()
    {
        $s = 'test@example.com';
        $this->assertTrue(Validate::is($s, 'email'));
        $s = 'test';
        $this->assertFalse(Validate::is($s, 'email'));
    }

    public function testEnum()
    {
        $s = 'blue';
        $this->assertTrue(Validate::is($s, 'enum:red,orange,yellow,green,blue,violet'));
        $s = 'Paris';
        $this->assertFalse(Validate::is($s, 'enum:Austin,Dallas,OKC,Tulsa'));
    }

    public function testDate()
    {
        date_default_timezone_set('UTC');
        $s = 'today';
        $this->assertTrue(Validate::is($s, 'date'));
        $s = '09/17/2013';
        $this->assertTrue(Validate::is($s, 'date'));
        $s = 'doesnotwork';
        $this->assertFalse(Validate::is($s, 'date'));
    }

    public function testIp()
    {
        $s = '127.0.0.1';
        $this->assertTrue(Validate::is($s, 'ip'));
        $s = 'doesnotwork';
        $this->assertFalse(Validate::is($s, 'ip'));
    }

    public function testMatching()
    {
        $match = 'notarray';
        $this->assertTrue(Validate::is($match, 'matching'));

        $match = ['test', 'test'];
        $this->assertTrue(Validate::is($match, 'matching'));
        $this->assertEquals('test', $match);

        $match = ['test', 'test', 'test', 'test'];
        $this->assertTrue(Validate::is($match, 'matching'));
        $this->assertEquals('test', $match);

        $notmatching = ['test', 'nope'];
        $this->assertFalse(Validate::is($notmatching, 'matching'));
        $this->assertEquals(['test', 'nope'], $notmatching);
    }

    public function testNumeric()
    {
        $s = 12345.22;
        $this->assertTrue(Validate::is($s, 'numeric'));
        $s = '1234';
        $this->assertTrue(Validate::is($s, 'numeric'));
        $s = 'notanumber';
        $this->assertFalse(Validate::is($s, 'numeric'));
        $s = 12345.22;
        $this->assertTrue(Validate::is($s, 'numeric:double'));
        $s = 12345.22;
        $this->assertFalse(Validate::is($s, 'numeric:int'));
    }

    public function testPassword()
    {
        $salt = 'saltvalue';
        Validate::configure(['salt' => $salt]);

        $password = 'testpassword';
        $this->assertTrue(Validate::is($password, 'password:8'));
        $this->assertEquals(Utility::encryptPassword('testpassword', $salt), $password);

        $invalid = '...';
        $this->assertFalse(Validate::is($invalid, 'password:8'));
    }

    public function testRange()
    {
        $s = -1;
        $this->assertTrue(Validate::is($s, 'range'));
        $this->assertTrue(Validate::is($s, 'range:-1'));
        $this->assertTrue(Validate::is($s, 'range:-1:100'));

        $s = 100;
        $this->assertFalse(Validate::is($s, 'range:101'));
        $this->assertFalse(Validate::is($s, 'range:0:99'));
    }

    public function testRequired()
    {
        $s = 'ok';
        $this->assertTrue(Validate::is($s, 'required'));
        $s = '';
        $this->assertFalse(Validate::is($s, 'required'));
    }

    public function testString()
    {
        $s = 'thisisok';
        $this->assertTrue(Validate::is($s, 'string'));
        $this->assertTrue(Validate::is($s, 'string:5'));
        $this->assertTrue(Validate::is($s, 'string:1:8'));
        $this->assertTrue(Validate::is($s, 'string:0:9'));
        $this->assertFalse(Validate::is($s, 'string:9'));
        $this->assertFalse(Validate::is($s, 'string:1:7'));

        $s = new stdClass();
        $this->assertFalse(Validate::is($s, 'string'));
    }

    public function testTimeZone()
    {
        $s = 'America/Chicago';
        $this->assertTrue(Validate::is($s, 'time_zone'));
        $s = 'anywhere';
        $this->assertFalse(Validate::is($s, 'time_zone'));
    }

    public function testTimestamp()
    {
        $s = $t = time();
        $this->assertTrue(Validate::is($s, 'timestamp'));
        $this->assertEquals($t, $s);

        $s = 'today';
        $this->assertTrue(Validate::is($s, 'timestamp'));
        $this->assertEquals(strtotime('today'), $s);
    }

    public function testDbTimestamp()
    {
        $s = mktime(23, 34, 20, 4, 18, 2012);
        $this->assertTrue(Validate::is($s, 'db_timestamp'));
        $this->assertEquals('2012-04-18 23:34:20', $s);

        $s = 'test';
        $this->assertFalse(Validate::is($s, 'db_timestamp'));
    }

    public function testUrl()
    {
        $s = 'http://example.com';
        $this->assertTrue(Validate::is($s, 'url'));
        $s = 'notaurl';
        $this->assertFalse(Validate::is($s, 'url'));
    }

    public function testMultipleRequirements()
    {
        $t = ['test', 'test'];
        $this->assertTrue(Validate::is($t, 'matching|string:2'));
        $this->assertEquals('test', $t);
    }

    public function testKeyValueRequirements()
    {
        $test = [
            'test' => ['test', 'test'],
            'test2' => 'alphanumer1c',
        ];

        $requirements = [
            'test' => 'matching|string:2',
            'test2' => 'alpha_numeric',
        ];

        $this->assertTrue(Validate::is($test, $requirements));
        $this->assertEquals('test', $test['test']);
    }
}
