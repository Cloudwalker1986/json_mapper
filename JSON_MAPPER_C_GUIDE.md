# Walkthrough of `src/json_mapper.c`

This guide explains how `src/json_mapper.c` implements `json_schema_for_object()`. Native attribute classes, their constructors, validation helpers, and registration live in [`src/json_mapper_attributes.c`](src/json_mapper_attributes.c) and are covered in [`JSON_MAPPER_ATTRIBUTES_C_GUIDE.md`](JSON_MAPPER_ATTRIBUTES_C_GUIDE.md). It follows the schema source and assumes only basic C knowledge.

## The whole call in five steps

1. PHP loads the extension and the module startup function registers the native `JsonField` and `JsonCollectionValue` classes.
2. PHP code calls `json_schema_for_object($objectOrClassName)` or `(new JsonMapper\JsonSchema($objectOrClassName))->draw()`.
3. The extension finds the class's declared properties, including declarations in parent classes.
4. For each property, it builds metadata and reads a runtime value only when that is safe.
5. It returns a PHP array containing the class name and a property map keyed by PHP property name.

For example:

```php
use JsonMapper\Attributes\JsonField;

class User
{
    #[JsonField(fieldName: 'first_name', description: 'Given name', required: true)]
    public string $firstName = 'John';
}

$schema = json_schema_for_object(new User());
```

The entry for `firstName` includes `field`, `description`, `required`, `item_types`, and `type`. Since an object was passed, it also includes `value` and `initialized` for this public property when it has standard, non-virtual storage.

If a user-declared property type includes `array`, its `item_types` list must not be empty. `JsonField(itemTypes: [...])` or `JsonCollectionValue(...)` supplies that metadata before the property entry is created.

## A few Zend types used throughout

- `zend_class_entry` holds metadata about a PHP class, including its name, parent class, properties, and default property values.
- `zend_property_info` describes one declared property: its name, type, visibility, storage offset, and attributes.
- `zend_object` is the runtime PHP object. Its class entry identifies the class, and its property slots hold stored values.
- `zval` is Zend's container for a PHP value. It can contain a string, integer, boolean, array, object, `null`, and other PHP values.
- `zend_string` is Zend's managed string type. It is reference counted, so code must copy or release it correctly.

`IS_UNDEF` is an internal state used for an uninitialized typed property. `IS_NULL` means the property was initialized and its value is PHP `null`; those states are different.

## Includes and native attributes

`php.h` provides the PHP extension API. The Zend headers used by this source define attribute metadata, class and property metadata, class lookup, exception helpers, and safe property-slot access. `json_mapper_attributes.h` exposes the shared item-type validators and the attribute registration function.

`JSON_FIELD_ATTRIBUTE` and `JSON_COLLECTION_VALUE_ATTRIBUTE` store the fully qualified attribute names in lowercase. Zend keeps normalized lowercase names for attribute lookup, so the code finds each attribute regardless of how PHP code capitalizes its spelling.

`json_mapper.c` reads raw attribute arguments with Zend APIs. It does not instantiate the native attribute classes or call their constructors. The attribute classes and their constructor validation live in the separate source file linked from the guide header.

## `property_name`

Zend stores private and protected property names in a special form that includes visibility and declaring-class information. For example, an internal private name may contain null bytes around the class and property names.

`property_name()` calls `zend_unmangle_property_name_ex()` to recover the regular PHP spelling. If Zend cannot unmangle the name, it copies the original string. It returns a new `zend_string`; the caller releases that string after using it.

This keeps the public schema understandable. A private property is reported as `secret`, not with Zend's internal null-byte encoding.

## `json_field_attribute_values`

This helper reads a property's `JsonField` metadata. Its output parameters are:

- `field_name`: the JSON field name.
- `description`: a PHP string or `null`.
- `required`: a C boolean.
- `item_types`: a zval list of class-name strings.

It starts with the defaults: use the PHP property name as the field name, set description to `null`, set required to `false`, and set item types to an empty array. If the property has no `JsonField` attribute, those defaults are returned immediately.

When an attribute is present, the helper:

1. Counts matching attributes and rejects repeated `JsonField` attributes on the same property.
2. Walks the raw attribute arguments in source order.
3. Maps positional arguments to indexes 0 through 3, and named arguments to `fieldName`, `description`, `required`, and `itemTypes`.
4. Rejects unknown names, duplicate arguments, and more than four positional arguments.
5. Uses `zend_get_attribute_value()` to resolve each argument value. This reads the attribute arguments; it does not construct a `JsonField` object or invoke its constructor.
6. Checks each value's type, validates that `fieldName` is non-empty, and calls `json_mapper_validate_item_types()` for the element class list.

The `seen` array remembers which parameters were supplied. This is how the helper detects duplicates and verifies that the required `fieldName` was provided. `positional_index` tracks the next positional parameter. After validation, the helper copies the item type array into the output zval.

If any check fails, the code jumps to the `failure` label. That block releases any field-name string, description value, and item type array already created, restores safe output states, and returns `false`. The caller then stops building the schema and propagates the PHP exception.

## `collection_value_attribute_class_name`

This helper looks for `JsonCollectionValue` in the property's raw attributes. If present, it requires exactly one argument, accepts either a positional argument or the named argument `className`, validates that the value is a non-empty string, and returns a copied `zend_string`.

It counts matching attributes so repeated uses produce an error. Like the `JsonField` parser, it reads raw arguments with `zend_get_attribute_value()` and never constructs an attribute object or invokes its constructor.

## `add_collection_value_to_item_types`

This helper merges a `JsonCollectionValue` class name into the schema's `item_types` list. If no such attribute exists, it leaves the list unchanged. If one exists, it adds the class name as the single item type, unless `JsonField(itemTypes: [...])` already supplied a non-empty list; that conflict is reported as an error so one property's element type cannot be described two different ways.

The helper releases the old empty list, creates a new list, and uses `add_next_index_str()` to transfer ownership of the copied class-name string into it.

## `property_type_contains_array`

This helper checks the Zend type mask for an explicit array type. It returns true for user-declared `array`, nullable array types, and union types that include array. It returns false for untyped and `mixed` properties; `mixed` can contain an array at runtime but does not declare a collection type. Internal PHP classes are also exempt because userland cannot add these attributes to their property declarations.

The `MAY_BE_ANY` check distinguishes `mixed` from an explicit `array` bit. This is why the extension does not require `item_types` just because a `mixed` property's current value happens to be an array.

## Property map key

The returned `properties` map uses the unmangled PHP property name for every visibility. If a child declaration has a property with the same PHP name as an inherited declaration, the child declaration is kept and the parent declaration is skipped. This ensures private property keys stay plain PHP names, with one deterministic entry for duplicate names.

## `add_property_schema`

This helper creates one property entry and places it in the `properties` array.

First, it creates the key from the PHP property name. If a child declaration has already added a property with that name, the parent declaration is skipped, regardless of visibility.

Next, the helper calls `json_field_attribute_values()` to get the field name, description, required flag, and item type list. It then creates a PHP array and adds:

- `field`, containing the mapped JSON field name;
- `description` and `required`;
- `item_types`, copied from the attribute or an empty array;
- the declared type, or `mixed` when the property has no declared type;

Before creating the entry, it calls `add_collection_value_to_item_types()` so a `JsonCollectionValue` attribute is represented by a one-element `item_types` list.

It then calls `property_type_contains_array()`. If a user-declared type includes `array` and the item type list is empty, it throws `Error`, releases the property metadata created so far, and stops the schema build. A non-empty `item_types` list satisfies the requirement regardless of which attribute supplied it.

The `add_assoc_*` calls insert values into the PHP array. When a string or zval is passed to `add_assoc_str()` or `add_assoc_zval()`, ownership moves into the array entry; the caller must not release it a second time.

Runtime values are added only if an object was supplied, the property is public, and the object uses `std_object_handlers`. The function reads the storage slot directly with `OBJ_PROP()`. This avoids invoking magic getters or custom handlers. For a virtual property, the source skips this slot access.

If the slot is `IS_UNDEF`, the entry gets `value => null` and `initialized => false`. Otherwise `ZVAL_COPY_DEREF()` copies the value while dereferencing a PHP reference, and the entry gets `initialized => true`. An explicitly initialized `null` therefore has `initialized => true`.

Finally, `zend_hash_update()` inserts the completed entry under its schema key. The temporary key string is released after insertion.

## `json_mapper_build_schema`, `json_schema_for_object`, and `JsonMapper\JsonSchema`

`json_mapper_build_schema()` is the shared internal implementation. It accepts either a `zend_object` or a class-name `zend_string`, creates the output array, and adds class metadata and the property map. Both public APIs call this helper.

`json_schema_for_object()` is the procedural entry point. `arginfo_schema` declares its parameter as `object|string`, and `Z_PARAM_OBJ_OR_STR()` parses either form before calling the shared helper.

If the argument is an object, the function reads its class entry from the runtime object. If the argument is a string, `zend_lookup_class()` finds the class entry. Normal PHP autoloading may occur during this lookup, but the extension does not instantiate the class or call its constructor.

The helper creates the return array, adds the class name, and creates a separate `properties` array. It walks the class and each parent class. For each scope, it:

1. Skips static properties.
2. Skips inherited copies whose declaring class is a different scope. The parent scope will handle those declarations, preventing duplicates.
3. Converts the Zend property name to its regular PHP name.
4. Calls `add_property_schema()`.
5. Releases the temporary property-name string.

If a property has invalid attribute data, `add_property_schema()` returns `false`. The function destroys the partially built properties array and returns the pending exception. On success, it transfers the `properties` array into the final return value.

When given a class name, the object pointer is `NULL`. As a result, the function returns property metadata but omits `value` and `initialized` because no runtime property state exists.

`JsonMapper\JsonSchema` is a final internal class registered during module startup. Its constructor stores an object or class-name string in a private `target` property. `draw()` reads that declared property and calls the same builder. It does not duplicate the schema logic or cache the result; each call draws the target's current schema.

## Function table and module startup

`json_mapper_functions` registers `json_schema_for_object()` as a global PHP function and ends with `PHP_FE_END`.

`PHP_MINIT_FUNCTION(json_mapper)` runs when PHP starts the extension, calls `json_mapper_register_attributes()` from `json_mapper_attributes.c`, and registers the native `JsonMapper\JsonSchema` class and methods.

`PHP_MINFO_FUNCTION(json_mapper)` supplies the small status table shown by `phpinfo()`.

`json_mapper_module_entry` connects the extension name, global functions, startup hook, info hook, and version. `ZEND_GET_MODULE(json_mapper)` exports the loader entry point used when PHP loads the compiled shared extension. The `ZTS` conditional defines a thread-safe resource cache when PHP is built with thread safety.

## Memory ownership cheat sheet

| Operation | Meaning in this file |
|---|---|
| `zend_string_copy()` | Keep another reference to an existing managed string. |
| `zend_string_release()` | Release a string reference owned by the current code. |
| `ZVAL_COPY()` | Copy a zval and retain references to its managed contents. |
| `ZVAL_COPY_DEREF()` | Copy the value behind a PHP reference instead of returning the reference wrapper. |
| `zval_ptr_dtor()` | Release the managed contents held by a zval. |
| `add_assoc_str()` / `add_assoc_zval()` | Put a value into a PHP array and transfer ownership to that array entry. |
| `add_next_index_str()` | Append a string to a PHP array and transfer ownership to that item. |

When changing this source, check both the success path and every `goto failure` or exception path. A string or zval should have one clear owner at each point in the code.

## Scope of this file

`json_mapper.c` only inspects the direct schema metadata for the requested class. It requires item metadata for explicitly array-typed properties, but it does not validate collection contents or the declared property's collection behavior, load the named classes, encode or decode JSON, traverse nested object properties, include dynamic or static properties, or read private/protected runtime values. A heterogeneous list also does not tell a future decoder which class to instantiate for each JSON item; that requires a discriminator or another selection policy. The README describes these limits and the complete returned array shape.
