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
use Pulsar\Validator;

function customValidator(&$value, array $parameters)
{
    $value = $parameters[0];

    return true;
}

class ValidatorTest extends PHPUnit_Framework_TestCase
{
    public function testMultipleRules()
    {
        $data = [
            'test' => ['test', 'test'],
            'test2' => 'alphanumer1c',
        ];

        $rules = [
            'test' => 'matching|string:2',
            'test2' => 'alpha_numeric',
        ];

        $validator = new Validator($rules);

        $this->assertTrue($validator->validate($data));
        $this->assertEquals('test', $data['test']);
    }

    public function testInvalidWithErrors()
    {
        $data = [
            'test' => ['test', 'test1'],
            'test2' => '#&%(*&#%',
        ];

        $rules = [
            'test' => 'matching|string:2',
            'test2' => 'alpha_numeric',
        ];

        $errors = new Errors();
        $validator = new Validator($rules, $errors);

        $this->assertFalse($validator->validate($data));

        $expected = [
            'pulsar.validation.matching',
            'pulsar.validation.string',
            'pulsar.validation.alpha_numeric',
        ];
        $this->assertEquals($expected, $errors->all());
    }

    public function testArrayRules()
    {
        $validator = $this->buildValidator([['matching', []], ['string', [2]]]);

        $data = ['test', 'test'];
        $this->assertTrue($this->validateWith($validator, $data));
        $this->assertEquals('test', $data);
    }

    ////////////////////////////////
    // Rules
    ////////////////////////////////

    public function testAlpha()
    {
        $validator = $this->buildValidator('alpha');
        $data = 'abc';
        $this->assertTrue($this->validateWith($validator, $data));
        $data = ')S*F#$)S*';
        $this->assertFalse($this->validateWith($validator, $data));

        $validator = $this->buildValidator('alpha:5');
        $data = 'abcde';
        $this->assertTrue($this->validateWith($validator, $data));
        $data = 'abc';
        $this->assertFalse($this->validateWith($validator, $data));
    }

    public function testAlphaNumeric()
    {
        $validator = $this->buildValidator('alpha_numeric');
        $data = 'abc1234';
        $this->assertTrue($this->validateWith($validator, $data));
        $data = ')S*F#$)S*';
        $this->assertFalse($this->validateWith($validator, $data));
        $data = 'a2cde';

        $validator = $this->buildValidator('alpha_numeric:5');
        $this->assertTrue($this->validateWith($validator, $data));
        $data = 'a2c';
        $this->assertFalse($this->validateWith($validator, $data));
    }

    public function testAlphaDash()
    {
        $validator = $this->buildValidator('alpha_dash');
        $data = 'abc-1234';
        $this->assertTrue($this->validateWith($validator, $data));
        $data = ')S*F#$)S*';
        $this->assertFalse($this->validateWith($validator, $data));

        $validator = $this->buildValidator('alpha_dash:5');
        $data = 'r2-d2';
        $this->assertTrue($this->validateWith($validator, $data));

        $validator = $this->buildValidator('alpha_dash:7');
        $this->assertFalse($this->validateWith($validator, $data));
    }

    public function testBoolean()
    {
        $validator = $this->buildValidator('boolean');
        $data = '1';
        $this->assertTrue($this->validateWith($validator, $data));
        $this->assertTrue($data);
        $data = '0';
        $this->assertTrue($this->validateWith($validator, $data));
        $this->assertFalse($data);
    }

    public function testCustom()
    {
        $validator = $this->buildValidator('custom:customValidator,test');
        $data = 'willbereplaced';
        $this->assertTrue($this->validateWith($validator, $data));
        $this->assertEquals('test', $data);
    }

    public function testEmail()
    {
        $validator = $this->buildValidator('email');
        $data = 'test@example.com';
        $this->assertTrue($this->validateWith($validator, $data));
        $data = 'test';
        $this->assertFalse($this->validateWith($validator, $data));
    }

    public function testEnum()
    {
        $validator = $this->buildValidator('enum:red,orange,yellow,green,blue,violet');
        $data = 'blue';
        $this->assertTrue($this->validateWith($validator, $data));

        $validator = $this->buildValidator('enum:Austin,Dallas,OKC,Tulsa');
        $data = 'Paris';
        $this->assertFalse($this->validateWith($validator, $data));
    }

    public function testDate()
    {
        $validator = $this->buildValidator('date');
        date_default_timezone_set('UTC');
        $data = 'today';
        $this->assertTrue($this->validateWith($validator, $data));
        $data = '09/17/2013';
        $this->assertTrue($this->validateWith($validator, $data));
        $data = 'doesnotwork';
        $this->assertFalse($this->validateWith($validator, $data));
    }

    public function testIp()
    {
        $validator = $this->buildValidator('ip');
        $data = '127.0.0.1';
        $this->assertTrue($this->validateWith($validator, $data));
        $data = 'doesnotwork';
        $this->assertFalse($this->validateWith($validator, $data));
    }

    public function testMatching()
    {
        $validator = $this->buildValidator('matching');
        $data = 'notarray';
        $this->assertTrue($this->validateWith($validator, $data));

        $data = ['test', 'test'];
        $this->assertTrue($this->validateWith($validator, $data));
        $this->assertEquals('test', $data);

        $data = ['test', 'test', 'test', 'test'];
        $this->assertTrue($this->validateWith($validator, $data));
        $this->assertEquals('test', $data);

        $data = ['test', 'nope'];
        $this->assertFalse($this->validateWith($validator, $data));
        $this->assertEquals(['test', 'nope'], $data);
    }

    public function testNumeric()
    {
        $validator = $this->buildValidator('numeric');
        $data = 12345.22;
        $this->assertTrue($this->validateWith($validator, $data));
        $data = '1234';
        $this->assertTrue($this->validateWith($validator, $data));
        $data = 'notanumber';
        $this->assertFalse($this->validateWith($validator, $data));

        $validator = $this->buildValidator('numeric:double');
        $data = 12345.22;
        $this->assertTrue($this->validateWith($validator, $data));

        $validator = $this->buildValidator('numeric:int');
        $data = 12345.22;
        $this->assertFalse($this->validateWith($validator, $data));
    }

    public function testPassword()
    {
        $salt = 'saltvalue';
        Validator::configure(['salt' => $salt]);

        $validator = $this->buildValidator('password:8');
        $data = 'testpassword';
        $this->assertTrue($this->validateWith($validator, $data));
        $this->assertEquals(Utility::encryptPassword('testpassword', $salt), $data);

        $data = '...';
        $this->assertFalse($this->validateWith($validator, $data));
    }

    public function testRange()
    {
        $validator = $this->buildValidator('range');
        $data = -1;
        $this->assertTrue($this->validateWith($validator, $data));

        $validator = $this->buildValidator('range:-1');
        $this->assertTrue($this->validateWith($validator, $data));

        $validator = $this->buildValidator('range:-1,100');
        $this->assertTrue($this->validateWith($validator, $data));

        $validator = $this->buildValidator('range:101');
        $data = 100;
        $this->assertFalse($this->validateWith($validator, $data));

        $validator = $this->buildValidator('range:0,99');
        $this->assertFalse($this->validateWith($validator, $data));
    }

    public function testRequired()
    {
        $validator = $this->buildValidator('required');
        $data = 'ok';
        $this->assertTrue($this->validateWith($validator, $data));
        $data = '';
        $this->assertFalse($this->validateWith($validator, $data));
        $data = null;
        $this->assertFalse($this->validateWith($validator, $data));
        $data = [];
        $this->assertFalse($this->validateWith($validator, $data));
    }

    public function testSkipEmpty()
    {
        $validator = $this->buildValidator('skip_empty|required|range:101');
        $data = null;
        $this->assertTrue($this->validateWith($validator, $data));
        $this->assertNull($data);
        $data = '';
        $this->assertTrue($this->validateWith($validator, $data));
        $this->assertNull($data);
        $data = 100;
        $this->assertFalse($this->validateWith($validator, $data));
        $this->assertEquals(100, $data);
    }

    public function testString()
    {
        $validator = $this->buildValidator('string');
        $data = 'thisisok';
        $this->assertTrue($this->validateWith($validator, $data));

        $validator = $this->buildValidator('string:5');
        $this->assertTrue($this->validateWith($validator, $data));

        $validator = $this->buildValidator('string:1,8');
        $this->assertTrue($this->validateWith($validator, $data));

        $validator = $this->buildValidator('string:0,9');
        $this->assertTrue($this->validateWith($validator, $data));

        $validator = $this->buildValidator('string:9');
        $this->assertFalse($this->validateWith($validator, $data));

        $validator = $this->buildValidator('string:1,7');
        $this->assertFalse($this->validateWith($validator, $data));

        $validator = $this->buildValidator('string');
        $data = new stdClass();
        $this->assertFalse($this->validateWith($validator, $data));
    }

    public function testTimeZone()
    {
        $validator = $this->buildValidator('time_zone');
        $data = 'America/Chicago';
        $this->assertTrue($this->validateWith($validator, $data));

        $data = 'anywhere';
        $this->assertFalse($this->validateWith($validator, $data));
    }

    public function testTimestamp()
    {
        $validator = $this->buildValidator('timestamp');
        $data = $t = time();
        $this->assertTrue($this->validateWith($validator, $data));
        $this->assertEquals($t, $data);

        $data = 'today';
        $this->assertTrue($this->validateWith($validator, $data));
        $this->assertEquals(strtotime('today'), $data);
    }

    public function testDbTimestamp()
    {
        $validator = $this->buildValidator('db_timestamp');
        $data = mktime(23, 34, 20, 4, 18, 2012);
        $this->assertTrue($this->validateWith($validator, $data));
        $this->assertEquals('2012-04-18 23:34:20', $data);

        $data = 'test';
        $this->assertFalse($this->validateWith($validator, $data));
    }

    public function testUrl()
    {
        $validator = $this->buildValidator('url');
        $data = 'http://example.com';
        $this->assertTrue($this->validateWith($validator, $data));
        $data = 'notaurl';
        $this->assertFalse($this->validateWith($validator, $data));
    }

    private function buildValidator($rules)
    {
        return new Validator(['data' => $rules]);
    }

    private function validateWith($validator, &$data)
    {
        $data2 = ['data' => &$data];

        return $validator->validate($data2);
    }
}
