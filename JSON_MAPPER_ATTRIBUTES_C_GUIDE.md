# Walkthrough of `src/json_mapper_attributes.c`

This file implements the native `JsonMapper\Attributes\JsonField` and `JsonMapper\Attributes\JsonCollectionValue` classes. The classes are registered by the extension, so users do not need separate PHP files or an autoloader. Schema construction stays in [`src/json_mapper.c`](src/json_mapper.c); its overview is in [`JSON_MAPPER_C_GUIDE.md`](JSON_MAPPER_C_GUIDE.md).

## Class entry pointers

`json_field_ce` and `json_collection_value_ce` are static pointers to Zend's class metadata structures. Registration fills them in during module startup. Each constructor uses its class entry when writing the attribute object's properties.

## `json_mapper_validate_collection_class_name`

This shared helper accepts only a non-empty string. It raises a `TypeError` for another value and a `ValueError` for an empty string. It does not look up the class name or invoke an autoloader.

Both `JsonCollectionValue::__construct()` and schema parsing in `json_mapper.c` use this function, so direct constructor calls and raw attribute arguments follow the same rule.

## `json_mapper_validate_item_types`

This helper checks that `itemTypes` is a PHP list with consecutive integer indexes and that each member is a non-empty string. It does not require the named classes to exist and does not load them.

An empty list is valid as `JsonField`'s default, but a property explicitly typed as `array` must provide at least one item class before a schema can be created. One class describes a homogeneous collection; multiple classes describe allowed classes for a heterogeneous collection.

## `JsonCollectionValue::__construct`

This C method backs:

```php
new JsonCollectionValue(string $className)
```

`ZEND_PARSE_PARAMETERS_START(1, 1)` requires one argument. The method captures the raw zval, validates it with `json_mapper_validate_collection_class_name()`, and stores the string in the public `className` property with `zend_update_property_str()`.

`arginfo_json_collection_value_construct` describes the parameter name and type to PHP. `json_collection_value_methods` connects the PHP method name to the C implementation.

## `JsonField::__construct`

This C method backs:

```php
new JsonField(
    string $fieldName,
    ?string $description = null,
    bool $required = false,
    array $itemTypes = [],
)
```

It parses one required and three optional zvals, then validates their exact runtime types. The manual checks prevent weak scalar coercion. `fieldName` cannot be empty, `description` may be a string or `null`, `required` must be a boolean, and `itemTypes` must pass `json_mapper_validate_item_types()`.

Invalid values raise `TypeError` or `ValueError`, and `RETURN_THROWS()` returns control to PHP with the exception pending. For valid values, the method writes `fieldName`, `description`, and `required` with the matching Zend property update helper. If `itemTypes` is supplied, it copies that zval to the attribute object's property; otherwise the declared empty-array default remains in place.

`arginfo_json_field_construct` records parameter names, types, and defaults for reflection and named arguments. `json_field_methods` connects `__construct` to the implementation.

## `json_mapper_declare_typed_property`

This small helper creates a managed `zend_string` for a property name, calls `zend_declare_typed_property()`, then releases the temporary name. It reduces repetition in class registration. The default zval and `zend_type` describe the public property declared on the native attribute class.

## `json_mapper_register_attributes`

The module startup function in `json_mapper.c` calls this helper. It registers `JsonField` and declares these public typed properties:

- `fieldName: string`, uninitialized until the constructor runs;
- `description: ?string`, defaulting to `null`;
- `required: bool`, defaulting to `false`;
- `itemTypes: array`, defaulting to the empty array.

It then registers `JsonField` for property targets only. It registers `JsonCollectionValue` with a required, uninitialized `className: string` property and the same property-only target.

The function returns `SUCCESS` to the module startup hook after both classes have been registered. It does not construct attribute instances; PHP constructs them only when user code explicitly does so or asks PHP's attribute API to instantiate them.

## How schema inspection uses the attributes

When building a schema, `json_mapper.c` reads compiled property attribute arguments directly. It uses the shared validation helpers above, but never calls `newInstance()` and never invokes either constructor. This keeps metadata inspection free of attribute-constructor side effects.

`config.m4` lists both C source files so `phpize` compiles the attribute implementation into the same extension module.
