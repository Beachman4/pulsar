Models
=====

## Property Definitions

The properties for each model are defined using the `$properties` variable on every model class.

### `type`

The type of the property.

Accepted Types:
- string
- number
- boolean
- date
- array
- object

String, Required

### `default`

The default value to be used when creating new models.

String, Optional

### `mutable`
        Specifies whether the property can be set (mutated)
        Boolean
        Default: true
        Optional

### `unique`

Specifies whether the field is required to be unique
        Boolean
        Default: false
        Optional

### `title`

Title of the property that shows up in admin panel

String, Optional, Default: Derived from property name