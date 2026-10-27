--TEST--
Virtual properties are reported as metadata without invoking their getter
--SKIPIF--
<?php
if (PHP_VERSION_ID < 80400) echo 'skip property hooks require PHP 8.4';
elseif (!extension_loaded('json_mapper')) echo 'skip json_mapper not loaded';
?>
--FILE--
<?php
class VirtualPropertyModel {
    public int $getterCalls = 0;

    public string $computed {
        get {
            $this->getterCalls++;
            return 'computed';
        }
    }
}

$object = new VirtualPropertyModel();
$properties = json_schema_for_object($object)['properties'];
var_dump($properties['computed']['type']);
var_dump(array_key_exists('value', $properties['computed']));
var_dump(array_key_exists('initialized', $properties['computed']));
var_dump($object->getterCalls);
?>
--EXPECT--
string(6) "string"
bool(false)
bool(false)
int(0)
