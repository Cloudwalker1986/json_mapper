--TEST--
Schema inspection never invokes magic property accessors for non-public values
--SKIPIF--
<?php if (!extension_loaded('json_mapper')) echo 'skip json_mapper not loaded'; ?>
--FILE--
<?php
class MagicAccessModel {
    public int $visible = 7;
    public int $reads = 0;
    private int $hidden = 9;

    public function __get(string $name): mixed {
        $this->reads++;
        throw new RuntimeException('magic getter must not run');
    }
}

$object = new MagicAccessModel();
$properties = json_schema_for_object($object)['properties'];
var_dump($object->reads);
var_dump($properties['visible']['value']);
var_dump($properties['MagicAccessModel::$hidden']['visibility']);
var_dump(array_key_exists('value', $properties['MagicAccessModel::$hidden']));
?>
--EXPECT--
int(0)
int(7)
string(7) "private"
bool(false)
