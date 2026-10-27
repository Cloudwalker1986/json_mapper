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
var_dump($properties['token']['field'], $properties['token']['value']);
var_dump($properties['token']['description'], $properties['token']['required']);
var_dump($properties['protectedValue']['field']);
var_dump(array_key_exists('value', $properties['protectedValue']));
var_dump(array_keys($properties['secret']));
var_dump($properties['secret']['type']);
?>
--EXPECT--
int(2)
string(12) "parent_token"
string(4) "base"
string(15) "Inherited token"
bool(true)
string(14) "protectedValue"
bool(false)
array(5) {
  [0]=>
  string(5) "field"
  [1]=>
  string(11) "description"
  [2]=>
  string(8) "required"
  [3]=>
  string(10) "item_types"
  [4]=>
  string(4) "type"
}
string(6) "string"
