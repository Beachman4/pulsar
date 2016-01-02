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

use Infuse\Utility;

class Validator
{
    /**
     * @staticvar array
     */
    private static $config = [
        'salt' => '',
    ];

    /**
     * @var array
     */
    private $rules;

    /**
     * @var Errors
     */
    private $errors;

    /**
     * @var bool
     */
    private $skipRemaining;

    /**
     * These are rules that always run even if a value is not present.
     *
     * @staticvar array
     */
    private static $runsWhenNotPresent = [
        'required',
    ];

    /**
     * Changes settings for the validator.
     *
     * @param array $config
     */
    public static function configure($config)
    {
        self::$config = array_replace(self::$config, (array) $config);
    }

    /**
     * @var array
     * @var Errors
     */
    public function __construct(array $rules, Errors $errors = null)
    {
        // parse rule strings if used
        foreach ($rules as &$rules2) {
            if (!is_array($rules2)) {
                $rules2 = $this->buildRulesFromStr($rules2);
            }
        }

        $this->rules = $rules;
        $this->errors = $errors;
    }

    /**
     * Validates whether an input passes the validator's rules.
     *
     * @param array $data
     *
     * @return bool
     */
    public function validate(array &$data)
    {
        $validated = true;
        foreach ($this->rules as $name => $rules) {
            // if a value is not present then skip any validations
            if ((!array_key_exists($name, $data) || !$this->required($data[$name])) && !$this->runsWhenNotPresent($rules)) {
                continue;
            }

            if (!isset($data[$name])) {
                $data[$name] = null;
            }

            $this->skipRemaining = false;

            foreach ($rules as $rule) {
                list($method, $parameters) = $rule;

                $valid = self::$method($data[$name], $parameters);
                $validated = $validated && $valid;

                if (!$valid && $this->errors) {
                    $this->errors->add($name, "pulsar.validation.$method");
                }

                if ($this->skipRemaining) {
                    break;
                }
            }
        }

        return $validated;
    }

    /**
     * Parses a string into a list of rules.
     * Rule strings have the form "numeric|range:10,30" where
     * '|' separates rules and ':' allows a comma-separated list
     * of parameters to be specified. This example would generate 
     * [['numeric', []], ['range', [10, 30]]].
     *
     * @param string $rules
     *
     * @return array
     */
    private function buildRulesFromStr($str)
    {
        $rules = [];

        // explodes the string into a a list of strings
        // containing rules and parameters
        $pieces = explode('|', $str);
        foreach ($pieces as $piece) {
            $exp = explode(':', $piece);
            // [0] = rule method
            $method = $exp[0];
            // [1] = optional method parameters
            $parameters = isset($exp[1]) ? explode(',', $exp[1]) : [];

            $rules[] = [$exp[0], $parameters];
        }

        return $rules;
    }

    /**
     * Checks if the rules should be ran when a value is empty or
     * not present.
     *
     * @param array $rules
     *
     * @return bool
     */
    private function runsWhenNotPresent(array $rules)
    {
        foreach ($rules as $rule) {
            if (in_array($rule[0], self::$runsWhenNotPresent)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Skips remaining rules.
     *
     * @return self
     */
    private function skipRemaining()
    {
        $this->skipRemaining = true;

        return $this;
    }

    ////////////////////////////////
    // Rules
    ////////////////////////////////

    /**
     * Validates an alpha string.
     * OPTIONAL alpha:5 can specify minimum length.
     *
     * @param mixed $value
     * @param array $parameters
     *
     * @return bool
     */
    private function alpha($value, array $parameters)
    {
        return preg_match('/^[A-Za-z]*$/', $value) && strlen($value) >= array_value($parameters, 0);
    }

    /**
     * Validates an alpha-numeric string
     * OPTIONAL alpha_numeric:6 can specify minimum length.
     *
     * @param mixed $value
     * @param array $parameters
     *
     * @return bool
     */
    private function alpha_numeric($value, array $parameters)
    {
        return preg_match('/^[A-Za-z0-9]*$/', $value) && strlen($value) >= array_value($parameters, 0);
    }

    /**
     * Validates an alpha-numeric string with dashes and underscores
     * OPTIONAL alpha_dash:7 can specify minimum length.
     *
     * @param mixed $value
     * @param array $parameters
     *
     * @return bool
     */
    private function alpha_dash($value, array $parameters)
    {
        return preg_match('/^[A-Za-z0-9_-]*$/', $value) && strlen($value) >= array_value($parameters, 0);
    }

    /**
     * Validates a boolean value.
     *
     * @param mixed $value
     *
     * @return bool
     */
    private function boolean(&$value)
    {
        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        return true;
    }

    /**
     * Validates by calling a given function.
     *
     * @param mixed $value
     * @param array $parameters
     *
     * @return bool
     */
    private function custom(&$value, array $parameters)
    {
        $method = $parameters[0];
        $parameters2 = (array) array_slice($parameters, 1);

        return $method($value, $parameters2);
    }

    /**
     * Validates an e-mail address.
     *
     * @param string $email      e-mail address
     * @param array  $parameters parameters for validation
     *
     * @return bool success
     */
    private function email(&$value, array $parameters)
    {
        $value = trim(strtolower($value));

        return filter_var($value, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Validates a value exists in an array. i.e. enum:blue,red,green,yellow.
     *
     * @param mixed $value
     * @param array $parameters
     *
     * @return bool
     */
    private function enum($value, array $parameters)
    {
        return in_array($value, $parameters);
    }

    /**
     * Validates a date string.
     *
     * @param mixed $value
     *
     * @return bool
     */
    private function date($value)
    {
        return strtotime($value);
    }

    /**
     * Validates an IP address.
     *
     * @param mixed $value
     *
     * @return bool
     */
    private function ip($value)
    {
        return filter_var($value, FILTER_VALIDATE_IP);
    }

    /**
     * Validates that an array of values matches. The array will
     * be flattened to a single value if it matches.
     *
     * @param mixed $value
     *
     * @return bool
     */
    private function matching(&$value)
    {
        if (!is_array($value)) {
            return true;
        }

        $matches = true;
        $cur = reset($value);
        foreach ($value as $v) {
            $matches = ($v == $cur) && $matches;
            $cur = $v;
        }

        if ($matches) {
            $value = $cur;
        }

        return $matches;
    }

    /**
     * Validates a number.
     * OPTIONAL numeric:int specifies a type.
     *
     * @param mixed $value
     * @param array $parameters
     *
     * @return bool
     */
    private function numeric($value, array $parameters)
    {
        $check = 'is_'.array_value($parameters, 0);

        return (!isset($parameters[0])) ? is_numeric($value) : $check($value);
    }

    /**
     * Validates a password and hashes the value.
     * OPTIONAL password:10 sets the minimum length.
     *
     * @param mixed $value
     * @param array $parameters
     *
     * @return bool
     */
    private function password(&$value, array $parameters)
    {
        $minimumPasswordLength = (isset($parameters[0])) ? $parameters[0] : 8;

        if (strlen($value) < $minimumPasswordLength) {
            return false;
        }

        $value = Utility::encryptPassword($value, self::$config['salt']);

        return true;
    }

    /**
     * Validates that a number falls within a range.
     *
     * @param mixed $value
     * @param array $parameters
     *
     * @return bool
     */
    private function range($value, array $parameters)
    {
        // check min
        if (isset($parameters[0]) && $value < $parameters[0]) {
            return false;
        }

        // check max
        if (isset($parameters[1]) && $value > $parameters[1]) {
            return false;
        }

        return true;
    }

    /**
     * Makes sure that a variable is not empty.
     *
     * @param mixed $value
     *
     * @return bool
     */
    private function required($value)
    {
        if ($value === null || $value === '') {
            return false;
        }

        if (is_array($value) && count($value) === 0) {
            return false;
        }

        return true;
    }

    /**
     * Skips any remaining rules for a field if the
     * value is empty.
     *
     * @param mixed $value
     *
     * @return bool
     */
    public function skip_empty(&$value)
    {
        if (empty($value)) {
            $value = null;
            $this->skipRemaining();
        }

        return true;
    }

    /**
     * Validates a string.
     * OPTIONAL string:5 supplies a minimum length
     *          string:1:5 supplies a minimum and maximum length.
     *
     * @param mixed $value
     * @param array $parameters
     *
     * @return bool
     */
    private function string($value, array $parameters)
    {
        if (!is_string($value)) {
            return false;
        }

        $len = strlen($value);
        $min = array_value($parameters, 0);
        $max = array_value($parameters, 1);

        return $len >= $min && (!$max || $len <= $max);
    }

    /**
     * Validates a PHP time zone identifier.
     *
     * @param mixed $value
     *
     * @return bool
     */
    private function time_zone($value)
    {
        // thanks to http://stackoverflow.com/questions/5816960/how-to-check-is-timezone-identifier-valid-from-code
        $valid = [];
        $tza = timezone_abbreviations_list();
        foreach ($tza as $zone) {
            foreach ($zone as $item) {
                $valid[$item['timezone_id']] = true;
            }
        }
        unset($valid['']);

        return !!array_value($valid, $value);
    }

    /**
     * Validates a Unix timestamp. If the value is not a timestamp it will be
     * converted to one with strtotime().
     *
     * @param mixed $value
     *
     * @return bool
     */
    private function timestamp(&$value)
    {
        if (ctype_digit((string) $value)) {
            return true;
        }

        $value = strtotime($value);

        return !!$value;
    }

    /**
     * Converts a Unix timestamp into a format compatible with database
     * timestamp types.
     *
     * @param mixed $value
     *
     * @return bool
     */
    private function db_timestamp(&$value)
    {
        if (is_integer($value)) {
            $value = Utility::unixToDb($value);

            return true;
        }

        return false;
    }

    /**
     * Validates a URL.
     *
     * @param mixed $value
     *
     * @return bool
     */
    private function url($value)
    {
        return filter_var($value, FILTER_VALIDATE_URL);
    }
}
