--TEST--
Schema property map uses PHP names as keys and field for JSON names
--SKIPIF--
<?php if (!extension_loaded('json_mapper')) echo 'skip json_mapper not loaded'; ?>
--FILE--
<?php
use JsonMapper\Attributes\JsonCollectionValue;

class Team {}

class FootballClub {
    #[JsonCollectionValue(Team::class)]
    private array $team;

    private string $name;
}

var_dump(json_schema_for_object(FootballClub::class));
?>
--EXPECT--
array(2) {
  ["class"]=>
  string(12) "FootballClub"
  ["properties"]=>
  array(2) {
    ["team"]=>
    array(5) {
      ["field"]=>
      string(4) "team"
      ["description"]=>
      NULL
      ["required"]=>
      bool(false)
      ["item_types"]=>
      array(1) {
        [0]=>
        string(4) "Team"
      }
      ["type"]=>
      string(5) "array"
    }
    ["name"]=>
    array(5) {
      ["field"]=>
      string(4) "name"
      ["description"]=>
      NULL
      ["required"]=>
      bool(false)
      ["item_types"]=>
      array(0) {
      }
      ["type"]=>
      string(6) "string"
    }
  }
}
