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

use BadMethodCallException;
use ICanBoogie\Inflector;
use Infuse\ErrorStack;
use InvalidArgumentException;
use Pulsar\Driver\DriverInterface;
use Pulsar\Exception\NotFoundException;
use Pulsar\Relation\HasOne;
use Pulsar\Relation\BelongsTo;
use Pulsar\Relation\HasMany;
use Pulsar\Relation\BelongsToMany;
use Pimple\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;

abstract class Model implements \ArrayAccess
{
    const IMMUTABLE = 0;
    const MUTABLE_CREATE_ONLY = 1;
    const MUTABLE = 2;

    const TYPE_STRING = 'string';
    const TYPE_NUMBER = 'number';
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_DATE = 'date';
    const TYPE_OBJECT = 'object';
    const TYPE_ARRAY = 'array';

    const ERROR_REQUIRED_FIELD_MISSING = 'required_field_missing';
    const ERROR_VALIDATION_FAILED = 'validation_failed';
    const ERROR_NOT_UNIQUE = 'not_unique';

    const DEFAULT_ID_PROPERTY = 'id';

    /////////////////////////////
    // Model visible variables
    /////////////////////////////

    /**
     * List of model ID property names.
     *
     * @staticvar array
     */
    protected static $ids = [self::DEFAULT_ID_PROPERTY];

    /**
     * Property definitions expressed as a key-value map with
     * property names as the keys.
     * i.e. ['enabled' => ['type' => Model::TYPE_BOOLEAN]].
     *
     * @staticvar array
     */
    protected static $properties = [];

    /**
     * @staticvar array
     */
    protected static $relationships = [];

    /**
     * @staticvar \Pimple\Container
     */
    protected static $injectedApp;

    /**
     * @staticvar array
     */
    protected static $dispatchers;

    /**
     * @var \Pimple\Container
     */
    protected $app;

    /**
     * @var array
     */
    protected $_values = [];

    /**
     * @var array
     */
    protected $_unsaved = [];

    /**
     * @var bool
     */
    protected $_exists = false;

    /**
     * @var \Infuse\ErrorStack
     */
    protected $_errors;

    /////////////////////////////
    // Base model variables
    /////////////////////////////

    /**
     * @staticvar array
     */
    private static $propertyDefinitionBase = [
        'type' => self::TYPE_STRING,
        'mutable' => self::MUTABLE,
        'null' => false,
        'unique' => false,
        'required' => false,
    ];

    /**
     * @staticvar array
     */
    private static $defaultIDProperty = [
        'type' => self::TYPE_NUMBER,
        'mutable' => self::IMMUTABLE,
    ];

    /**
     * @staticvar array
     */
    private static $timestampProperties = [
        'created_at' => [
            'type' => self::TYPE_DATE,
            'default' => null,
            'null' => true,
            'validate' => 'timestamp|db_timestamp',
        ],
        'updated_at' => [
            'type' => self::TYPE_DATE,
            'validate' => 'timestamp|db_timestamp',
        ],
    ];

    /**
     * @staticvar array
     */
    private static $initialized = [];

    /**
     * @staticvar Model\Driver\DriverInterface
     */
    private static $driver;

    /**
     * @staticvar array
     */
    private static $accessors = [];

    /**
     * @staticvar array
     */
    private static $mutators = [];

    /**
     * @var bool
     */
    private $_ignoreUnsaved;

    /**
     * Creates a new model object.
     *
     * @param array $values values to fill model with
     */
    public function __construct(array $values = [])
    {
        $this->init();
        $this->_values = $values;
    }

    /**
     * Performs initialization on this model.
     */
    private function init()
    {
        $this->app = self::$injectedApp;

        // ensure the initialize function is called only once
        $k = get_called_class();
        if (!isset(self::$initialized[$k])) {
            $this->initialize();
            self::$initialized[$k] = true;
        }
    }

    /**
     * The initialize() method is called once per model. It's used
     * to perform any one-off tasks before the model gets
     * constructed. This is a great place to add any model
     * properties. When extending this method be sure to call
     * parent::initialize() as some important stuff happens here.
     * If extending this method to add properties then you should
     * call parent::initialize() after adding any properties.
     */
    protected function initialize()
    {
        // add in the default ID property
        if (static::$ids == [self::DEFAULT_ID_PROPERTY] && !isset(static::$properties[self::DEFAULT_ID_PROPERTY])) {
            static::$properties[self::DEFAULT_ID_PROPERTY] = self::$defaultIDProperty;
        }

        // add in the auto timestamp properties
        if (property_exists(get_called_class(), 'autoTimestamps')) {
            static::$properties = array_replace(self::$timestampProperties, static::$properties);
        }

        // fill in each property by extending the property
        // definition base
        foreach (static::$properties as &$property) {
            $property = array_replace(self::$propertyDefinitionBase, $property);
        }

        // order the properties array by name for consistency
        // since it is constructed in a random order
        ksort(static::$properties);
    }

    /**
     * Injects a DI container.
     *
     * @param \Pimple\Container $app
     */
    public static function inject(Container $app)
    {
        self::$injectedApp = $app;
    }

    /**
     * Gets the DI container used for this model.
     *
     * @return \Pimple\Container
     */
    public function getApp()
    {
        return $this->app;
    }

    /**
     * Sets the driver for all models.
     *
     * @param Model\Driver\DriverInterface $driver
     */
    public static function setDriver(DriverInterface $driver)
    {
        self::$driver = $driver;
    }

    /**
     * Gets the driver for all models.
     *
     * @return Model\Driver\DriverInterface
     *
     * @throws BadMethodCallException
     */
    public static function getDriver()
    {
        if (!self::$driver) {
            throw new BadMethodCallException('A model driver has not been set yet.');
        }

        return self::$driver;
    }

    /**
     * Clears the driver for all models.
     */
    public static function clearDriver()
    {
        self::$driver = null;
    }

    /**
     * Gets the name of the model without namespacing.
     *
     * @return string
     */
    public static function modelName()
    {
        $class_name = get_called_class();

        // strip namespacing
        $paths = explode('\\', $class_name);

        return end($paths);
    }

    /**
     * Gets the model ID.
     *
     * @return string|number|null ID
     */
    public function id()
    {
        $ids = $this->ids();

        // if a single ID then return it
        if (count($ids) === 1) {
            return reset($ids);
        }

        // if multiple IDs then return a comma-separated list
        return implode(',', $ids);
    }

    /**
     * Gets a key-value map of the model ID.
     *
     * @return array ID map
     */
    public function ids()
    {
        return $this->get(static::$ids);
    }

    /////////////////////////////
    // Magic Methods
    /////////////////////////////

    /**
     * Converts the model into a string.
     *
     * @return string
     */
    public function __toString()
    {
        return get_called_class().'('.$this->id().')';
    }

    /**
     * Shortcut to a get() call for a given property.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        // get relationship values
        if (static::isRelationship($name)) {
            return $this->loadRelationship($name);
        }

        // get property values
        $result = $this->get([$name]);

        return reset($result);
    }

    /**
     * Sets an unsaved value.
     *
     * @param string $name
     * @param mixed  $value
     *
     * @throws BadMethodCallException
     */
    public function __set($name, $value)
    {
        if (static::isRelationship($name)) {
            throw new BadMethodCallException("Cannot set the `$name` property because it is a relationship");
        }

        // set using any mutators
        if ($mutator = self::getMutator($name)) {
            $this->_unsaved[$name] = $this->$mutator($value);
        } else {
            $this->_unsaved[$name] = $value;
        }

        return $this;
    }

    /**
     * Checks if an unsaved value or property exists by this name.
     *
     * @param string $name
     *
     * @return bool
     */
    public function __isset($name)
    {
        return array_key_exists($name, $this->_unsaved) || static::hasProperty($name);
    }

    /**
     * Unsets an unsaved value.
     *
     * @param string $name
     *
     * @throws BadMethodCallException
     */
    public function __unset($name)
    {
        if (static::isRelationship($name)) {
            throw new BadMethodCallException("Cannot unset the `$name` property because it is a relationship");
        }

        if (array_key_exists($name, $this->_unsaved)) {
            unset($this->_unsaved[$name]);
        }
    }

    /////////////////////////////
    // ArrayAccess Interface
    /////////////////////////////

    public function offsetExists($offset)
    {
        return isset($this->$offset);
    }

    public function offsetGet($offset)
    {
        return $this->$offset;
    }

    public function offsetSet($offset, $value)
    {
        $this->$offset = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->$offset);
    }

    public static function __callStatic($name, $parameters)
    {
        // Any calls to unkown static methods should be deferred to
        // the query. This allows calls like User::where()
        // to replace User::query()->where().
        return call_user_func_array([static::query(), $name], $parameters);
    }

    /////////////////////////////
    // Property Definitions
    /////////////////////////////

    /**
     * Gets all the property definitions for the model.
     *
     * @return array key-value map of properties
     */
    public static function getProperties()
    {
        return static::$properties;
    }

    /**
     * Gets a property defition for the model.
     *
     * @param string $property property to lookup
     *
     * @return array|null property
     */
    public static function getProperty($property)
    {
        return array_value(static::$properties, $property);
    }

    /**
     * Gets the names of the model ID properties.
     *
     * @return array
     */
    public static function getIdProperties()
    {
        return static::$ids;
    }

    /**
     * Builds an existing model instance given a single ID value or
     * ordered array of ID values.
     *
     * @param mixed $id
     *
     * @return Model
     */
    public static function buildFromId($id)
    {
        $ids = [];
        $id = (array) $id;
        foreach (static::$ids as $j => $k) {
            $ids[$k] = $id[$j];
        }

        $model = new static($ids);

        return $model;
    }

    /**
     * Checks if the model has a property.
     *
     * @param string $property property
     *
     * @return bool has property
     */
    public static function hasProperty($property)
    {
        return isset(static::$properties[$property]);
    }

    /**
     * Gets the mutator method name for a given proeprty name.
     * Looks for methods in the form of `setPropertyValue`.
     * i.e. the mutator for `last_name` would be `setLastNameValue`.
     *
     * @param string $property property
     *
     * @return string|false method name if it exists
     */
    public static function getMutator($property)
    {
        $class = get_called_class();

        $k = $class.':'.$property;
        if (!array_key_exists($k, self::$mutators)) {
            $inflector = Inflector::get();
            $method = 'set'.$inflector->camelize($property).'Value';

            if (!method_exists($class, $method)) {
                $method = false;
            }

            self::$mutators[$k] = $method;
        }

        return self::$mutators[$k];
    }

    /**
     * Gets the accessor method name for a given proeprty name.
     * Looks for methods in the form of `getPropertyValue`.
     * i.e. the accessor for `last_name` would be `getLastNameValue`.
     *
     * @param string $property property
     *
     * @return string|false method name if it exists
     */
    public static function getAccessor($property)
    {
        $class = get_called_class();

        $k = $class.':'.$property;
        if (!array_key_exists($k, self::$accessors)) {
            $inflector = Inflector::get();
            $method = 'get'.$inflector->camelize($property).'Value';

            if (!method_exists($class, $method)) {
                $method = false;
            }

            self::$accessors[$k] = $method;
        }

        return self::$accessors[$k];
    }

    /**
     * Checks if a given property is a relationship.
     *
     * @param string $property
     *
     * @return bool
     */
    public static function isRelationship($property)
    {
        return in_array($property, static::$relationships);
    }

    /////////////////////////////
    // CRUD Operations
    /////////////////////////////

    /**
     * Saves the model.
     *
     * @return bool
     */
    public function save()
    {
        if (!$this->_exists) {
            return $this->create();
        }

        return $this->set($this->_unsaved);
    }

    /**
     * Creates a new model.
     *
     * @param array $data optional key-value properties to set
     *
     * @return bool
     *
     * @throws BadMethodCallException when called on an existing model
     */
    public function create(array $data = [])
    {
        if ($this->_exists) {
            throw new BadMethodCallException('Cannot call create() on an existing model');
        }

        if (!empty($data)) {
            foreach ($data as $k => $value) {
                $this->$k = $value;
            }
        }

        // dispatch the model.creating event
        $event = $this->dispatch(ModelEvent::CREATING);
        if ($event->isPropagationStopped()) {
            return false;
        }

        foreach (static::$properties as $name => $property) {
            // add in default values
            if (!array_key_exists($name, $this->_unsaved) && array_key_exists('default', $property)) {
                $this->_unsaved[$name] = $property['default'];
            }
        }

        // validate the values being saved
        $validated = true;
        $insertArray = [];
        foreach ($this->_unsaved as $name => $value) {
            // exclude if value does not map to a property
            $property = static::getProperty($name);
            if ($property === null) {
                continue;
            }

            // cannot insert immutable values
            // (unless using the default value)
            if ($property['mutable'] == self::IMMUTABLE && $value !== $this->getPropertyDefault($property)) {
                continue;
            }

            $validated = $validated && $this->filterAndValidate($property, $name, $value);
            $insertArray[$name] = $value;
        }

        // the final validation check is to look for required fields
        // it should be ran before returning (even if the validation
        // has already failed) in order to build a complete list of
        // validation errors
        if (!$this->hasRequiredValues($insertArray) || !$validated) {
            return false;
        }

        $created = self::getDriver()->createModel($this, $insertArray);

        if ($created) {
            // determine the model's new ID
            $ids = $this->getNewIds();

            // NOTE clear the local cache before the model.created
            // event so that fetching values forces a reload
            // from the data layer
            $this->clearCache();
            $this->_values = $ids;

            // dispatch the model.created event
            $event = $this->dispatch(ModelEvent::CREATED);
            if ($event->isPropagationStopped()) {
                return false;
            }
        }

        return $created;
    }

    /**
     * Ignores unsaved values when fetching the next value.
     *
     * @return self
     */
    public function ignoreUnsaved()
    {
        $this->_ignoreUnsaved = true;

        return $this;
    }

    /**
     * Gets property values from the model.
     *
     * This method looks up values from these locations in this
     * precedence order (least important to most important):
     *  1. defaults
     *  2. data layer
     *  3. local cache
     *  4. unsaved values
     *
     * @param array $properties list of property names to fetch values of
     *
     * @return array
     */
    public function get(array $properties)
    {
        // load the values from the local model cache
        $values = $this->_values;

        // unless specified, use any unsaved values
        $ignoreUnsaved = $this->_ignoreUnsaved;
        $this->_ignoreUnsaved = false;

        if (!$ignoreUnsaved) {
            $values = array_replace($values, $this->_unsaved);
        }

        // attempt to load any missing values from the data layer
        $missing = array_diff($properties, array_keys($values));
        if (count($missing) > 0) {
            // load values for the model
            $this->refresh();
            $values = array_replace($values, $this->_values);

            // add back any unsaved values
            if (!$ignoreUnsaved) {
                $values = array_replace($values, $this->_unsaved);
            }
        }

        return $this->buildGetResponse($properties, $values);
    }

    /**
     * Builds a key-value map of the requested properties given a set of values.
     *
     * @param array $properties
     * @param array $values
     *
     * @return array
     *
     * @throws InvalidArgumentException when a property was requested not present in the values
     */
    private function buildGetResponse(array $properties, array $values)
    {
        $response = [];
        foreach ($properties as $k) {
            $accessor = self::getAccessor($k);

            // use the supplied value if it's available
            if (array_key_exists($k, $values)) {
                $response[$k] = $values[$k];
            // set any missing values to the default value
            } elseif ($property = static::getProperty($k)) {
                $response[$k] = $this->_values[$k] = $this->getPropertyDefault($property);
            // throw an exception for non-properties that do not
            // have an accessor
            } elseif (!$accessor) {
                throw new InvalidArgumentException(static::modelName().' does not have a `'.$k.'` property.');
            // otherwise the value is considered null
            } else {
                $response[$k] = null;
            }

            // call any accessors
            if ($accessor) {
                $response[$k] = $this->$accessor($response[$k]);
            }
        }

        return $response;
    }

    /**
     * Gets the IDs for a newly created model.
     *
     * @return string
     */
    protected function getNewIds()
    {
        $ids = [];
        foreach (static::$ids as $k) {
            // attempt use the supplied value if the ID property is mutable
            $property = static::getProperty($k);
            if (in_array($property['mutable'], [self::MUTABLE, self::MUTABLE_CREATE_ONLY]) && isset($this->_unsaved[$k])) {
                $ids[$k] = $this->_unsaved[$k];
            } else {
                $ids[$k] = self::getDriver()->getCreatedID($this, $k);
            }
        }

        return $ids;
    }

    /**
     * Converts the model to an array.
     *
     * @return array model array
     */
    public function toArray()
    {
        // build the list of properties to retrieve
        $properties = array_keys(static::$properties);

        // remove any hidden properties
        $hide = (property_exists($this, 'hidden')) ? static::$hidden : [];
        $properties = array_diff($properties, $hide);

        // add any appended properties
        $append = (property_exists($this, 'appended')) ? static::$appended : [];
        $properties = array_merge($properties, $append);

        // get the values for the properties
        $result = $this->get($properties);

        return $result;
    }

    /**
     * Updates the model.
     *
     * @param array $data optional key-value properties to set
     *
     * @return bool
     *
     * @throws BadMethodCallException when not called on an existing model
     */
    public function set(array $data = [])
    {
        if (!$this->_exists) {
            throw new BadMethodCallException('Can only call set() on an existing model');
        }

        // not updating anything?
        if (count($data) == 0) {
            return true;
        }

        // apply mutators
        foreach ($data as $k => $value) {
            if ($mutator = self::getMutator($k)) {
                $data[$k] = $this->$mutator($value);
            }
        }

        // dispatch the model.updating event
        $event = $this->dispatch(ModelEvent::UPDATING);
        if ($event->isPropagationStopped()) {
            return false;
        }

        // validate the values being saved
        $validated = true;
        $updateArray = [];
        foreach ($data as $name => $value) {
            // exclude if value does not map to a property
            $property = static::getProperty($name);
            if ($property === null) {
                continue;
            }

            // can only modify mutable properties
            if ($property['mutable'] != self::MUTABLE) {
                continue;
            }

            $validated = $validated && $this->filterAndValidate($property, $name, $value);
            $updateArray[$name] = $value;
        }

        if (!$validated) {
            return false;
        }

        $updated = self::getDriver()->updateModel($this, $updateArray);

        if ($updated) {
            // NOTE clear the local cache before the model.updated
            // event so that fetching values forces a reload
            // from the data layer
            $this->clearCache();

            // dispatch the model.updated event
            $event = $this->dispatch(ModelEvent::UPDATED);
            if ($event->isPropagationStopped()) {
                return false;
            }
        }

        return $updated;
    }

    /**
     * Delete the model.
     *
     * @return bool success
     */
    public function delete()
    {
        if (!$this->_exists) {
            throw new BadMethodCallException('Can only call delete() on an existing model');
        }

        // dispatch the model.deleting event
        $event = $this->dispatch(ModelEvent::DELETING);
        if ($event->isPropagationStopped()) {
            return false;
        }

        $deleted = self::getDriver()->deleteModel($this);

        if ($deleted) {
            // dispatch the model.deleted event
            $event = $this->dispatch(ModelEvent::DELETED);
            if ($event->isPropagationStopped()) {
                return false;
            }

            // NOTE clear the local cache before the model.deleted
            // event so that fetching values forces a reload
            // from the data layer
            $this->clearCache();
        }

        return $deleted;
    }

    /////////////////////////////
    // Queries
    /////////////////////////////

    /**
     * Generates a new query instance.
     *
     * @return Query
     */
    public static function query()
    {
        // Create a new model instance for the query to ensure
        // that the model's initialize() method gets called.
        // Otherwise, the property definitions will be incomplete.
        $model = new static();

        return new Query($model);
    }

    /**
     * Finds a single instance of a model given it's ID.
     *
     * @param mixed $id
     *
     * @return Model|false
     */
    public static function find($id)
    {
        $model = static::buildFromId($id);
        $values = self::getDriver()->loadModel($model);

        if (!is_array($values)) {
            return false;
        }

        return $model->refreshWith($values);
    }

    /**
     * Finds a single instance of a model given it's ID or throws an exception.
     *
     * @param mixed $id
     *
     * @return Model|false
     *
     * @throws ModelNotFoundException when a model could not be found
     */
    public static function findOrFail($id)
    {
        $model = static::find($id);
        if (!$model) {
            throw new NotFoundException('Could not find the requested '.static::modelName());
        }

        return $model;
    }

    /**
     * Gets the toal number of records matching an optional criteria.
     *
     * @param array $where criteria
     *
     * @return int total
     */
    public static function totalRecords(array $where = [])
    {
        $query = static::query();
        $query->where($where);

        return self::getDriver()->totalRecords($query);
    }

    /**
     * Checks if the model exists.
     *
     * @return bool
     */
    public function exists()
    {
        return $this->_exists;
    }

    /**
     * Loads the model from the data layer.
     *
     * @return self
     */
    public function refresh()
    {
        if (!$this->_exists) {
            return $this;
        }

        $values = self::getDriver()->loadModel($this);

        if (!is_array($values)) {
            return $this;
        }

        return $this->refreshWith($values);
    }

    /**
     * Loads values into the model retrieved from the data layer.
     *
     * @param array $values values
     *
     * @return self
     */
    public function refreshWith(array $values)
    {
        $this->_exists = true;
        $this->_values = $values;

        return $this;
    }

    /**
     * Clears the cache for this model.
     *
     * @return self
     */
    public function clearCache()
    {
        $this->_unsaved = [];
        $this->_values = [];

        return $this;
    }

    /////////////////////////////
    // Relationships
    /////////////////////////////

    /**
     * Creates the parent side of a One-To-One relationship.
     *
     * @param string $model      foreign model class
     * @param string $foreignKey identifying key on foreign model
     * @param string $localKey   identifying key on local model
     *
     * @return \Pulsar\Relation\Relation
     */
    public function hasOne($model, $foreignKey = '', $localKey = '')
    {
        // the default local key would look like `user_id`
        // for a model named User
        if (!$foreignKey) {
            $inflector = Inflector::get();
            $foreignKey = strtolower($inflector->underscore(static::modelName())).'_id';
        }

        if (!$localKey) {
            $localKey = self::DEFAULT_ID_PROPERTY;
        }

        return new HasOne($model, $foreignKey, $localKey, $this);
    }

    /**
     * Creates the child side of a One-To-One or One-To-Many relationship.
     *
     * @param string $model      foreign model class
     * @param string $foreignKey identifying key on foreign model
     * @param string $localKey   identifying key on local model
     *
     * @return \Pulsar\Relation\Relation
     */
    public function belongsTo($model, $foreignKey = '', $localKey = '')
    {
        if (!$foreignKey) {
            $foreignKey = self::DEFAULT_ID_PROPERTY;
        }

        // the default local key would look like `user_id`
        // for a model named User
        if (!$localKey) {
            $inflector = Inflector::get();
            $localKey = strtolower($inflector->underscore($model::modelName())).'_id';
        }

        return new BelongsTo($model, $foreignKey, $localKey, $this);
    }

    /**
     * Creates the parent side of a Many-To-One or Many-To-Many relationship.
     *
     * @param string $model      foreign model class
     * @param string $foreignKey identifying key on foreign model
     * @param string $localKey   identifying key on local model
     *
     * @return \Pulsar\Relation\Relation
     */
    public function hasMany($model, $foreignKey = '', $localKey = '')
    {
        // the default local key would look like `user_id`
        // for a model named User
        if (!$foreignKey) {
            $inflector = Inflector::get();
            $foreignKey = strtolower($inflector->underscore(static::modelName())).'_id';
        }

        if (!$localKey) {
            $localKey = self::DEFAULT_ID_PROPERTY;
        }

        return new HasMany($model, $foreignKey, $localKey, $this);
    }

    /**
     * Creates the child side of a Many-To-Many relationship.
     *
     * @param string $model      foreign model class
     * @param string $foreignKey identifying key on foreign model
     * @param string $localKey   identifying key on local model
     *
     * @return \Pulsar\Relation\Relation
     */
    public function belongsToMany($model, $foreignKey = '', $localKey = '')
    {
        if (!$foreignKey) {
            $foreignKey = self::DEFAULT_ID_PROPERTY;
        }

        // the default local key would look like `user_id`
        // for a model named User
        if (!$localKey) {
            $inflector = Inflector::get();
            $localKey = strtolower($inflector->underscore($model::modelName())).'_id';
        }

        return new BelongsToMany($model, $foreignKey, $localKey, $this);
    }

    /**
     * Loads a given relationship (if not already) and returns
     * its results.
     *
     * @param string $name
     *
     * @return mixed
     */
    protected function loadRelationship($name)
    {
        if (!isset($this->_values[$name])) {
            $relationship = $this->$name();
            $this->_values[$name] = $relationship->getResults();
        }

        return $this->_values[$name];
    }

    /////////////////////////////
    // Events
    /////////////////////////////

    /**
     * Gets the event dispatcher.
     *
     * @return \Symfony\Component\EventDispatcher\EventDispatcher
     */
    public static function getDispatcher($ignoreCache = false)
    {
        $class = get_called_class();
        if ($ignoreCache || !isset(self::$dispatchers[$class])) {
            self::$dispatchers[$class] = new EventDispatcher();
        }

        return self::$dispatchers[$class];
    }

    /**
     * Subscribes to a listener to an event.
     *
     * @param string   $event    event name
     * @param callable $listener
     * @param int      $priority optional priority, higher #s get called first
     */
    public static function listen($event, callable $listener, $priority = 0)
    {
        static::getDispatcher()->addListener($event, $listener, $priority);
    }

    /**
     * Adds a listener to the model.creating event.
     *
     * @param callable $listener
     * @param int      $priority
     */
    public static function creating(callable $listener, $priority = 0)
    {
        static::listen(ModelEvent::CREATING, $listener, $priority);
    }

    /**
     * Adds a listener to the model.created event.
     *
     * @param callable $listener
     * @param int      $priority
     */
    public static function created(callable $listener, $priority = 0)
    {
        static::listen(ModelEvent::CREATED, $listener, $priority);
    }

    /**
     * Adds a listener to the model.updating event.
     *
     * @param callable $listener
     * @param int      $priority
     */
    public static function updating(callable $listener, $priority = 0)
    {
        static::listen(ModelEvent::UPDATING, $listener, $priority);
    }

    /**
     * Adds a listener to the model.updated event.
     *
     * @param callable $listener
     * @param int      $priority
     */
    public static function updated(callable $listener, $priority = 0)
    {
        static::listen(ModelEvent::UPDATED, $listener, $priority);
    }

    /**
     * Adds a listener to the model.deleting event.
     *
     * @param callable $listener
     * @param int      $priority
     */
    public static function deleting(callable $listener, $priority = 0)
    {
        static::listen(ModelEvent::DELETING, $listener, $priority);
    }

    /**
     * Adds a listener to the model.deleted event.
     *
     * @param callable $listener
     * @param int      $priority
     */
    public static function deleted(callable $listener, $priority = 0)
    {
        static::listen(ModelEvent::DELETED, $listener, $priority);
    }

    /**
     * Dispatches an event.
     *
     * @param string $eventName
     *
     * @return ModelEvent
     */
    protected function dispatch($eventName)
    {
        $event = new ModelEvent($this);

        return static::getDispatcher()->dispatch($eventName, $event);
    }

    /////////////////////////////
    // Validation
    /////////////////////////////

    /**
     * Gets the error stack for this model instance. Used to
     * keep track of validation errors.
     *
     * @return \Infuse\ErrorStack
     */
    public function getErrors()
    {
        if (!$this->_errors) {
            $this->_errors = new ErrorStack($this->app);
        }

        return $this->_errors;
    }

    /**
     * Validates and marshals a value to storage.
     *
     * @param array  $property
     * @param string $propertyName
     * @param mixed  $value
     *
     * @return bool
     */
    private function filterAndValidate(array $property, $propertyName, &$value)
    {
        // assume empty string is a null value for properties
        // that are marked as optionally-null
        if ($property['null'] && empty($value)) {
            $value = null;

            return true;
        }

        // validate
        list($valid, $value) = $this->validate($property, $propertyName, $value);

        // unique?
        if ($valid && $property['unique'] && (!$this->_exists || $value != $this->ignoreUnsaved()->$propertyName)) {
            $valid = $this->checkUniqueness($property, $propertyName, $value);
        }

        return $valid;
    }

    /**
     * Validates a value for a property.
     *
     * @param array  $property
     * @param string $propertyName
     * @param mixed  $value
     *
     * @return bool
     */
    private function validate(array $property, $propertyName, $value)
    {
        $valid = true;

        if (isset($property['validate']) && is_callable($property['validate'])) {
            $valid = call_user_func_array($property['validate'], [$value]);
        } elseif (isset($property['validate'])) {
            $valid = Validate::is($value, $property['validate']);
        }

        if (!$valid) {
            $this->getErrors()->push([
                'error' => self::ERROR_VALIDATION_FAILED,
                'params' => [
                    'field' => $propertyName,
                    'field_name' => (isset($property['title'])) ? $property['title'] : Inflector::get()->titleize($propertyName), ], ]);
        }

        return [$valid, $value];
    }

    /**
     * Checks if a value is unique for a property.
     *
     * @param array  $property
     * @param string $propertyName
     * @param mixed  $value
     *
     * @return bool
     */
    private function checkUniqueness(array $property, $propertyName, $value)
    {
        if (static::totalRecords([$propertyName => $value]) > 0) {
            $this->getErrors()->push([
                'error' => self::ERROR_NOT_UNIQUE,
                'params' => [
                    'field' => $propertyName,
                    'field_name' => (isset($property['title'])) ? $property['title'] : Inflector::get()->titleize($propertyName), ], ]);

            return false;
        }

        return true;
    }

    /**
     * Checks if an input has all of the required values. Adds
     * messages for any missing values to the error stack.
     *
     * @param array $values
     *
     * @return bool
     */
    private function hasRequiredValues(array $values)
    {
        $hasRequired = true;
        foreach (static::$properties as $name => $property) {
            if ($property['required'] && !isset($values[$name])) {
                $property = static::getProperty($name);
                $this->getErrors()->push([
                    'error' => self::ERROR_REQUIRED_FIELD_MISSING,
                    'params' => [
                        'field' => $name,
                        'field_name' => (isset($property['title'])) ? $property['title'] : Inflector::get()->titleize($name), ], ]);

                $hasRequired = false;
            }
        }

        return $hasRequired;
    }

    /**
     * Gets the marshaled default value for a property (if set).
     *
     * @param string $property
     *
     * @return mixed
     */
    private function getPropertyDefault(array $property)
    {
        return array_value($property, 'default');
    }
}
