--TEST--
Array-typed properties require collection item type metadata
--SKIPIF--
<?php if (!extension_loaded('json_mapper')) echo 'skip json_mapper not loaded'; ?>
--FILE--
<?php
use JsonMapper\Attributes\JsonCollectionValue;
use JsonMapper\Attributes\JsonField;

class ArrayItem {}

class ArrayWithoutItemType {
    public array $items = [];
}
class ArrayWithEmptyItemTypes {
    #[JsonField('items', itemTypes: [])]
    public array $items = [];
}
class NullableArrayWithoutItemType {
    public ?array $items = null;
}
class UnionArrayWithoutItemType {
    public array|stdClass $items;
}
class ArrayWithJsonFieldItemType {
    #[JsonField('items', itemTypes: [ArrayItem::class])]
    public array $items = [];
}
class ArrayWithCollectionValue {
    #[JsonCollectionValue(ArrayItem::class)]
    public array $items = [];
}
class MixedPropertyContainingArray {
    public mixed $items = [];
}

foreach ([
    ArrayWithoutItemType::class,
    ArrayWithEmptyItemTypes::class,
    NullableArrayWithoutItemType::class,
    UnionArrayWithoutItemType::class,
] as $class) {
    try {
        json_schema_for_object($class);
    } catch (Error $error) {
        var_dump(str_contains($error->getMessage(), 'must declare a collection item class'));
    }
}

$fieldTypes = json_schema_for_object(ArrayWithJsonFieldItemType::class)['properties']['items']['item_types'];
$collectionValue = json_schema_for_object(ArrayWithCollectionValue::class)['properties']['items']['item_types'];
$mixedTypes = json_schema_for_object(MixedPropertyContainingArray::class)['properties']['items']['item_types'];
$internalTypes = json_schema_for_object(JsonField::class)['properties']['itemTypes']['item_types'];
var_dump($fieldTypes === [ArrayItem::class]);
var_dump($collectionValue === [ArrayItem::class]);
var_dump($mixedTypes);
var_dump($internalTypes);
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
array(0) {
}
array(0) {
}
