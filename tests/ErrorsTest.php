<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
use Infuse\Locale;
use Pulsar\Errors;

class ErrorsTest extends PHPUnit_Framework_TestCase
{
    public static $locale;

    public static function setUpBeforeClass()
    {
        self::$locale = new Locale();
        self::$locale->setLocaleDataDir(__DIR__.'/locales');
    }

    public function testGetModel()
    {
        $person = new Person();
        $errors = new Errors($person);
        $this->assertEquals($person, $errors->getModel());
    }

    public function testGetLocale()
    {
        $errors = $this->getErrors();
        $this->assertEquals(self::$locale, $errors->getLocale());

        $errors = new Errors(new Person());
        $this->assertNull($errors->getLocale());
    }

    public function testAdd()
    {
        $errors = $this->getErrors();
        $this->assertEquals($errors, $errors->add('property', 'Something is wrong'));
        $this->assertEquals($errors, $errors->add('username', 'pulsar.validation.invalid'));
        $this->assertEquals($errors, $errors->add('property', 'some_error'));
    }

    public function testAll()
    {
        $errors = $this->getErrors();
        $errors->add('property', 'Something is wrong')
               ->add('username', 'pulsar.validation.invalid')
               ->add('property', 'some_error');

        $expected = [
            'Something is wrong',
            'some_error',
            'Username is invalid',
        ];

        $messages = $errors->all();
        $this->assertEquals(3, count($messages));
        $this->assertEquals($expected, $messages);

        $expected = ['Username is invalid'];
    }

    public function testHas()
    {
        $errors = $this->getErrors();
        $errors->add('username', 'pulsar.validation.invalid');
        $this->assertTrue($errors->has('username'));
        $this->assertFalse($errors->has('non-existent'));
    }

    public function testClear()
    {
        $errors = $this->getErrors();
        $errors->add('test', 'test');
        $this->assertEquals($errors, $errors->clear());
        $this->assertCount(0, $errors->all());
    }

    public function testIterator()
    {
        $errors = $this->getErrors();

        for ($i = 1; $i <= 5; ++$i) {
            $errors->add('property', $i);
        }

        $result = [];
        foreach ($errors as $k => $v) {
            $result[$k] = $v;
        }

        $this->assertEquals(['property' => ['1', '2', '3', '4', '5']], $result);
    }

    public function testCount()
    {
        $errors = $this->getErrors();
        $errors->add('property', 'Test');
        $this->assertCount(1, $errors);
    }

    public function testArrayAccess()
    {
        $errors = $this->getErrors();

        $errors['property'] = 'test';
        $this->assertTrue(isset($errors['property']));
        $this->assertFalse(isset($errors['notset']));

        $this->assertEquals(['test'], $errors['property']);
        unset($errors['property']);
        $this->assertFalse(isset($errors['property']));
    }

    public function testArrayGetFail()
    {
        $errors = $this->getErrors();
        $this->assertEquals([], $errors['invalid']);
    }

    public function testDefaultMessages()
    {
        $errors = $this->getErrors();

        $messages = [
            'pulsar.validation.alpha' => 'Property only allows letters',
            'pulsar.validation.alpha_numeric' => 'Property only allows letters and numbers',
            'pulsar.validation.alpha_dash' => 'Property only allows letters and dashes',
            'pulsar.validation.boolean' => 'Property must be yes or no',
            'pulsar.validation.custom' => 'Property validation failed',
            'pulsar.validation.email' => 'Property must be a valid email address',
            'pulsar.validation.enum' => 'Property must be one of the allowed values',
            'pulsar.validation.date' => 'Property must be a date',
            'pulsar.validation.ip' => 'Property only allows valid IP addresses',
            'pulsar.validation.matching' => 'Property must match',
            'pulsar.validation.numeric' => 'Property only allows numbers',
            'pulsar.validation.password' => 'Property must meet the password requirements',
            'pulsar.validation.range' => 'Property must be within the allowed range',
            'pulsar.validation.required' => 'Property is missing',
            'pulsar.validation.string' => 'Property must be a string of the proper length',
            'pulsar.validation.time_zone' => 'Property only allows valid time zones',
            'pulsar.validation.timestamp' => 'Property only allows timestamps',
            'pulsar.validation.unique' => 'Property must be unique',
            'pulsar.validation.url' => 'Property only allows valid URLs',
        ];

        foreach ($messages as $error => $message) {
            $errors->clear();
            $errors['property'] = $error;
            $this->assertEquals([$message], $errors['property']);
        }
    }

    private function getErrors()
    {
        $person = new Person();

        return new Errors($person, self::$locale);
    }
}
