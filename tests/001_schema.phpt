--TEST--
Schema reports declared property names, types, defaults, values, and initialization
--SKIPIF--
<?php if (!extension_loaded('json_mapper')) echo 'skip json_mapper not loaded'; ?>
--FILE--
<?php
class SchemaBasic {
    public int $id = 42;
    public ?string $name = null;
    public string $unset;
    public static int $ignored = 7;
}

$properties = json_schema_for_object(new SchemaBasic())['properties'];
var_dump(array_keys($properties));
var_dump($properties['id']['php_name'], $properties['id']['type']);
var_dump($properties['id']['visibility'], $properties['id']['has_default']);
var_dump($properties['id']['description'], $properties['id']['required']);
var_dump($properties['id']['initialized'], $properties['id']['value']);
var_dump($properties['name']['type'], $properties['name']['has_default']);
var_dump($properties['name']['initialized'], $properties['name']['value']);
var_dump($properties['unset']['has_default']);
var_dump($properties['unset']['initialized'], $properties['unset']['value']);
?>
--EXPECT--
array(3) {
  [0]=>
  string(2) "id"
  [1]=>
  string(4) "name"
  [2]=>
  string(5) "unset"
}
string(2) "id"
string(3) "int"
string(6) "public"
bool(true)
NULL
bool(false)
bool(true)
int(42)
string(7) "?string"
bool(true)
bool(true)
NULL
bool(false)
bool(false)
NULL
