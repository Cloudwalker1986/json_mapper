--TEST--
Native JsonField attribute maps names and exposes description and required metadata
--SKIPIF--
<?php if (!extension_loaded('json_mapper')) echo 'skip json_mapper not loaded'; ?>
--FILE--
<?php
use JsonMapper\Attributes\JsonField;

class MappedProperties {
    #[JsonField(fieldName: 'first_name', description: 'Given name', required: true)]
    public string $firstName = 'John';

    #[JsonField(required: false, description: 'Email contact', fieldName: 'email_address')]
    public ?string $email = null;
}

$properties = json_schema_for_object(new MappedProperties())['properties'];
var_dump(class_exists(JsonField::class));
var_dump((new ReflectionClass(JsonField::class))->isInternal());
var_dump($properties['firstName']['field']);
var_dump($properties['firstName']['description'], $properties['firstName']['required']);
var_dump($properties['email']['field']);
var_dump($properties['email']['description'], $properties['email']['required']);

$field = new JsonField('direct_name');
var_dump($field->fieldName, $field->description, $field->required);
var_dump($field->itemTypes);
$detailed = new JsonField('detail_name', 'A description', true);
var_dump($detailed->fieldName, $detailed->description, $detailed->required);
?>
--EXPECT--
bool(true)
bool(true)
string(10) "first_name"
string(10) "Given name"
bool(true)
string(13) "email_address"
string(13) "Email contact"
bool(false)
string(11) "direct_name"
NULL
bool(false)
array(0) {
}
string(11) "detail_name"
string(13) "A description"
bool(true)
