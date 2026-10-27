--TEST--
Inherited and overridden properties retain correct metadata
--SKIPIF--
<?php if (!extension_loaded('json_mapper')) echo 'skip json_mapper not loaded'; ?>
--FILE--
<?php
use JsonMapper\Attributes\JsonField;

class SchemaParent {
    public int $id = 1;
    protected string $protectedValue = 'parent';
    private string $secret = 'parent';

    #[JsonField('parent_token', 'Inherited token', true)]
    public string $token = 'base';
}

class SchemaChild extends SchemaParent {
    public int $id = 2;
    private string $secret = 'child';
    public string $name = 'child';
}

$properties = json_schema_for_object(new SchemaChild())['properties'];
var_dump($properties['id']['value']);
var_dump($properties['token']['json_name'], $properties['token']['value']);
var_dump($properties['token']['description'], $properties['token']['required']);
var_dump($properties['protectedValue']['php_name']);
var_dump($properties['protectedValue']['visibility']);
var_dump(array_key_exists('value', $properties['protectedValue']));
var_dump(array_keys($properties['SchemaChild::$secret']));
var_dump(array_keys($properties['SchemaParent::$secret']));
?>
--EXPECT--
int(2)
string(12) "parent_token"
string(4) "base"
string(15) "Inherited token"
bool(true)
string(14) "protectedValue"
string(9) "protected"
bool(false)
array(8) {
  [0]=>
  string(8) "php_name"
  [1]=>
  string(9) "json_name"
  [2]=>
  string(11) "description"
  [3]=>
  string(8) "required"
  [4]=>
  string(10) "item_types"
  [5]=>
  string(4) "type"
  [6]=>
  string(10) "visibility"
  [7]=>
  string(11) "has_default"
}
array(8) {
  [0]=>
  string(8) "php_name"
  [1]=>
  string(9) "json_name"
  [2]=>
  string(11) "description"
  [3]=>
  string(8) "required"
  [4]=>
  string(10) "item_types"
  [5]=>
  string(4) "type"
  [6]=>
  string(10) "visibility"
  [7]=>
  string(11) "has_default"
}
