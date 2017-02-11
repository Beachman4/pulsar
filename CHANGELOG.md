# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
### Added
- Relationships.
- Each model maintains its own error stack. Can be obtained with `errors()`.
- Added `valid()` method checking if a model has valid values.
- Added `custom` validation rule for specifying custom validations.
- Added `skip_empty` validation rule for skipping further validation when a value is empty.
- Now ships with nice human-readable error messages that can be overriden with `pulsar.validations.*` locale phrases.
- Property titles are now looked up from `pulsar.properties.*` locale phrases.
- Added `find()` and `findOrFail()` methods for looking up models from the data store by ID.
- `AdapterException` represents errors that occur in the data layer.
- Added model adapter service for Infuse Framework.
- Can check if models are persisted with `persisted()`
- Added `getTablename()` method for custom table names.

### Changed
- Model properties are now determined by the values the specific model instance contains at run-time instead of being pre-defined.
- Removed internal `_id` property.
- Throw an exception when accessing a non-property value without an accessor.
- Model constructor signature has changed. Now accepts an array of property values only.
- All exceptions inherit from `ModelException`.
- Refactored model validations and error messages.
- Uniqueness constraint is now a validation rule called `unique`.
- Required constraint is now a validation rule called `required`.
- Model validation rules are now specified at `::$validations`
- `loadModel()` is now accomplished with a `queryModels()` call.
- `clearCache()` is only available to models with the `Cacheable` trait.
- Mutability was refactored into mass assignments. Can use `::$protected` and `::$permitted` for controlling mass assignable properties.
- Moved adapter unserialization logic to `Model::cast()`.
- Property type definitions moved to `::$casts`. No type casting is performed by default.
- Split `number` type into `integer` and `float`.
- Cast values in `refreshWith()` and `setValue()`.
- Date values are now `Carbon` objects instead of integers.
- Auto timestamps, `created_at` and `updated_at` are generated in the model layer instead of by the data layer.
- Support custom date formats in the data layer, in addition to the default UNIX timestamp. Can be specified per-property with `::$dates`.
- Support Symfony 3.
- Use PSR-6 in `Cacheable` trait.
- Deprecated `relation()`.

### Removed
- Removed `toArrayDeprecated()`.
- Removed deprecated hooks for create, update, and delete operations.
- Removed `exists()`.
- Removed `Validate::is()`.
- Removed default property values. Can instead override the constructor to implement default values.
- Removed `db_timestamp` validation rule.
- Removed DI container from models to become framework-agnostic.

### Fixed
- Throw exception when calling `create()`, `set()`, or `delete()` inappropriately.
- Any models returned by `toArray()` are converted into arrays also.
- Saving or deleting a model no longer clears its local cache.
- Cannot call `refresh()` on a model that is not persisted yet.

## 0.1.0 - 2015-12-22
### Added
- Initial release! Project imported from `infuse/libs`.