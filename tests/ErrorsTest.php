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
    public static $errors;

    public static function setUpBeforeClass()
    {
        self::$locale = new Locale();
        self::$locale->setLocaleDataDir(__DIR__.'/locales');
        self::$errors = new Errors('Person', self::$locale);
    }

    public function testGetModel()
    {
        $errors = new Errors('Person');
        $this->assertEquals('Person', $errors->getModel());
    }

    public function testGetLocale()
    {
        $errors = new Errors('Person', self::$locale);
        $this->assertEquals(self::$locale, $errors->getLocale());

        $errors = new Errors();
        $this->assertNull($errors->getLocale());
    }

    public function testAdd()
    {
        $this->assertEquals(self::$errors, self::$errors->add('property', 'Something is wrong'));

        $this->assertEquals(self::$errors, self::$errors->add('username', 'pulsar.validation.invalid'));

        $this->assertEquals(self::$errors, self::$errors->add('property', 'some_error'));
    }

    /**
     * @depends testAdd
     */
    public function testAll()
    {
        $expected = [
            'Something is wrong',
            'some_error',
            'Username is invalid',
        ];

        $messages = self::$errors->all();
        $this->assertEquals(3, count($messages));
        $this->assertEquals($expected, $messages);

        $expected = ['Username is invalid'];
    }

    /**
     * @depends testAdd
     */
    public function testHas()
    {
        $this->assertTrue(self::$errors->has('username'));
        $this->assertFalse(self::$errors->has('non-existent'));
    }

    /**
     * @depends testAll
     */
    public function testClear()
    {
        $this->assertEquals(self::$errors, self::$errors->clear());
        $this->assertCount(0, self::$errors->all());
    }

    public function testIterator()
    {
        self::$errors->clear();
        for ($i = 1; $i <= 5; ++$i) {
            self::$errors->add('property', $i);
        }

        $result = [];
        foreach (self::$errors as $k => $v) {
            $result[$k] = $v;
        }

        $this->assertEquals(['property' => ['1', '2', '3', '4', '5']], $result);
    }

    public function testCount()
    {
        self::$errors->clear()->add('property', 'Test');
        $this->assertCount(1, self::$errors);
    }

    public function testArrayAccess()
    {
        self::$errors->clear();

        self::$errors['property'] = 'test';
        $this->assertTrue(isset(self::$errors['property']));
        $this->assertFalse(isset(self::$errors['notset']));

        $this->assertEquals(['test'], self::$errors['property']);
        unset(self::$errors['property']);
        $this->assertFalse(isset(self::$errors['property']));
    }

    public function testArrayGetFail()
    {
        $this->assertEquals([], self::$errors['invalid']);
    }
}
