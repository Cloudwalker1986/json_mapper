--TEST--
JsonField validates collection item type metadata in constructors and attributes
--SKIPIF--
<?php if (!extension_loaded('json_mapper')) echo 'skip json_mapper not loaded'; ?>
--FILE--
<?php
use JsonMapper\Attributes\JsonField;

class WrongItemTypesContainer {
    #[JsonField('items', itemTypes: 'Product')]
    public array $items = [];
}
class WrongItemTypesEntry {
    #[JsonField('items', itemTypes: [123])]
    public array $items = [];
}
class EmptyItemType {
    #[JsonField('items', itemTypes: [''])]
    public array $items = [];
}
class NonListItemTypes {
    #[JsonField('items', itemTypes: ['product' => Product::class])]
    public array $items = [];
}
class TooManyJsonFieldArguments {
    #[JsonField('items', null, false, [], true)]
    public array $items = [];
}

foreach ([
    [WrongItemTypesContainer::class, TypeError::class],
    [WrongItemTypesEntry::class, TypeError::class],
    [EmptyItemType::class, ValueError::class],
    [NonListItemTypes::class, ValueError::class],
    [TooManyJsonFieldArguments::class, ArgumentCountError::class],
] as [$class, $exception]) {
    try {
        json_schema_for_object($class);
    } catch (Throwable $error) {
        var_dump($error instanceof $exception);
    }
}

foreach ([
    fn() => new JsonField('items', itemTypes: 'Product'),
    fn() => new JsonField('items', itemTypes: [123]),
    fn() => new JsonField('items', itemTypes: ['']),
    fn() => new JsonField('items', itemTypes: ['product' => Product::class]),
] as $construct) {
    try {
        $construct();
    } catch (Throwable $error) {
        var_dump($error instanceof TypeError || $error instanceof ValueError);
    }
}
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
