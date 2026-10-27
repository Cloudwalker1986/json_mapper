#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php.h"
#include "Zend/zend_attributes.h"
#include "Zend/zend_compile.h"
#include "Zend/zend_execute.h"
#include "Zend/zend_exceptions.h"
#include "Zend/zend_object_handlers.h"
#include "ext/standard/info.h"
#include "json_mapper_attributes.h"

#define JSON_FIELD_ATTRIBUTE "jsonmapper\\attributes\\jsonfield"
#define JSON_COLLECTION_VALUE_ATTRIBUTE "jsonmapper\\attributes\\jsoncollectionvalue"

static zend_string *property_name(zend_property_info *prop)
{
    const char *class_name;
    const char *name;
    size_t name_len;

    if (zend_unmangle_property_name_ex(prop->name, &class_name, &name, &name_len) == SUCCESS) {
        return zend_string_init(name, name_len, 0);
    }

    return zend_string_copy(prop->name);
}

static bool json_field_attribute_values(
    zend_property_info *prop,
    zend_string *php_name,
    zend_string **field_name,
    zval *description,
    bool *required,
    zval *item_types
)
{
    zend_attribute *attribute;
    zend_attribute *candidate;
    uint32_t positional_index = 0;
    uint32_t matching_attributes = 0;
    bool seen[4] = {false, false, false, false};
    uint32_t i;

    *field_name = NULL;
    ZVAL_NULL(description);
    *required = false;
    array_init(item_types);

    if (!prop->attributes) {
        *field_name = zend_string_copy(php_name);
        return true;
    }

    attribute = zend_get_attribute_str(prop->attributes, JSON_FIELD_ATTRIBUTE, sizeof(JSON_FIELD_ATTRIBUTE) - 1);
    if (!attribute) {
        *field_name = zend_string_copy(php_name);
        return true;
    }

    ZEND_HASH_FOREACH_PTR(prop->attributes, candidate) {
        if (candidate && candidate->lcname && attribute->lcname
            && zend_string_equals(candidate->lcname, attribute->lcname)) {
            matching_attributes++;
        }
    } ZEND_HASH_FOREACH_END();

    if (matching_attributes > 1) {
        zend_throw_error(NULL, "JsonMapper\\Attributes\\JsonField may only be applied once to a property");
        goto failure;
    }

    for (i = 0; i < attribute->argc; i++) {
        zend_attribute_arg *arg = &attribute->args[i];
        uint32_t parameter_index;
        zval value;

        if (!arg->name) {
            parameter_index = positional_index++;
            if (parameter_index >= 4) {
                zend_argument_count_error(
                    "JsonMapper\\Attributes\\JsonField::__construct() expects at most 4 arguments, %u given",
                    attribute->argc);
                goto failure;
            }
        } else if (zend_string_equals_literal(arg->name, "fieldName")) {
            parameter_index = 0;
        } else if (zend_string_equals_literal(arg->name, "description")) {
            parameter_index = 1;
        } else if (zend_string_equals_literal(arg->name, "required")) {
            parameter_index = 2;
        } else if (zend_string_equals_literal(arg->name, "itemTypes")) {
            parameter_index = 3;
        } else {
            zend_throw_error(NULL, "Unknown named parameter $%s", ZSTR_VAL(arg->name));
            goto failure;
        }

        if (seen[parameter_index]) {
            zend_throw_error(NULL, "Named parameter for JsonField::__construct() was passed more than once");
            goto failure;
        }
        seen[parameter_index] = true;

        ZVAL_UNDEF(&value);
        if (zend_get_attribute_value(&value, attribute, i, prop->ce) == FAILURE) {
            if (Z_TYPE(value) != IS_UNDEF) {
                zval_ptr_dtor(&value);
            }
            goto failure;
        }

        if (parameter_index == 0) {
            if (Z_TYPE(value) != IS_STRING) {
                zend_type_error(
                    "JsonMapper\\Attributes\\JsonField::__construct(): Argument #1 ($fieldName) must be of type string, %s given",
                    zend_zval_value_name(&value)
                );
                zval_ptr_dtor(&value);
                goto failure;
            }
            if (Z_STRLEN(value) == 0) {
                zend_value_error("JSON field name must not be empty");
                zval_ptr_dtor(&value);
                goto failure;
            }
            *field_name = zend_string_copy(Z_STR(value));
        } else if (parameter_index == 1) {
            if (Z_TYPE(value) != IS_NULL && Z_TYPE(value) != IS_STRING) {
                zend_type_error(
                    "JsonMapper\\Attributes\\JsonField::__construct(): Argument #2 ($description) must be of type ?string, %s given",
                    zend_zval_value_name(&value)
                );
                zval_ptr_dtor(&value);
                goto failure;
            }
            zval_ptr_dtor(description);
            ZVAL_COPY(description, &value);
        } else if (parameter_index == 2) {
            if (Z_TYPE(value) != IS_TRUE && Z_TYPE(value) != IS_FALSE) {
                zend_type_error(
                    "JsonMapper\\Attributes\\JsonField::__construct(): Argument #3 ($required) must be of type bool, %s given",
                    zend_zval_value_name(&value)
                );
                zval_ptr_dtor(&value);
                goto failure;
            }
            *required = Z_TYPE(value) == IS_TRUE;
        } else {
            if (!json_mapper_validate_item_types(&value)) {
                zval_ptr_dtor(&value);
                goto failure;
            }
            zval_ptr_dtor(item_types);
            ZVAL_COPY(item_types, &value);
        }
        zval_ptr_dtor(&value);
    }

    if (!seen[0]) {
        zend_argument_count_error(
            "JsonMapper\\Attributes\\JsonField::__construct() requires argument #1 ($fieldName)");
        goto failure;
    }

    return true;

failure:
    if (*field_name) {
        zend_string_release(*field_name);
        *field_name = NULL;
    }
    zval_ptr_dtor(description);
    ZVAL_NULL(description);
    *required = false;
    zval_ptr_dtor(item_types);
    ZVAL_UNDEF(item_types);
    return false;
}

static bool collection_value_attribute_class_name(zend_property_info *prop, zend_string **class_name)
{
    zend_attribute *attribute;
    zend_attribute *candidate;
    zend_attribute_arg *arg;
    uint32_t matching_attributes = 0;
    zval value;

    *class_name = NULL;

    if (!prop->attributes) {
        return true;
    }

    attribute = zend_get_attribute_str(
        prop->attributes,
        JSON_COLLECTION_VALUE_ATTRIBUTE,
        sizeof(JSON_COLLECTION_VALUE_ATTRIBUTE) - 1
    );
    if (!attribute) {
        return true;
    }

    ZEND_HASH_FOREACH_PTR(prop->attributes, candidate) {
        if (candidate && candidate->lcname && attribute->lcname
            && zend_string_equals(candidate->lcname, attribute->lcname)) {
            matching_attributes++;
        }
    } ZEND_HASH_FOREACH_END();

    if (matching_attributes > 1) {
        zend_throw_error(NULL, "JsonMapper\\Attributes\\JsonCollectionValue may only be applied once to a property");
        return false;
    }

    if (attribute->argc != 1) {
        zend_argument_count_error(
            "JsonMapper\\Attributes\\JsonCollectionValue::__construct() expects exactly 1 argument, %u given",
            attribute->argc
        );
        return false;
    }

    arg = &attribute->args[0];
    if (arg->name && !zend_string_equals_literal(arg->name, "className")) {
        zend_throw_error(NULL, "Unknown named parameter $%s", ZSTR_VAL(arg->name));
        return false;
    }

    ZVAL_UNDEF(&value);
    if (zend_get_attribute_value(&value, attribute, 0, prop->ce) == FAILURE) {
        if (Z_TYPE(value) != IS_UNDEF) {
            zval_ptr_dtor(&value);
        }
        return false;
    }

    if (!json_mapper_validate_collection_class_name(&value)) {
        zval_ptr_dtor(&value);
        return false;
    }

    *class_name = zend_string_copy(Z_STR(value));
    zval_ptr_dtor(&value);
    return true;
}

static bool add_collection_value_to_item_types(zend_property_info *prop, zval *item_types)
{
    zend_string *class_name;

    if (!collection_value_attribute_class_name(prop, &class_name)) {
        return false;
    }

    if (!class_name) {
        return true;
    }

    if (zend_hash_num_elements(Z_ARRVAL_P(item_types)) > 0) {
        zend_throw_error(
            NULL,
            "JsonCollectionValue cannot be combined with non-empty JsonField itemTypes on the same property"
        );
        zend_string_release(class_name);
        return false;
    }

    zval_ptr_dtor(item_types);
    array_init(item_types);
    add_next_index_str(item_types, class_name);
    return true;
}

static bool property_has_default(zend_class_entry *ce, zend_property_info *prop)
{
    uint32_t property_num;

#ifdef ZEND_ACC_VIRTUAL
    if (prop->flags & ZEND_ACC_VIRTUAL) {
        return false;
    }
#endif

    if (!ce->default_properties_table || prop->offset < OBJ_PROP_TO_OFFSET(0)) {
        return false;
    }

    property_num = OBJ_PROP_TO_NUM(prop->offset);
    if (property_num >= ce->default_properties_count) {
        return false;
    }

    return Z_TYPE(ce->default_properties_table[property_num]) != IS_UNDEF;
}

static bool property_type_contains_array(zend_property_info *prop)
{
    const zend_type *type_component;

    /* Internal PHP classes cannot be amended with userland collection attributes. */
    if (prop->ce->type == ZEND_INTERNAL_CLASS) {
        return false;
    }

    if (!ZEND_TYPE_IS_SET(prop->type)) {
        return false;
    }

    ZEND_TYPE_FOREACH(prop->type, type_component) {
        uint32_t type_mask = ZEND_TYPE_PURE_MASK(*type_component);

        /* mixed allows arrays at runtime, but does not declare a collection type. */
        if (type_mask != MAY_BE_ANY && (type_mask & MAY_BE_ARRAY)) {
            return true;
        }
    } ZEND_TYPE_FOREACH_END();

    return false;
}

static const char *property_visibility(zend_property_info *prop)
{
    if (prop->flags & ZEND_ACC_PRIVATE) {
        return "private";
    }
    if (prop->flags & ZEND_ACC_PROTECTED) {
        return "protected";
    }
    return "public";
}

static zend_string *property_schema_key(zend_property_info *prop, zend_string *php_name)
{
    if (prop->flags & ZEND_ACC_PRIVATE) {
        return strpprintf(0, "%s::$%s", ZSTR_VAL(prop->ce->name), ZSTR_VAL(php_name));
    }

    return zend_string_copy(php_name);
}

static bool add_property_schema(
    zval *properties,
    zend_class_entry *ce,
    zend_property_info *prop,
    zend_string *php_name,
    zend_object *object
)
{
    zval entry;
    zend_string *field_name;
    zend_string *schema_key;
    zend_string *type_name;
    const char *visibility;
    zval description;
    bool required;
    zval item_types;

    schema_key = property_schema_key(prop, php_name);

    /* A child declaration shadows the same public or protected parent property. */
    if (!(prop->flags & ZEND_ACC_PRIVATE)
        && zend_hash_exists(Z_ARRVAL_P(properties), schema_key)) {
        zend_string_release(schema_key);
        return true;
    }

    if (!json_field_attribute_values(prop, php_name, &field_name, &description, &required, &item_types)) {
        zend_string_release(schema_key);
        return false;
    }

    if (!add_collection_value_to_item_types(prop, &item_types)) {
        zend_string_release(field_name);
        zval_ptr_dtor(&description);
        zval_ptr_dtor(&item_types);
        zend_string_release(schema_key);
        return false;
    }

    if (property_type_contains_array(prop) && zend_hash_num_elements(Z_ARRVAL(item_types)) == 0) {
        zend_throw_error(
            NULL,
            "Array-typed property %s::$%s must declare a collection item class with JsonCollectionValue or JsonField(itemTypes: [...])",
            ZSTR_VAL(prop->ce->name),
            ZSTR_VAL(php_name)
        );
        zend_string_release(field_name);
        zval_ptr_dtor(&description);
        zval_ptr_dtor(&item_types);
        zend_string_release(schema_key);
        return false;
    }

    visibility = property_visibility(prop);

    array_init(&entry);
    add_assoc_str(&entry, "php_name", zend_string_copy(php_name));
    add_assoc_str(&entry, "json_name", field_name);
    add_assoc_zval(&entry, "description", &description);
    add_assoc_bool(&entry, "required", required);
    add_assoc_zval(&entry, "item_types", &item_types);

    if (ZEND_TYPE_IS_SET(prop->type)) {
        type_name = zend_type_to_string(prop->type);
        add_assoc_str(&entry, "type", type_name);
    } else {
        add_assoc_string(&entry, "type", "mixed");
    }

    add_assoc_string(&entry, "visibility", (char *) visibility);
    add_assoc_bool(&entry, "has_default", property_has_default(ce, prop));

    /* Values are intentionally limited to public properties with standard storage.
     * This bypasses magic accessors and custom handlers entirely. */
    if (object && (prop->flags & ZEND_ACC_PUBLIC) && object->handlers == &std_object_handlers) {
        zval *value = NULL;

#ifdef ZEND_ACC_VIRTUAL
        if (!(prop->flags & ZEND_ACC_VIRTUAL)) {
#endif
            value = OBJ_PROP(object, prop->offset);
            if (Z_TYPE_P(value) == IS_INDIRECT) {
                value = Z_INDIRECT_P(value);
            }

            if (Z_TYPE_P(value) == IS_UNDEF) {
                add_assoc_null(&entry, "value");
                add_assoc_bool(&entry, "initialized", 0);
            } else {
                zval copy;
                ZVAL_COPY_DEREF(&copy, value);
                add_assoc_zval(&entry, "value", &copy);
                add_assoc_bool(&entry, "initialized", 1);
            }
#ifdef ZEND_ACC_VIRTUAL
        }
#endif
    }

    zend_hash_update(Z_ARRVAL_P(properties), schema_key, &entry);
    zend_string_release(schema_key);
    return true;
}

PHP_FUNCTION(json_schema_for_object)
{
    zend_object *object = NULL;
    zend_string *class_name = NULL;
    zend_class_entry *ce;
    zend_property_info *prop;
    zend_string *key;
    zval properties;
    zend_class_entry *scope;

    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_OBJ_OR_STR(object, class_name)
    ZEND_PARSE_PARAMETERS_END();

    if (object) {
        ce = object->ce;
    } else {
        ce = zend_lookup_class(class_name);
        if (!ce) {
            if (!EG(exception)) {
                zend_throw_error(NULL, "Class \"%s\" not found", ZSTR_VAL(class_name));
            }
            RETURN_THROWS();
        }
    }

    array_init(return_value);
    add_assoc_str(return_value, "class", zend_string_copy(ce->name));
    array_init(&properties);

    for (scope = ce; scope; scope = scope->parent) {
        ZEND_HASH_MAP_FOREACH_STR_KEY_PTR(&scope->properties_info, key, prop) {
            zend_string *php_name;
            bool added;

            if (!prop || prop->ce != scope || (prop->flags & ZEND_ACC_STATIC)) {
                continue;
            }

            php_name = property_name(prop);
            added = add_property_schema(&properties, ce, prop, php_name, object);
            zend_string_release(php_name);
            if (!added) {
                zval_ptr_dtor(&properties);
                RETURN_THROWS();
            }
        } ZEND_HASH_FOREACH_END();
    }

    add_assoc_zval(return_value, "properties", &properties);
}

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_schema, 0, 1, IS_ARRAY, 0)
    ZEND_ARG_TYPE_MASK(0, object, MAY_BE_OBJECT | MAY_BE_STRING, NULL)
ZEND_END_ARG_INFO()

static const zend_function_entry json_mapper_functions[] = {
    PHP_FE(json_schema_for_object, arginfo_schema)
    PHP_FE_END
};

PHP_MINIT_FUNCTION(json_mapper)
{
    return json_mapper_register_attributes();
}

PHP_MINFO_FUNCTION(json_mapper)
{
    php_info_print_table_start();
    php_info_print_table_row(2, "json_mapper support", "enabled");
    php_info_print_table_row(2, "Version", "0.1.0");
    php_info_print_table_end();
}

zend_module_entry json_mapper_module_entry = {
    STANDARD_MODULE_HEADER,
    "json_mapper",
    json_mapper_functions,
    PHP_MINIT(json_mapper),
    NULL,
    NULL,
    NULL,
    PHP_MINFO(json_mapper),
    "0.1.0",
    STANDARD_MODULE_PROPERTIES
};

#ifdef COMPILE_DL_JSON_MAPPER
# ifdef ZTS
ZEND_TSRMLS_CACHE_DEFINE()
# endif
ZEND_GET_MODULE(json_mapper)
#endif
