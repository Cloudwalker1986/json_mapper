--TEST--
Schema validates raw JsonField arguments and the native attribute targets properties
--SKIPIF--
<?php if (!extension_loaded('json_mapper')) echo 'skip json_mapper not loaded'; ?>
--FILE--
<?php
use JsonMapper\Attributes\JsonField;

class EmptyFieldName {
    #[JsonField('')]
    public string $value = 'x';
}
class WrongFieldNameType {
    #[JsonField(123)]
    public string $value = 'x';
}
class UnknownNamedFieldArgument {
    #[JsonField(field: 'x')]
    public string $value = 'x';
}
class TooManyFieldArguments {
    #[JsonField('x', 'description', false, [], true)]
    public string $value = 'x';
}
class WrongDescriptionType {
    #[JsonField('x', 123)]
    public string $value = 'x';
}
class WrongRequiredType {
    #[JsonField('x', null, 'yes')]
    public string $value = 'x';
}
class MissingFieldName {
    #[JsonField(description: 'description only')]
    public string $value = 'x';
}

foreach ([
    [EmptyFieldName::class, ValueError::class],
    [WrongFieldNameType::class, TypeError::class],
    [UnknownNamedFieldArgument::class, Error::class],
    [TooManyFieldArguments::class, ArgumentCountError::class],
    [WrongDescriptionType::class, TypeError::class],
    [WrongRequiredType::class, TypeError::class],
    [MissingFieldName::class, ArgumentCountError::class],
] as [$class, $exception]) {
    try {
        json_schema_for_object($class);
    } catch (Throwable $error) {
        var_dump($error instanceof $exception);
    }
}

$attribute = (new ReflectionClass(JsonField::class))->getAttributes(Attribute::class)[0];
var_dump($attribute->getArguments()[0] === Attribute::TARGET_PROPERTY);
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
