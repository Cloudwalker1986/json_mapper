--TEST--
Class-name input returns metadata without constructing an object or inventing values
--SKIPIF--
<?php if (!extension_loaded('json_mapper')) echo 'skip json_mapper not loaded'; ?>
--FILE--
<?php
class MetadataOnly {
    public static int $constructed = 0;
    public int $defaulted = 9;
    public ?string $withoutDefault;

    public function __construct() {
        self::$constructed++;
    }
}

$schema = json_schema_for_object(MetadataOnly::class);
$properties = $schema['properties'];
var_dump($schema['class']);
var_dump(MetadataOnly::$constructed);
var_dump($properties['defaulted']['field'], $properties['defaulted']['type']);
var_dump(array_key_exists('value', $properties['defaulted']));
var_dump(array_key_exists('initialized', $properties['defaulted']));
var_dump(array_key_exists('value', $properties['withoutDefault']));
?>
--EXPECT--
string(12) "MetadataOnly"
int(0)
string(9) "defaulted"
string(3) "int"
bool(false)
bool(false)
bool(false)
