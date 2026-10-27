--TEST--
JsonSchema OOP wrapper draws the same schema as the procedural API
--SKIPIF--
<?php if (!extension_loaded('json_mapper')) echo 'skip json_mapper not loaded'; ?>
--FILE--
<?php
use JsonMapper\JsonSchema;

class OopSchemaModel {
    public int $id = 7;
}

$classSchema = json_schema_for_object(OopSchemaModel::class);
$builder = new JsonSchema(OopSchemaModel::class);
var_dump((new ReflectionClass(JsonSchema::class))->isInternal());
var_dump($builder->draw() === $classSchema);

$objectBuilder = new JsonSchema(new OopSchemaModel());
$objectSchema = $objectBuilder->draw();
var_dump($objectSchema['properties']['id']['value']);
var_dump($objectSchema['properties']['id']['initialized']);

try {
    new JsonSchema([]);
} catch (TypeError $error) {
    echo "invalid target rejected\n";
}
?>
--EXPECT--
bool(true)
bool(true)
int(7)
bool(true)
invalid target rejected
