#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php.h"
#include "Zend/zend_attributes.h"
#include "Zend/zend_exceptions.h"

#include "json_mapper_attributes.h"

static zend_class_entry *json_field_ce;
static zend_class_entry *json_collection_value_ce;

bool json_mapper_validate_collection_class_name(zval *class_name)
{
    if (Z_TYPE_P(class_name) != IS_STRING) {
        zend_type_error(
            "JsonMapper\\Attributes\\JsonCollectionValue::__construct(): Argument #1 ($className) must be of type string, %s given",
            zend_zval_value_name(class_name)
        );
        return false;
    }

    if (Z_STRLEN_P(class_name) == 0) {
        zend_value_error("Collection item class name must not be empty");
        return false;
    }

    return true;
}

bool json_mapper_validate_item_types(zval *item_types)
{
    zend_ulong expected_index = 0;
    zend_ulong index;
    zend_string *key;
    zval *item_type;

    if (Z_TYPE_P(item_types) != IS_ARRAY) {
        zend_type_error(
            "JsonMapper\\Attributes\\JsonField::__construct(): Argument #4 ($itemTypes) must be of type array, %s given",
            zend_zval_value_name(item_types)
        );
        return false;
    }

    ZEND_HASH_FOREACH_KEY_VAL(Z_ARRVAL_P(item_types), index, key, item_type) {
        if (key || index != expected_index) {
            zend_value_error("JsonField itemTypes must be a list of class-name strings");
            return false;
        }

        if (Z_TYPE_P(item_type) != IS_STRING) {
            zend_type_error(
                "JsonMapper\\Attributes\\JsonField::__construct(): Argument #4 ($itemTypes) must contain only strings, %s given",
                zend_zval_value_name(item_type)
            );
            return false;
        }

        if (Z_STRLEN_P(item_type) == 0) {
            zend_value_error("JsonField itemTypes must not contain empty class names");
            return false;
        }

        expected_index++;
    } ZEND_HASH_FOREACH_END();

    return true;
}

static PHP_METHOD(JsonCollectionValue, __construct)
{
    zval *class_name;

    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_ZVAL(class_name)
    ZEND_PARSE_PARAMETERS_END();

    if (!json_mapper_validate_collection_class_name(class_name)) {
        RETURN_THROWS();
    }

    zend_update_property_str(
        json_collection_value_ce,
        Z_OBJ_P(ZEND_THIS),
        "className",
        sizeof("className") - 1,
        Z_STR_P(class_name)
    );
}

ZEND_BEGIN_ARG_INFO_EX(arginfo_json_collection_value_construct, 0, 0, 1)
    ZEND_ARG_TYPE_INFO(0, className, IS_STRING, 0)
ZEND_END_ARG_INFO()

static const zend_function_entry json_collection_value_methods[] = {
    PHP_ME(JsonCollectionValue, __construct, arginfo_json_collection_value_construct, ZEND_ACC_PUBLIC)
    PHP_FE_END
};

static PHP_METHOD(JsonField, __construct)
{
    zval *field_name;
    zval *description = NULL;
    zval *required = NULL;
    zval *item_types = NULL;

    ZEND_PARSE_PARAMETERS_START(1, 4)
        Z_PARAM_ZVAL(field_name)
        Z_PARAM_OPTIONAL
        Z_PARAM_ZVAL(description)
        Z_PARAM_ZVAL(required)
        Z_PARAM_ZVAL(item_types)
    ZEND_PARSE_PARAMETERS_END();

    if (Z_TYPE_P(field_name) != IS_STRING) {
        zend_type_error(
            "JsonMapper\\Attributes\\JsonField::__construct(): Argument #1 ($fieldName) must be of type string, %s given",
            zend_zval_value_name(field_name)
        );
        RETURN_THROWS();
    }

    if (Z_STRLEN_P(field_name) == 0) {
        zend_value_error("JSON field name must not be empty");
        RETURN_THROWS();
    }

    if (description && Z_TYPE_P(description) != IS_NULL && Z_TYPE_P(description) != IS_STRING) {
        zend_type_error(
            "JsonMapper\\Attributes\\JsonField::__construct(): Argument #2 ($description) must be of type ?string, %s given",
            zend_zval_value_name(description)
        );
        RETURN_THROWS();
    }

    if (required && Z_TYPE_P(required) != IS_TRUE && Z_TYPE_P(required) != IS_FALSE) {
        zend_type_error(
            "JsonMapper\\Attributes\\JsonField::__construct(): Argument #3 ($required) must be of type bool, %s given",
            zend_zval_value_name(required)
        );
        RETURN_THROWS();
    }

    if (item_types && !json_mapper_validate_item_types(item_types)) {
        RETURN_THROWS();
    }

    zend_update_property_str(json_field_ce, Z_OBJ_P(ZEND_THIS), "fieldName", sizeof("fieldName") - 1, Z_STR_P(field_name));
    if (description && Z_TYPE_P(description) == IS_STRING) {
        zend_update_property_str(json_field_ce, Z_OBJ_P(ZEND_THIS), "description", sizeof("description") - 1, Z_STR_P(description));
    } else {
        zend_update_property_null(json_field_ce, Z_OBJ_P(ZEND_THIS), "description", sizeof("description") - 1);
    }
    zend_update_property_bool(json_field_ce, Z_OBJ_P(ZEND_THIS), "required", sizeof("required") - 1,
        required && Z_TYPE_P(required) == IS_TRUE);
    if (item_types) {
        zend_update_property(json_field_ce, Z_OBJ_P(ZEND_THIS), "itemTypes", sizeof("itemTypes") - 1, item_types);
    }
}

ZEND_BEGIN_ARG_INFO_EX(arginfo_json_field_construct, 0, 0, 1)
    ZEND_ARG_TYPE_INFO(0, fieldName, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, description, IS_STRING, 1, "null")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, required, _IS_BOOL, 0, "false")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, itemTypes, IS_ARRAY, 0, "[]")
ZEND_END_ARG_INFO()

static const zend_function_entry json_field_methods[] = {
    PHP_ME(JsonField, __construct, arginfo_json_field_construct, ZEND_ACC_PUBLIC)
    PHP_FE_END
};

static void json_mapper_declare_typed_property(
    zend_class_entry *ce,
    const char *name,
    size_t name_length,
    zval *default_value,
    zend_type type
)
{
    zend_string *property_name = zend_string_init(name, name_length, 1);

    zend_declare_typed_property(ce, property_name, default_value, ZEND_ACC_PUBLIC, NULL, type);
    zend_string_release(property_name);
}

zend_result json_mapper_register_attributes(void)
{
    zend_class_entry ce;
    zval uninitialized, null_default, false_default, empty_item_types;
    zend_type field_name_type = ZEND_TYPE_INIT_CODE(IS_STRING, 0, 0);
    zend_type description_type = ZEND_TYPE_INIT_CODE(IS_STRING, 1, 0);
    zend_type required_type = ZEND_TYPE_INIT_CODE(_IS_BOOL, 0, 0);
    zend_type item_types_type = ZEND_TYPE_INIT_CODE(IS_ARRAY, 0, 0);
    zend_type collection_class_name_type = ZEND_TYPE_INIT_CODE(IS_STRING, 0, 0);

    INIT_NS_CLASS_ENTRY(ce, "JsonMapper\\Attributes", "JsonField", json_field_methods);
    json_field_ce = zend_register_internal_class(&ce);

    ZVAL_UNDEF(&uninitialized);
    json_mapper_declare_typed_property(json_field_ce, "fieldName", sizeof("fieldName") - 1, &uninitialized, field_name_type);

    ZVAL_NULL(&null_default);
    json_mapper_declare_typed_property(json_field_ce, "description", sizeof("description") - 1, &null_default, description_type);

    ZVAL_FALSE(&false_default);
    json_mapper_declare_typed_property(json_field_ce, "required", sizeof("required") - 1, &false_default, required_type);

    ZVAL_EMPTY_ARRAY(&empty_item_types);
    json_mapper_declare_typed_property(json_field_ce, "itemTypes", sizeof("itemTypes") - 1, &empty_item_types, item_types_type);
    zend_internal_attribute_register(json_field_ce, ZEND_ATTRIBUTE_TARGET_PROPERTY);

    INIT_NS_CLASS_ENTRY(ce, "JsonMapper\\Attributes", "JsonCollectionValue", json_collection_value_methods);
    json_collection_value_ce = zend_register_internal_class(&ce);

    ZVAL_UNDEF(&uninitialized);
    json_mapper_declare_typed_property(
        json_collection_value_ce,
        "className",
        sizeof("className") - 1,
        &uninitialized,
        collection_class_name_type
    );
    zend_internal_attribute_register(json_collection_value_ce, ZEND_ATTRIBUTE_TARGET_PROPERTY);

    return SUCCESS;
}
