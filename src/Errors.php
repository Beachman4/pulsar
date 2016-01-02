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
     * @var array
     */
    private $stack = [];

    /**
     * @var Model
     */
    private $model;

    /**
     * @var Locale
     */
    private $locale;

    /**
     * @var int
     */
    private $pointer = 0;

    /**
     * @param string $model class name of model
     *
     * @var Locale
     */
    public function __construct($model, Locale $locale = null)
    {
        if (!$locale) {
            $locale = new Locale();
        }

        $this->locale = $locale;
        $this->model = $model;
    }

    /**
     * Gets the locale instance.
     *
     * @return Locale
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
        if ($property) {
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
     * @return array
     */
    private function parse($property, $error, $locale)
    {
        $model = $this->model;

        $parameters = [
            'property' => $model::getPropertyTitle($property),
        ];

        return $this->locale->t($error, $parameters, $locale);
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
