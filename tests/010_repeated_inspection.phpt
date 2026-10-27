--TEST--
Repeated schema inspection preserves references and object values safely
--SKIPIF--
<?php if (!extension_loaded('json_mapper')) echo 'skip json_mapper not loaded'; ?>
--FILE--
<?php
use JsonMapper\Attributes\JsonCollectionValue;

class RepeatedInspectionItem {}

class RepeatedInspectionModel {
    #[JsonCollectionValue(RepeatedInspectionItem::class)]
    public array $values = [];
    public object $child;
}

$shared = ['sequence' => [1, 2, 3]];
$object = new RepeatedInspectionModel();
$object->values =& $shared;
$object->child = (object) ['ok' => true];
$sum = 0;

for ($i = 0; $i < 1000; $i++) {
    $schema = json_schema_for_object($object);
    $sum += $schema['properties']['values']['value']['sequence'][0];
    $schema['properties']['values']['value']['sequence'][] = 4;
    if ($schema['properties']['child']['value'] !== $object->child) {
        throw new RuntimeException('object identity changed');
    }
    unset($schema);
}

var_dump($sum);
var_dump($shared);
?>
--EXPECT--
int(1000)
array(1) {
  ["sequence"]=>
  array(3) {
    [0]=>
    int(1)
    [1]=>
    int(2)
    [2]=>
    int(3)
  }
}
