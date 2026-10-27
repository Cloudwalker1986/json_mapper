#ifndef JSON_MAPPER_ATTRIBUTES_H
#define JSON_MAPPER_ATTRIBUTES_H

#include "php.h"

bool json_mapper_validate_collection_class_name(zval *class_name);
bool json_mapper_validate_item_types(zval *item_types);
zend_result json_mapper_register_attributes(void);

#endif /* JSON_MAPPER_ATTRIBUTES_H */
