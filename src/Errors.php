<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
namespace Pulsar;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Infuse\Locale;

class Errors implements IteratorAggregate, Countable, ArrayAccess
{
    /**
     * @staticvar array
     */
    private static $messages = [
        'pulsar.validation.alpha' => '{{property}} only allows letters',
        'pulsar.validation.alpha_numeric' => '{{property}} only allows letters and numbers',
        'pulsar.validation.alpha_dash' => '{{property}} only allows letters and dashes',
        'pulsar.validation.boolean' => '{{property}} must be yes or no',
        'pulsar.validation.custom' => '{{property}} validation failed',
        'pulsar.validation.email' => '{{property}} must be a valid email address',
        'pulsar.validation.enum' => '{{property}} must be one of the allowed values',
        'pulsar.validation.date' => '{{property}} must be a date',
        'pulsar.validation.ip' => '{{property}} only allows valid IP addresses',
        'pulsar.validation.matching' => '{{property}} must match',
        'pulsar.validation.numeric' => '{{property}} only allows numbers',
        'pulsar.validation.password' => '{{property}} must meet the password requirements',
        'pulsar.validation.range' => '{{property}} must be within the allowed range',
        'pulsar.validation.required' => '{{property}} is missing',
        'pulsar.validation.string' => '{{property}} must be a string of the proper length',
        'pulsar.validation.time_zone' => '{{property}} only allows valid time zones',
        'pulsar.validation.timestamp' => '{{property}} only allows timestamps',
        'pulsar.validation.unique' => '{{property}} must be unique',
        'pulsar.validation.url' => '{{property}} only allows valid URLs',
    ];

    /**
     * @var array
     */
    private $stack = [];

    /**
     * @var Model
     */
    private $model;

    /**
     * @var Locale|null
     */
    private $locale;

    /**
     * @param Model       $model
     * @param Locale|null $locale
     */
    public function __construct(Model $model, Locale $locale = null)
    {
        $this->model = $model;
        $this->locale = $locale;
    }

    /**
     * Gets the model instance for these errors.
     *
     * @return Model
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Gets the locale instance.
     *
     * @return Locale|null
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Adds an error message to the stack.
     *
     * @param string $property name of property the error is about
     * @param string $error    error code or message
     *
     * @return self
     */
    public function add($property, $error)
    {
        if (!isset($this->stack[$property])) {
            $this->stack[$property] = [];
        }

        $this->stack[$property][] = $error;

        return $this;
    }

    /**
     * Gets all of the errors on the stack and also attempts
     * translation using the Locale class.
     *
     * @param string|false $property property to filter by
     * @param string|false $locale   locale name to translate to
     *
     * @return array errors
     */
    public function all($property = false, $locale = false)
    {
        $errors = $this->stack;
        if ($property !== false) {
            if (!isset($errors[$property])) {
                return [];
            }

            $errors = [$property => $this->stack[$property]];
        }

        // convert errors into messages
        $messages = [];
        foreach ($errors as $property => $errors2) {
            foreach ($errors2 as $error) {
                $messages[] = $this->parse($property, $error, $locale);
            }
        }

        return $messages;
    }

    /**
     * Checks if a property has an error.
     *
     * @param string $property
     *
     * @return bool
     */
    public function has($property)
    {
        return isset($this->stack[$property]);
    }

    /**
     * Clears all errors.
     *
     * @return self
     */
    public function clear()
    {
        $this->stack = [];

        return $this;
    }

    /**
     * Parses an error message before displaying it.
     *
     * @param string       $property
     * @param string       $error
     * @param string|false $locale
     *
     * @return string
     */
    private function parse($property, $error, $locale)
    {
        if (!$this->locale) {
            return $error;
        }

        $model = $this->model;

        $parameters = [
            'property' => $model::getPropertyTitle($property),
        ];

        // try to supply a fallback message
        $fallback = array_value(self::$messages, $error);

        return $this->locale->t($error, $parameters, $locale, $fallback);
    }

    //////////////////////////
    // IteratorAggregate Interface
    //////////////////////////

    public function getIterator()
    {
        return new ArrayIterator($this->stack);
    }

    //////////////////////////
    // Countable Interface
    //////////////////////////

    /**
     * Get total number of errors.
     *
     * @return int
     */
    public function count()
    {
        return count($this->all());
    }

    /////////////////////////////
    // ArrayAccess Interface
    /////////////////////////////

    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    public function offsetGet($offset)
    {
        return $this->all($offset);
    }

    public function offsetSet($offset, $error)
    {
        $this->add($offset, $error);
    }

    public function offsetUnset($offset)
    {
        if ($this->has($offset)) {
            unset($this->stack[$offset]);
        }
    }
}
