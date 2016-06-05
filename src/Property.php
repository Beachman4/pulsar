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

use Carbon\Carbon;

class Property
{
    /**
     * Casts a value to a string.
     *
     * @param mixed $value
     *
     * @return string
     */
    public static function to_string($value)
    {
        return (string) $value;
    }

    /**
     * Casts a value to an integer.
     *
     * @param mixed $value
     *
     * @return int
     */
    public static function to_integer($value)
    {
        return (int) $value;
    }

    /**
     * Casts a value to a float.
     *
     * @param mixed $value
     *
     * @return float
     */
    public static function to_float($value)
    {
        return (float) $value;
    }

    /**
     * Casts a value to a boolean.
     *
     * @param mixed $value
     *
     * @return bool
     */
    public static function to_boolean($value)
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Casts a value to a Carbon date object.
     *
     * @param mixed  $value
     * @param string $format expected date format
     *
     * @return Carbon
     */
    public static function to_date($value, $format)
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        // cast non-Carbon dates into Carbon objects
        return Carbon::createFromFormat($format, $value);
    }

    /**
     * Casts a value to an array.
     *
     * @param mixed $value
     *
     * @return array
     */
    public static function to_array($value)
    {
        // decode JSON strings into an array
        if (is_string($value)) {
            return json_decode($value, true);
        }

        return (array) $value;
    }

    /**
     * Casts a value to an object.
     *
     * @param mixed $value
     *
     * @return object
     */
    public static function to_object($value)
    {
        // decode JSON strings into an object
        if (is_string($value)) {
            return (object) json_decode($value);
        }

        return (object) $value;
    }
}
