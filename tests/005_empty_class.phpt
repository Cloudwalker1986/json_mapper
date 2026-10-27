--TEST--
Empty classes produce an empty properties map for instances and class names
--SKIPIF--
<?php if (!extension_loaded('json_mapper')) echo 'skip json_mapper not loaded'; ?>
--FILE--
<?php
class EmptySchemaClass {}

$fromObject = json_schema_for_object(new EmptySchemaClass());
$fromClass = json_schema_for_object(EmptySchemaClass::class);
var_dump($fromObject['class'], $fromObject['properties']);
var_dump($fromClass['class'], $fromClass['properties']);
?>
--EXPECT--
string(16) "EmptySchemaClass"
array(0) {
}
string(16) "EmptySchemaClass"
array(0) {
}
