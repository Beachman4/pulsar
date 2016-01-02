<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
use Infuse\Utility;
use Pulsar\Errors;
use Pulsar\Validate;

class ValidateTest extends PHPUnit_Framework_TestCase
{
    public function testAlpha()
    {
        $data = 'abc';
        $this->assertTrue(Validate::is($data, 'alpha'));
        $data = ')S*F#$)S*';
        $this->assertFalse(Validate::is($data, 'alpha'));
        $data = 'abcde';
        $this->assertTrue(Validate::is($data, 'alpha:5'));
        $data = 'abc';
        $this->assertFalse(Validate::is($data, 'alpha:5'));
    }

    public function testAlphaNumeric()
    {
        $data = 'abc1234';
        $this->assertTrue(Validate::is($data, 'alpha_numeric'));
        $data = ')S*F#$)S*';
        $this->assertFalse(Validate::is($data, 'alpha_numeric'));
        $data = 'a2cde';
        $this->assertTrue(Validate::is($data, 'alpha_numeric:5'));
        $data = 'a2c';
        $this->assertFalse(Validate::is($data, 'alpha_numeric:5'));
    }

    public function testAlphaDash()
    {
        $data = 'abc-1234';
        $this->assertTrue(Validate::is($data, 'alpha_dash'));
        $data = ')S*F#$)S*';
        $this->assertFalse(Validate::is($data, 'alpha_dash'));
        $data = 'r2-d2';
        $this->assertTrue(Validate::is($data, 'alpha_dash:5'));
        $this->assertFalse(Validate::is($data, 'alpha_dash:7'));
    }

    public function testBoolean()
    {
        $data = '1';
        $this->assertTrue(Validate::is($data, 'boolean'));
        $this->assertTrue($data);
        $data = '0';
        $this->assertTrue(Validate::is($data, 'boolean'));
        $this->assertFalse($data);
    }

    public function testEmail()
    {
        $data = 'test@example.com';
        $this->assertTrue(Validate::is($data, 'email'));
        $data = 'test';
        $this->assertFalse(Validate::is($data, 'email'));
    }

    public function testEnum()
    {
        $data = 'blue';
        $this->assertTrue(Validate::is($data, 'enum:red,orange,yellow,green,blue,violet'));
        $data = 'Paris';
        $this->assertFalse(Validate::is($data, 'enum:Austin,Dallas,OKC,Tulsa'));
    }

    public function testDate()
    {
        date_default_timezone_set('UTC');
        $data = 'today';
        $this->assertTrue(Validate::is($data, 'date'));
        $data = '09/17/2013';
        $this->assertTrue(Validate::is($data, 'date'));
        $data = 'doesnotwork';
        $this->assertFalse(Validate::is($data, 'date'));
    }

    public function testIp()
    {
        $data = '127.0.0.1';
        $this->assertTrue(Validate::is($data, 'ip'));
        $data = 'doesnotwork';
        $this->assertFalse(Validate::is($data, 'ip'));
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
        $data = 12345.22;
        $this->assertTrue(Validate::is($data, 'numeric'));
        $data = '1234';
        $this->assertTrue(Validate::is($data, 'numeric'));
        $data = 'notanumber';
        $this->assertFalse(Validate::is($data, 'numeric'));
        $data = 12345.22;
        $this->assertTrue(Validate::is($data, 'numeric:double'));
        $data = 12345.22;
        $this->assertFalse(Validate::is($data, 'numeric:int'));
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
        $data = -1;
        $this->assertTrue(Validate::is($data, 'range'));
        $this->assertTrue(Validate::is($data, 'range:-1'));
        $this->assertTrue(Validate::is($data, 'range:-1:100'));

        $data = 100;
        $this->assertFalse(Validate::is($data, 'range:101'));
        $this->assertFalse(Validate::is($data, 'range:0:99'));
    }

    public function testRequired()
    {
        $data = 'ok';
        $this->assertTrue(Validate::is($data, 'required'));
        $data = '';
        $this->assertFalse(Validate::is($data, 'required'));
    }

    public function testString()
    {
        $data = 'thisisok';
        $this->assertTrue(Validate::is($data, 'string'));
        $this->assertTrue(Validate::is($data, 'string:5'));
        $this->assertTrue(Validate::is($data, 'string:1:8'));
        $this->assertTrue(Validate::is($data, 'string:0:9'));
        $this->assertFalse(Validate::is($data, 'string:9'));
        $this->assertFalse(Validate::is($data, 'string:1:7'));

        $data = new stdClass();
        $this->assertFalse(Validate::is($data, 'string'));
    }

    public function testTimeZone()
    {
        $data = 'America/Chicago';
        $this->assertTrue(Validate::is($data, 'time_zone'));
        $data = 'anywhere';
        $this->assertFalse(Validate::is($data, 'time_zone'));
    }

    public function testTimestamp()
    {
        $data = $t = time();
        $this->assertTrue(Validate::is($data, 'timestamp'));
        $this->assertEquals($t, $data);

        $data = 'today';
        $this->assertTrue(Validate::is($data, 'timestamp'));
        $this->assertEquals(strtotime('today'), $data);
    }

    public function testDbTimestamp()
    {
        $data = mktime(23, 34, 20, 4, 18, 2012);
        $this->assertTrue(Validate::is($data, 'db_timestamp'));
        $this->assertEquals('2012-04-18 23:34:20', $data);

        $data = 'test';
        $this->assertFalse(Validate::is($data, 'db_timestamp'));
    }

    public function testUrl()
    {
        $data = 'http://example.com';
        $this->assertTrue(Validate::is($data, 'url'));
        $data = 'notaurl';
        $this->assertFalse(Validate::is($data, 'url'));
    }

    public function testMultipleRequirements()
    {
        $data = ['test', 'test'];
        $this->assertTrue(Validate::is($data, 'matching|string:2'));
        $this->assertEquals('test', $data);
    }

    public function testArrayRequirements()
    {
        $data = ['test', 'test'];
        $requirements = [['matching', []], ['string', [2]]];
        $this->assertTrue(Validate::is($data, $requirements));
        $this->assertEquals('test', $data);
    }

    public function testKeyValueRequirements()
    {
        $data = [
            'test' => ['test', 'test'],
            'test2' => 'alphanumer1c',
        ];

        $requirements = [
            'test' => 'matching|string:2',
            'test2' => 'alpha_numeric',
        ];

        $validator = new Validate($requirements);

        $this->assertTrue($validator->validate($data));
        $this->assertEquals('test', $data['test']);
    }

    public function testInvalidWithErrors()
    {
        $data = [
            'test' => ['test', 'test1'],
            'test2' => '#&%(*&#%',
        ];

        $requirements = [
            'test' => 'matching|string:2',
            'test2' => 'alpha_numeric',
        ];

        $errors = new Errors();
        $validator = new Validate($requirements, $errors);

        $this->assertFalse($validator->validate($data));

        $expected = [
            'pulsar.validation.matching',
            'pulsar.validation.string',
            'pulsar.validation.alpha_numeric',
        ];
        $this->assertEquals($expected, $errors->all());
    }
}
