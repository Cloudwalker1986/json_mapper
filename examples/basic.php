<?php

use JsonMapper\Attributes\JsonField;

class User
{
    public int $id = 42;

    #[JsonField(fieldName: 'first_name', description: 'Given name', required: true)]
    public string $firstName = 'John';

    public ?string $email = null;

    public string $uninitialized;
}

$schema = json_schema_for_object(new User());
echo json_encode($schema, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";

// Class-name inspection reports metadata without constructing a User.
$classSchema = json_schema_for_object(User::class);
echo json_encode($classSchema, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
