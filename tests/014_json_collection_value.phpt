--TEST--
JsonCollectionValue declares one element class for arrays and custom collections
--SKIPIF--
<?php if (!extension_loaded('json_mapper')) echo 'skip json_mapper not loaded'; ?>
--FILE--
<?php
use JsonMapper\Attributes\JsonCollectionValue;
use JsonMapper\Attributes\JsonField;

class Product {}
class ProductCollection {}
class CreatedEvent {}
class DeletedEvent {}

class CollectionValueMetadata {
    public Product $featuredProduct;

    #[JsonCollectionValue(Product::class)]
    public array $products = [];

    #[JsonCollectionValue(className: Product::class)]
    public ProductCollection $customCollection;

    #[JsonField('events', itemTypes: [CreatedEvent::class, DeletedEvent::class])]
    public array $events = [];

    #[JsonField('products_with_name', itemTypes: [])]
    #[JsonCollectionValue(Product::class)]
    public array $productsWithName = [];
}

$properties = json_schema_for_object(CollectionValueMetadata::class)['properties'];
var_dump(class_exists(JsonCollectionValue::class));
var_dump((new ReflectionClass(JsonCollectionValue::class))->isInternal());
var_dump($properties['featuredProduct']['type'] === Product::class);
var_dump($properties['featuredProduct']['item_types']);
var_dump($properties['products']['item_types'] === [Product::class]);
var_dump($properties['customCollection']['item_types'] === [Product::class]);
var_dump($properties['events']['item_types'] === [CreatedEvent::class, DeletedEvent::class]);
var_dump($properties['productsWithName']['item_types'] === [Product::class]);

$attribute = new JsonCollectionValue(Product::class);
var_dump($attribute->className);
$target = (new ReflectionClass(JsonCollectionValue::class))->getAttributes(Attribute::class)[0];
var_dump($target->getArguments()[0] === Attribute::TARGET_PROPERTY);

class ConflictingCollectionValue {
    #[JsonField('items', itemTypes: [CreatedEvent::class])]
    #[JsonCollectionValue(Product::class)]
    public array $items = [];
}

try {
    json_schema_for_object(ConflictingCollectionValue::class);
} catch (Error $error) {
    echo "conflicting collection declarations rejected\n";
}
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
array(0) {
}
bool(true)
bool(true)
bool(true)
bool(true)
string(7) "Product"
bool(true)
conflicting collection declarations rejected
