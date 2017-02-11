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
use BadMethodCallException;
use Carbon\Carbon;
use ICanBoogie\Inflector;
use Infuse\Locale;
use InvalidArgumentException;
use Pulsar\Adapter\AdapterInterface;
use Pulsar\Exception\AdapterMissingException;
use Pulsar\Exception\MassAssignmentException;
use Pulsar\Exception\NotFoundException;
use Pulsar\Relation\HasOne;
use Pulsar\Relation\BelongsTo;
use Pulsar\Relation\HasMany;
use Pulsar\Relation\BelongsToMany;
use Symfony\Component\EventDispatcher\EventDispatcher;

abstract class Model implements ArrayAccess
{
    const TYPE_STRING = 'string';
    const TYPE_INTEGER = 'integer';
    const TYPE_FLOAT = 'float';
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_DATE = 'date';
    const TYPE_OBJECT = 'object';
    const TYPE_ARRAY = 'array';

    const DEFAULT_ID_PROPERTY = 'id';

    const DEFAULT_DATE_FORMAT = 'U'; // unix timestamps

    // DEPRECATED
    const TYPE_NUMBER = 'float';
    const IMMUTABLE = 0;
    const MUTABLE_CREATE_ONLY = 1;
    const MUTABLE = 2;

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
     * @staticvar array
     */
    protected static $relationshipsDeprecated = [];

    /**
     * @staticvar array
     */
    protected static $dates = [];

    /**
     * @staticvar array
     */
    protected static $dispatchers = [];

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

    /**
     * @deprecated
     *
     * @var array
     */
    protected $_relationships = [];

    /////////////////////////////
    // Base model variables
    /////////////////////////////

    /**
     * @staticvar array
     */
    private static $initialized = [];

    /**
     * @staticvar AdapterInterface
     */
    private static $adapter;

    /**
     * @staticvar Locale
     */
    private static $locale;

    /**
     * @staticvar array
     */
    private static $tablenames = [];

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
        // parse deprecated property definitions
        if (property_exists($this, 'properties')) {
            $this->setDefaultValuesDeprecated();
        }

        foreach ($values as $k => $v) {
            $this->setValue($k, $v, false);
        }

        // ensure the initialize function is called only once
        $k = get_called_class();
        if (!isset(self::$initialized[$k])) {
            $this->initialize();
            self::$initialized[$k] = true;
        }
    }

    /**
     * @deprecated
     * Sets the default values from a deprecated $properties format
     *
     * @return self
     */
    private function setDefaultValuesDeprecated()
    {
        foreach (static::$properties as $k => $definition) {
            if (isset($definition['default'])) {
                $this->setValue($k, $definition['default'], false);
            }
        }

        return $this;
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
        // parse deprecated property definitions
        if (property_exists($this, 'properties')) {
            $this->parseDeprecatedProperties();
        }

        // add in the default ID property
        if (static::$ids == [self::DEFAULT_ID_PROPERTY]) {
            if (property_exists($this, 'casts') && !isset(static::$casts[self::DEFAULT_ID_PROPERTY])) {
                static::$casts[self::DEFAULT_ID_PROPERTY] = self::TYPE_INTEGER;
            }
        }

        // generates created_at and updated_at timestamps
        if (property_exists($this, 'autoTimestamps')) {
            $this->installAutoTimestamps();
        }
    }

    /**
     * @deprecated
     * Parses a deprecated $properties format
     *
     * @return self
     */
    private function parseDeprecatedProperties()
    {
        foreach (static::$properties as $k => $definition) {
            // parse property types
            if (isset($definition['type'])) {
                static::$casts[$k] = $definition['type'];
            }

            // parse validations
            $validation = [];
            if (isset($definition['required'])) {
                $validation[] = 'required';
            }

            if (isset($definition['validate'])) {
                $validation[] = $definition['validate'];
            }

            if (isset($definition['unique'])) {
                $validation[] = 'unique';
            }

            if ($validation) {
                static::$validations[$k] = implode('|', $validation);
            }

            // parse date formats
            if (property_exists($this, 'autoTimestamps')) {
                static::$dates['created_at'] = 'Y-m-d H:i:s';
                static::$dates['updated_at'] = 'Y-m-d H:i:s';
            }

            // parse deprecated relationships
            if (isset($definition['relation'])) {
                static::$relationshipsDeprecated[$k] = $definition['relation'];
            }

            // parse protected properties
            if (isset($definition['mutable']) && in_array($definition['mutable'], [self::IMMUTABLE, self::MUTABLE_CREATE_ONLY])) {
                static::$protected[] = $k;
            }
        }

        return $this;
    }

    /**
     * Installs the automatic timestamp properties,
     * `created_at` and `updated_at`.
     */
    private function installAutoTimestamps()
    {
        if (property_exists($this, 'casts')) {
            static::$casts['created_at'] = self::TYPE_DATE;
            static::$casts['updated_at'] = self::TYPE_DATE;
        }

        self::creating(function (ModelEvent $event) {
            $model = $event->getModel();
            $model->created_at = Carbon::now();
            $model->updated_at = Carbon::now();
        });

        self::updating(function (ModelEvent $event) {
            $event->getModel()->updated_at = Carbon::now();
        });
    }

    /**
     * Sets the adapter for all models.
     *
     * @param AdapterInterface $adapter
     */
    public static function setAdapter(AdapterInterface $adapter)
    {
        self::$adapter = $adapter;
    }

    /**
     * Gets the adapter for all models.
     *
     * @return AdapterInterface
     *
     * @throws AdapterMissingException
     */
    public static function getAdapter()
    {
        if (!self::$adapter) {
            throw new AdapterMissingException('A model adapter has not been set yet.');
        }

        return self::$adapter;
    }

    /**
     * Clears the adapter for all models.
     */
    public static function clearAdapter()
    {
        self::$adapter = null;
    }

    /**
     * @deprecated
     */
    public static function setDriver(AdapterInterface $adapter)
    {
        self::$adapter = $adapter;
    }

    /**
     * @deprecated
     */
    public static function getDriver()
    {
        if (!self::$adapter) {
            throw new AdapterMissingException('A model adapter has not been set yet.');
        }

        return self::$adapter;
    }

    /**
     * @deprecated
     */
    public static function clearDriver()
    {
        self::$adapter = null;
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
     * Gets the locale instance for all models.
     *
     * @return Locale
     */
    public static function getLocale()
    {
        return self::$locale;
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
     * Gets the table name of the model.
     *
     * @return string
     */
    public function getTablename()
    {
        $name = static::modelName();
        if (!isset(self::$tablenames[$name])) {
            $inflector = Inflector::get();

            self::$tablenames[$name] = $inflector->camelize($inflector->pluralize($name));
        }

        return self::$tablenames[$name];
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
        return $this->getValues(static::$ids);
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
        $value = $this->getValue($name);
        $this->_ignoreUnsaved = false;

        return $value;
    }

    public function __set($name, $value)
    {
        $this->setValue($name, $value);
    }

    public function __isset($name)
    {
        return array_key_exists($name, $this->_unsaved) || $this->hasProperty($name);
    }

    public function __unset($name)
    {
        if (static::isRelationship($name)) {
            throw new BadMethodCallException("Cannot unset the `$name` property because it is a relationship");
        }

        if (array_key_exists($name, $this->_unsaved)) {
            unset($this->_unsaved[$name]);
        }

        // if changing property, remove relation model
        // DEPRECATED
        if (isset($this->_relationships[$name])) {
            unset($this->_relationships[$name]);
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
     * Gets the mutator method name for a given proeprty name.
     * Looks for methods in the form of `setPropertyValue`.
     * i.e. the mutator for `last_name` would be `setLastNameValue`.
     *
     * @param string $property
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
     * @param string $property
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
     * Gets the string date format for a property. Defaults to
     * UNIX timestamps.
     *
     * @param string $property
     *
     * @return string
     */
    public static function getDateFormat($property)
    {
        if (isset(static::$dates[$property])) {
            return static::$dates[$property];
        }

        return self::DEFAULT_DATE_FORMAT;
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

    /**
     * Gets the type cast for a property.
     *
     * @param string $property
     *
     * @return string|null
     */
    public static function getPropertyType($property)
    {
        if (property_exists(get_called_class(), 'casts')) {
            return array_value(static::$casts, $property);
        }
    }

    /**
     * Casts a value to a given type.
     *
     * @param string|null $type
     * @param mixed       $value
     * @param string      $property optional property name
     *
     * @return mixed casted value
     */
    public static function cast($type, $value, $property = null)
    {
        if ($value === null) {
            return;
        }

        if ($type == self::TYPE_DATE) {
            $format = self::getDateFormat($property);

            return Property::to_date($value, $format);
        }

        $m = 'to_'.$type;

        return Property::$m($value);
    }

    /**
     * Gets the properties of this model.
     *
     * @return array
     */
    public function getProperties()
    {
        return array_unique(array_merge(
            static::$ids, array_keys($this->_values)));
    }

    /**
     * Checks if the model has a property.
     *
     * @param string $property
     *
     * @return bool has property
     */
    public function hasProperty($property)
    {
        return array_key_exists($property, $this->_values) ||
               in_array($property, static::$ids);
    }

    /////////////////////////////
    // Values
    /////////////////////////////

    /**
     * Sets an unsaved value.
     *
     * @param string $name
     * @param mixed  $value
     * @param bool   $unsaved when true, sets an unsaved value
     *
     * @throws BadMethodCallException when setting a relationship
     *
     * @return self
     */
    public function setValue($name, $value, $unsaved = true)
    {
        if (static::isRelationship($name)) {
            throw new BadMethodCallException("Cannot set the `$name` property because it is a relationship");
        }

        // cast the value
        if ($type = static::getPropertyType($name)) {
            $value = static::cast($type, $value, $name);
        }

        // apply any mutators
        if ($mutator = self::getMutator($name)) {
            $value = $this->$mutator($value);
        }

        // save the value on the model property
        if ($unsaved) {
            $this->_unsaved[$name] = $value;
        } else {
            $this->_values[$name] = $value;
        }

        // if changing property, remove relation model
        // DEPRECATED
        if (isset($this->_relationships[$name])) {
            unset($this->_relationships[$name]);
        }

        return $this;
    }

    /**
     * Sets a collection values on the model from an untrusted
     * input. Also known as mass assignment.
     *
     * @param array $values
     *
     * @throws MassAssignmentException when assigning a value that is protected or not whitelisted
     *
     * @return self
     */
    public function setValues($values)
    {
        // check if the model has a mass assignment whitelist
        $permitted = (property_exists($this, 'permitted')) ? static::$permitted : false;

        // if no whitelist, then check for a blacklist
        $protected = (!is_array($permitted) && property_exists($this, 'protected')) ? static::$protected : false;

        foreach ($values as $k => $value) {
            // check for mass assignment violations
            if (($permitted && !in_array($k, $permitted)) ||
                ($protected && in_array($k, $protected))) {
                throw new MassAssignmentException("Mass assignment of $k on ".static::modelName().' is not allowed');
            }

            $this->setValue($k, $value);
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
     * Gets a list of property values from the model.
     *
     * @param array $properties list of property values to fetch
     *
     * @return array
     */
    public function getValues(array $properties)
    {
        $result = [];
        foreach ($properties as $k) {
            $result[$k] = $this->getValue($k);
        }

        $this->_ignoreUnsaved = false;

        return $result;
    }

    /**
     * @deprecated
     */
    public function get(array $properties)
    {
        return $this->getValues($properties);
    }

    /**
     * Gets a property value from the model.
     *
     * Values are looked up in this order:
     *  1. unsaved values
     *  2. local values
     *  3. relationships
     *
     * @throws InvalidArgumentException when a property was requested not present in the values
     *
     * @return mixed
     */
    private function getValue($property)
    {
        $value = null;
        $accessor = self::getAccessor($property);

        // first check for unsaved values
        if (!$this->_ignoreUnsaved && array_key_exists($property, $this->_unsaved)) {
            $value = $this->_unsaved[$property];

        // then check the normal value store
        } elseif (array_key_exists($property, $this->_values)) {
            $value = $this->_values[$property];

        // get relationship values
        } elseif (static::isRelationship($property)) {
            $value = $this->loadRelationship($property);

        // throw an exception for non-properties
        // that do not have an accessor
        } elseif ($accessor === false && !in_array($property, static::$ids)) {
            throw new InvalidArgumentException(static::modelName().' does not have a `'.$property.'` property.');
        }

        // call any accessors
        if ($accessor !== false) {
            return $this->$accessor($value);
        }

        return $value;
    }

    /**
     * Converts the model to an array.
     *
     * @return array model array
     */
    public function toArray()
    {
        // build the list of properties to retrieve
        $properties = $this->getProperties();

        // remove any hidden properties
        if (property_exists($this, 'hidden')) {
            $properties = array_diff($properties, static::$hidden);
        }

        // include any appended properties
        if (property_exists($this, 'appended')) {
            $properties = array_unique(array_merge($properties, static::$appended));
        }

        // get the values for the properties
        $result = $this->getValues($properties);

        foreach ($result as $k => &$value) {
            // convert any models to arrays
            if ($value instanceof self) {
                $value = $value->toArray();
            // convert any Carbon objects to date strings
            } elseif ($value instanceof Carbon) {
                $format = self::getDateFormat($k);
                $value = $value->format($format);
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

        return $this->set();
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

        // mass assign values passed into create()
        $this->setValues($data);

        // add in any preset values
        $this->_unsaved = array_replace($this->_values, $this->_unsaved);

        // dispatch the model.creating event
        if (!$this->dispatch(ModelEvent::CREATING)) {
            return false;
        }

        // validate the model
        if (!$this->valid()) {
            return false;
        }

        // persist the model in the data layer
        if (!self::getAdapter()->createModel($this, $this->_unsaved)) {
            return false;
        }

        // update the model with the persisted values and new ID(s)
        $newValues = array_replace(
            $this->_unsaved,
            $this->getNewIds());
        $this->refreshWith($newValues);

        // dispatch the model.created event
        return $this->dispatch(ModelEvent::CREATED);
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
            // check if the ID property was already given,
            if (isset($this->_unsaved[$k])) {
                $ids[$k] = $this->_unsaved[$k];
            // otherwise, get it from the data layer (i.e. auto-incrementing IDs)
            } else {
                $ids[$k] = self::getAdapter()->getCreatedID($this, $k);
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

        // mass assign values passed into set()
        $this->setValues($data);

        // not updating anything?
        if (count($this->_unsaved) === 0) {
            return true;
        }

        // dispatch the model.updating event
        if (!$this->dispatch(ModelEvent::UPDATING)) {
            return false;
        }

        // validate the model
        if (!$this->valid()) {
            return false;
        }

        // persist the model in the data layer
        if (!self::getAdapter()->updateModel($this, $this->_unsaved)) {
            return false;
        }

        // update the model with the persisted values
        $this->refreshWith($this->_unsaved);

        // dispatch the model.updated event
        return $this->dispatch(ModelEvent::UPDATED);
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
        if (!$this->dispatch(ModelEvent::DELETING)) {
            return false;
        }

        // delete the model in the data layer
        if (!self::getAdapter()->deleteModel($this)) {
            return false;
        }

        // dispatch the model.deleted event
        if (!$this->dispatch(ModelEvent::DELETED)) {
            return false;
        }

        $this->_persisted = false;

        return true;
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

        $values = self::getAdapter()->queryModels($query);

        if (count($values) === 0) {
            return $this;
        }

        // clear any relations
        // DEPRECATED
        $this->_relationships = [];

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
        // cast the values
        if (property_exists($this, 'casts')) {
            foreach ($values as $k => &$value) {
                if ($type = static::getPropertyType($k)) {
                    $value = static::cast($type, $value, $k);
                }
            }
        }

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

        return self::getAdapter()->totalRecords($query);
    }

    /**
     * @deprecated
     * Checks if the model exists in the database
     *
     * @return bool
     */
    public function exists()
    {
        return static::totalRecords($this->ids()) == 1;
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
        return new HasOne($this, $localKey, $model, $foreignKey);
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
        return new BelongsTo($this, $localKey, $model, $foreignKey);
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
        return new HasMany($this, $localKey, $model, $foreignKey);
    }

    /**
     * Creates the child side of a Many-To-Many relationship.
     *
     * @param string $model      foreign model class
     * @param string $tablename  pivot table name
     * @param string $foreignKey identifying key on foreign model
     * @param string $localKey   identifying key on local model
     *
     * @return \Pulsar\Relation\Relation
     */
    public function belongsToMany($model, $tablename = '', $foreignKey = '', $localKey = '')
    {
        return new BelongsToMany($this, $localKey, $tablename, $model, $foreignKey);
    }

    /**
     * Loads a given relationship (if not already) and
     * returns its results.
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

    /**
     * @deprecated
     * Gets a relationship model with a has one relationship
     *
     * @param string $k property
     *
     * @return \Pulsar\Model|null
     */
    public function relation($k)
    {
        if (!isset(static::$relationshipsDeprecated[$k])) {
            return;
        }

        if (!isset($this->_relationships[$k])) {
            $model = static::$relationshipsDeprecated[$k];
            $this->_relationships[$k] = $model::find($this->$k);
        }

        return $this->_relationships[$k];
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
     * Adds a listener to the model.creating and model.updating events.
     *
     * @param callable $listener
     * @param int      $priority
     */
    public static function saving(callable $listener, $priority = 0)
    {
        static::listen(ModelEvent::CREATING, $listener, $priority);
        static::listen(ModelEvent::UPDATING, $listener, $priority);
    }

    /**
     * Adds a listener to the model.created and model.updated events.
     *
     * @param callable $listener
     * @param int      $priority
     */
    public static function saved(callable $listener, $priority = 0)
    {
        static::listen(ModelEvent::CREATED, $listener, $priority);
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
     * @return bool true when the event propagated fully without being stopped
     */
    protected function dispatch($eventName)
    {
        $event = new ModelEvent($this);

        static::getDispatcher()->dispatch($eventName, $event);

        return !$event->isPropagationStopped();
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
        $values = $this->_unsaved + $this->_values;
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
