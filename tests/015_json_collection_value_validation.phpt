--TEST--
JsonCollectionValue validates its class name in constructors and attributes
--SKIPIF--
<?php if (!extension_loaded('json_mapper')) echo 'skip json_mapper not loaded'; ?>
--FILE--
<?php
use JsonMapper\Attributes\JsonCollectionValue;

class WrongCollectionClassNameType {
    #[JsonCollectionValue(123)]
    public array $items = [];
}
class EmptyCollectionClassName {
    #[JsonCollectionValue('')]
    public array $items = [];
}
class UnknownCollectionValueArgument {
    #[JsonCollectionValue(type: Product::class)]
    public array $items = [];
}
class MissingCollectionClassName {
    #[JsonCollectionValue]
    public array $items = [];
}

foreach ([
    [WrongCollectionClassNameType::class, TypeError::class],
    [EmptyCollectionClassName::class, ValueError::class],
    [UnknownCollectionValueArgument::class, Error::class],
    [MissingCollectionClassName::class, ArgumentCountError::class],
] as [$class, $exception]) {
    try {
        json_schema_for_object($class);
    } catch (Throwable $error) {
        var_dump($error instanceof $exception);
    }
}

foreach ([
    fn() => new JsonCollectionValue(123),
    fn() => new JsonCollectionValue(''),
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
