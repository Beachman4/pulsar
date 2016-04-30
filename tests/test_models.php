<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
use Pulsar\Model;
use Pulsar\ACLModel;
use Pulsar\Cacheable;
use Pulsar\Query;

class TestModel extends Model
{
    protected static $casts = [
        'relation' => Model::TYPE_INTEGER,
    ];

    protected static $validations = [
        'answer' => 'matching',
    ];

    protected static $permitted = ['relation', 'answer', 'mutator', 'accessor', 'test_model2_id'];

    protected static $hidden = [
        'mutator',
        'accessor',
        'test_model2_id',
    ];
    protected static $appended = ['appended'];

    public static $query;

    public static function query()
    {
        if ($query = self::$query) {
            self::$query = false;

            return $query;
        }

        return parent::query();
    }

    public static function setQuery(Query $query)
    {
        self::$query = $query;
    }

    protected function setMutatorValue($value)
    {
        return strtoupper($value);
    }

    protected function getAccessorValue($value)
    {
        return strtolower($value);
    }

    protected function getAppendedValue()
    {
        return true;
    }
}

function validate()
{
    return false;
}

class TestModel2 extends Model
{
    protected static $ids = ['id', 'id2'];

    protected static $casts = [
        'id' => Model::TYPE_INTEGER,
        'id2' => Model::TYPE_INTEGER,
        'hidden' => Model::TYPE_BOOLEAN,
        'person_id' => Model::TYPE_INTEGER,
        'array' => Model::TYPE_ARRAY,
        'object' => Model::TYPE_OBJECT,
    ];

    protected static $dates = [
        'created_at' => 'Y-m-d H:i:s',
    ];

    protected static $autoTimestamps;

    protected static $validations = [
        'validate' => 'email',
        'validate2' => 'custom:validate',
        'required' => 'required|boolean',
        'unique' => 'unique',
    ];

    protected static $protected = ['protected'];

    protected static $hidden = [
        'validate2',
        'hidden',
        'person_id',
        'array',
        'object',
        'protected',
    ];
    protected static $appended = ['person'];

    protected static $relationships = ['person'];

    public static $query;

    public function __construct(array $values = [])
    {
        // set default values
        $values = array_replace([
            'default' => 'some default value',
            'hidden' => false,
            'person_id' => 20,
            'array' => [
                'tax' => '%',
                'discounts' => false,
                'shipping' => false,
            ],
        ], $values);

        parent::__construct($values);
    }

    public static function query()
    {
        if ($query = self::$query) {
            self::$query = false;

            return $query;
        }

        return parent::query();
    }

    public static function setQuery(Query $query)
    {
        self::$query = $query;
    }

    public function person()
    {
        return $this->belongsTo('Person');
    }
}

class TestModelNoPermission extends ACLModel
{
    protected function hasPermission($permission, Model $requester)
    {
        return false;
    }
}

class Person extends ACLModel
{
    protected static $casts = [
        'id' => Model::TYPE_STRING,
    ];

    public function __construct(array $values = [])
    {
        // set default values
        $values = array_replace(['name' => 'Jared'], $values);

        parent::__construct($values);
    }

    protected function hasPermission($permission, Model $requester)
    {
        return false;
    }
}

class Group extends Model
{
}

class IteratorTestModel extends Model
{
}

class AclObject extends ACLModel
{
    public $first = true;

    protected function hasPermission($permission, Model $requester)
    {
        if ($permission == 'whatever') {
            // always say no the first time
            if ($this->first) {
                $this->first = false;

                return false;
            }

            return true;
        } elseif ($permission == 'do nothing') {
            return $requester->id() == 5;
        }
    }
}

class CacheableModel extends Model
{
    use Cacheable;

    public static $cacheTTL = 10;
}

class Post extends Model
{
}

class Category extends Model
{
}

class Car extends Model
{
}

class Balance extends Model
{
}
