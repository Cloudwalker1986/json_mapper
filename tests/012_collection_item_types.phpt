--TEST--
JsonField records homogeneous and heterogeneous collection element classes
--SKIPIF--
<?php if (!extension_loaded('json_mapper')) echo 'skip json_mapper not loaded'; ?>
--FILE--
<?php
use JsonMapper\Attributes\JsonField;

class Product {}
class ProductCollection {}
class CreatedEvent {}
class DeletedEvent {}

class CollectionTypeMetadata {
    #[JsonField(fieldName: 'products', itemTypes: [Product::class])]
    public ProductCollection $products;

    #[JsonField('events', null, false, [CreatedEvent::class, DeletedEvent::class])]
    public array $events = [];

    public mixed $mixedValue = [];
}

$properties = json_schema_for_object(CollectionTypeMetadata::class)['properties'];
var_dump($properties['products']['type']);
var_dump($properties['products']['item_types'] === [Product::class]);
var_dump($properties['events']['type']);
var_dump($properties['events']['item_types'] === [CreatedEvent::class, DeletedEvent::class]);
var_dump($properties['mixedValue']['item_types']);

$field = new JsonField('direct', null, false, [Product::class]);
var_dump($field->itemTypes);
?>
--EXPECT--
string(17) "ProductCollection"
bool(true)
string(5) "array"
bool(true)
array(0) {
}
array(1) {
  [0]=>
  string(7) "Product"
}
