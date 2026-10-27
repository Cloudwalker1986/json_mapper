--TEST--
JsonField constructor validates its name, optional description, and required flag
--SKIPIF--
<?php if (!extension_loaded('json_mapper')) echo 'skip json_mapper not loaded'; ?>
--FILE--
<?php
use JsonMapper\Attributes\JsonField;

try {
    new JsonField('');
} catch (ValueError $error) {
    echo "empty field name rejected\n";
}

try {
    new JsonField(123);
} catch (TypeError $error) {
    echo "non-string field name rejected\n";
}

try {
    new JsonField('valid', 123);
} catch (TypeError $error) {
    echo "non-string description rejected\n";
}

try {
    new JsonField('valid', null, 'true');
} catch (TypeError $error) {
    echo "non-boolean required flag rejected\n";
}
?>
--EXPECT--
empty field name rejected
non-string field name rejected
non-string description rejected
non-boolean required flag rejected
