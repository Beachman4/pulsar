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
    protected static $properties = [
        'relation' => [
            'type' => Model::TYPE_NUMBER,
        ],
        'answer' => [
            'type' => Model::TYPE_STRING,
        ],
        'mutator' => [],
        'accessor' => [],
        'test_model2_id' => [],
    ];

    protected static $validations = [
        'answer' => 'matching',
    ];

    protected static $hidden = [
        'mutator',
        'accessor',
        'test_model2_id',
    ];
    protected static $appended = ['appended'];

    public static $query;

    protected function initialize()
    {
        self::$properties['test_hook'] = [];

        parent::initialize();
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

    protected static $properties = [
        'id' => [
            'type' => Model::TYPE_NUMBER,
        ],
        'id2' => [
            'type' => Model::TYPE_NUMBER,
        ],
        'default' => [
            'default' => 'some default value',
        ],
        'validate' => [
            'title' => 'Email address',
        ],
        'validate2' => [],
        'unique' => [],
        'required' => [
            'type' => Model::TYPE_NUMBER,
        ],
        'hidden' => [
            'type' => Model::TYPE_BOOLEAN,
            'default' => false,
        ],
        'person_id' => [
            'type' => Model::TYPE_NUMBER,
            'default' => 20,
        ],
        'array' => [
            'type' => Model::TYPE_ARRAY,
            'default' => [
                'tax' => '%',
                'discounts' => false,
                'shipping' => false,
            ],
        ],
        'object' => [
            'type' => Model::TYPE_OBJECT,
        ],
        'mutable_create_only' => [
            'mutable' => Model::MUTABLE_CREATE_ONLY,
        ],
    ];

    protected static $autoTimestamps;

    protected static $validations = [
        'validate' => 'email',
        'validate2' => 'custom:validate',
        'required' => 'required|boolean',
        'unique' => 'unique',
    ];

    protected static $relationships = ['person'];

    protected static $hidden = [
        'validate2',
        'hidden',
        'person_id',
        'array',
        'object',
        'mutable_create_only',
    ];
    protected static $appended = ['person'];

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
    protected static $properties = [
        'id' => [
            'type' => Model::TYPE_STRING,
        ],
        'name' => [
            'type' => Model::TYPE_STRING,
            'default' => 'Jared',
        ],
        'email' => [
            'type' => Model::TYPE_STRING,
        ],
    ];

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
    protected static $properties = [
        'name' => [],
    ];
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

    protected static $properties = [
        'answer' => [],
    ];

    public static $cacheTTL = 10;
}
