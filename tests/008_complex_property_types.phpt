--TEST--
Union, intersection, and untyped properties are represented without value conversion
--SKIPIF--
<?php if (!extension_loaded('json_mapper')) echo 'skip json_mapper not loaded'; ?>
--FILE--
<?php
interface TypeLeft {}
interface TypeRight {}
class BothTypes implements TypeLeft, TypeRight {}

class ComplexTypes {
    public int|string $union = 5;
    public TypeLeft&TypeRight $intersection;
    public $untyped = null;
}

$properties = json_schema_for_object(new ComplexTypes())['properties'];
var_dump($properties['union']['type'], $properties['union']['value']);
var_dump($properties['intersection']['type']);
var_dump($properties['intersection']['initialized']);
var_dump($properties['untyped']['type'], $properties['untyped']['initialized']);
?>
--EXPECT--
string(10) "string|int"
int(5)
string(18) "TypeLeft&TypeRight"
bool(false)
string(5) "mixed"
bool(true)
