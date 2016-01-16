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
use Infuse\Locale;
use InvalidArgumentException;
use Pulsar\Driver\DriverInterface;
use Pulsar\Exception\DriverMissingException;
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
     * Validation rules expressed as a key-value map with
     * property names as the keys.
     * i.e. ['name' => 'string:2'].
     *
     * @staticvar array
     */
    protected static $validations = [];

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
    protected $_persisted = false;

    /**
     * @var Errors
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
        ],
        'updated_at' => [
            'type' => self::TYPE_DATE,
        ],
    ];

    /**
     * @staticvar array
     */
    private static $timestampValidations = [
        'created_at' => 'timestamp|db_timestamp',
        'updated_at' => 'timestamp|db_timestamp',
    ];

    /**
     * @staticvar array
     */
    private static $initialized = [];

    /**
     * @staticvar DriverInterface
     */
    private static $driver;

    /**
     * @staticvar Locale
     */
    private static $locale;

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
        $this->_values = $values;
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

            static::$validations = array_replace(self::$timestampValidations, static::$validations);
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
     * @param DriverInterface $driver
     */
    public static function setDriver(DriverInterface $driver)
    {
        self::$driver = $driver;
    }

    /**
     * Gets the driver for all models.
     *
     * @return DriverInterface
     *
     * @throws DriverMissingException
     */
    public static function getDriver()
    {
        if (!self::$driver) {
            throw new DriverMissingException('A model driver has not been set yet.');
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
     * Sets the locale instance for all models.
     *
     * @param Locale $locale
     */
    public static function setLocale(Locale $locale)
    {
        self::$locale = $locale;
    }

    /**
     * Clears the locale for all models.
     */
    public static function clearLocale()
    {
        self::$locale = null;
    }

    /**
     * Gets the name of the model without namespacing.
     *
     * @return string
     */
    public static function modelName()
    {
        return explode('\\', get_called_class())[0];
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

    public function __toString()
    {
        return get_called_class().'('.$this->id().')';
    }

    public function __get($name)
    {
        return array_values($this->get([$name]))[0];
    }

    public function __set($name, $value)
    {
        $this->setValue($name, $value);
    }

    public function __isset($name)
    {
        return array_key_exists($name, $this->_unsaved) || static::hasProperty($name);
    }

    public function __unset($name)
    {
        if (static::isRelationship($name)) {
            throw new BadMethodCallException("Cannot unset the `$name` property because it is a relationship");
        }

        if (array_key_exists($name, $this->_unsaved)) {
            unset($this->_unsaved[$name]);
        }
    }

    public static function __callStatic($name, $parameters)
    {
        // Any calls to unkown static methods should be deferred to
        // the query. This allows calls like User::where()
        // to replace User::query()->where().
        return call_user_func_array([static::query(), $name], $parameters);
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

    /**
     * Gets the title of a property.
     *
     * @param string $name
     *
     * @return string
     */
    public static function getPropertyTitle($name)
    {
        // attmept to fetch the title from the Locale service
        $k = 'pulsar.properties.'.static::modelName().'.'.$name;
        if (self::$locale && $title = self::$locale->t($k)) {
            if ($title != $k) {
                return $title;
            }
        }

        return Inflector::get()->humanize($name);
    }

    /////////////////////////////
    // Values
    /////////////////////////////

    /**
     * Sets an unsaved value.
     *
     * @param string $name
     * @param mixed  $value
     *
     * @throws BadMethodCallException when setting a relationship
     */
    public function setValue($name, $value)
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
     *  1. local values
     *  2. unsaved values
     *
     * @param array $properties list of property names to fetch values of
     *
     * @return array
     *
     * @throws InvalidArgumentException when a property was requested not present in the values
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

        // build the response
        $result = [];
        foreach ($properties as $k) {
            $accessor = self::getAccessor($k);

            // use the supplied value if it's available
            if (array_key_exists($k, $values)) {
                $result[$k] = $values[$k];
            // get relationship values
            } elseif (static::isRelationship($k)) {
                $result[$k] = $this->loadRelationship($k);
            // set any missing values to null
            } elseif ($property = static::getProperty($k)) {
                $result[$k] = $this->_values[$k] = null;
            // throw an exception for non-properties that do not
            // have an accessor
            } elseif (!$accessor) {
                throw new InvalidArgumentException(static::modelName().' does not have a `'.$k.'` property.');
            // otherwise the value is considered null
            } else {
                $result[$k] = null;
            }

            // call any accessors
            if ($accessor) {
                $result[$k] = $this->$accessor($result[$k]);
            }
        }

        return $result;
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

        // convert any models to arrays
        foreach ($result as &$value) {
            if ($value instanceof self) {
                $value = $value->toArray();
            }
        }

        return $result;
    }

    /////////////////////////////
    // Persistence
    /////////////////////////////

    /**
     * Saves the model.
     *
     * @return bool
     */
    public function save()
    {
        if (!$this->_persisted) {
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
        if ($this->_persisted) {
            throw new BadMethodCallException('Cannot call create() on an existing model');
        }

        if (!empty($data)) {
            foreach ($data as $k => $value) {
                $this->setValue($k, $value);
            }
        }

        // add in any preset values
        $this->_unsaved = array_replace($this->_values, $this->_unsaved);

        // dispatch the model.creating event
        $event = $this->dispatch(ModelEvent::CREATING);
        if ($event->isPropagationStopped()) {
            return false;
        }

        // validate the model
        if (!$this->valid()) {
            return false;
        }

        // build the insert array
        $insertValues = [];
        foreach ($this->_unsaved as $k => $value) {
            // remove any non-existent or immutable properties
            $property = static::getProperty($k);
            if ($property === null || $property['mutable'] == self::IMMUTABLE) {
                continue;
            }

            $insertValues[$k] = $value;
        }

        if (!self::getDriver()->createModel($this, $insertValues)) {
            return false;
        }

        // update the model with the persisted values and new ID(s)
        $newValues = array_replace(
            $insertValues,
            $this->getNewIds());
        $this->refreshWith($newValues);

        // dispatch the model.created event
        $event = $this->dispatch(ModelEvent::CREATED);

        return !$event->isPropagationStopped();
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
        if (!$this->_persisted) {
            throw new BadMethodCallException('Can only call set() on an existing model');
        }

        if (!empty($data)) {
            foreach ($data as $k => $value) {
                $this->setValue($k, $value);
            }
        }

        // not updating anything?
        if (count($this->_unsaved) === 0) {
            return true;
        }

        // dispatch the model.updating event
        $event = $this->dispatch(ModelEvent::UPDATING);
        if ($event->isPropagationStopped()) {
            return false;
        }

        // validate the model
        if (!$this->valid()) {
            return false;
        }

        // build the update array
        $updateValues = [];
        foreach ($this->_unsaved as $k => $value) {
            // remove any non-existent or immutable properties
            $property = static::getProperty($k);
            if ($property === null || $property['mutable'] != self::MUTABLE) {
                continue;
            }

            $updateValues[$k] = $value;
        }

        if (!self::getDriver()->updateModel($this, $updateValues)) {
            return false;
        }

        // update the model with the persisted values
        $this->refreshWith($updateValues);

        // dispatch the model.updated event
        $event = $this->dispatch(ModelEvent::UPDATED);

        return !$event->isPropagationStopped();
    }

    /**
     * Delete the model.
     *
     * @return bool success
     */
    public function delete()
    {
        if (!$this->_persisted) {
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

            $this->_persisted = false;
        }

        return $deleted;
    }

    /**
     * Tells if the model has been persisted.
     *
     * @return bool
     */
    public function persisted()
    {
        return $this->_persisted;
    }

    /**
     * Loads the model from the data layer.
     *
     * @return self
     *
     * @throws NotFoundException
     */
    public function refresh()
    {
        if (!$this->_persisted) {
            throw new NotFoundException('Cannot call refresh() before '.static::modelName().' has been persisted');
        }

        $query = static::query();
        $query->where($this->ids());

        $values = self::getDriver()->queryModels($query);

        if (count($values) === 0) {
            return $this;
        }

        return $this->refreshWith($values[0]);
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
        $this->_persisted = true;
        $this->_values = $values;
        $this->_unsaved = [];

        return $this;
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
     * @return Model|null
     */
    public static function find($id)
    {
        $model = static::buildFromId($id);

        return static::query()->where($model->ids())->first();
    }

    /**
     * Finds a single instance of a model given it's ID or throws an exception.
     *
     * @param mixed $id
     *
     * @return Model|false
     *
     * @throws NotFoundException when a model could not be found
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
     * @return Errors
     */
    public function errors()
    {
        if (!$this->_errors) {
            $this->_errors = new Errors($this, self::$locale);
        }

        return $this->_errors;
    }

    /**
     * Checks if the model is valid in its current state.
     *
     * @return bool
     */
    public function valid()
    {
        // clear any previous errors
        $this->errors()->clear();

        // run the validator against the model values
        $validator = $this->getValidator();
        $values = $this->_values + $this->_unsaved;
        $validated = $validator->validate($values);

        // add back any modified unsaved values
        foreach (array_keys($this->_unsaved) as $k) {
            $this->_unsaved[$k] = $values[$k];
        }

        return $validated;
    }

    /**
     * Gets a new validator instance for this model.
     * 
     * @return Validator
     */
    public function getValidator()
    {
        return new Validator(static::$validations, $this->errors());
    }
}
