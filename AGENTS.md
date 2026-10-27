# Codex instructions — json_mapper

This repository is an experimental native PHP extension written in C with the Zend API. PHP 8.3 is the minimum supported version; maintain compatibility with PHP 8.4 and 8.5. Read `README.md` and the source before changing code.

## Development environment

Use Docker Compose by default, not host PHP packages.

- Start/build: `docker compose up -d --build`
- Compile and check load: `docker compose exec -T php bash -lc './scripts/build.sh'`
- Run PHPT tests: `docker compose exec -T php bash -lc './scripts/test.sh'`
- One-off build and test: `docker compose run --rm -T php bash -lc './scripts/build.sh && ./scripts/test.sh'`
- Debug container: `docker compose -f compose.yaml -f compose.debug.yaml up -d --build`
- Switch PHP versions by cleaning generated PHP build artifacts with `./scripts/clean.sh` inside the current container, stopping the stack, then rebuilding with `PHP_VERSION=8.4` or `PHP_VERSION=8.5`. Prefer separate worktrees for simultaneous versions.

Do not claim tests passed unless the commands actually ran. Report Docker or version availability blockers accurately. GitHub Actions has separate workflows for PHP 8.3, 8.4, and 8.5; keep their build and PHPT steps aligned.

## Current implementation

The current milestone implements:

- `json_schema_for_object(object|string $object): array`
- Native `JsonMapper\Attributes\JsonField`, targeted to properties, with `fieldName`, nullable `description`, `required` defaulting to false, and optional `itemTypes` class-name list
- Native `JsonMapper\Attributes\JsonCollectionValue`, targeted to properties, with required `className` for one homogeneous collection item type
- Attribute class registration and constructors live in `src/json_mapper_attributes.c`; schema inspection and raw attribute argument parsing live in `src/json_mapper.c`
- Declared, non-static property metadata, including inherited and overridden properties
- PHP and JSON property names, description, required state, collection `item_types` from either collection metadata attribute, Zend-rendered type, visibility, and `has_default`
- User-declared array, nullable-array, and union properties containing `array` require at least one declared item class; `mixed` properties and internal PHP class properties are exempt
- Runtime `value` and `initialized` for public properties on objects with standard Zend property storage
- Metadata-only inspection for class-name input; no object is constructed

Private and protected properties are reported as metadata only. Public values are read directly from standard object storage, without magic getters or setters. Runtime fields are omitted for non-public properties, custom object handlers, and class-name input. Uninitialized public properties have `value => null` and `initialized => false`; explicit `null` has `initialized => true`. Static and dynamic properties are ignored. See `README.md` for the output shape and limitations.

The 16-test PHPT suite covers schema basics, `JsonField` mapping and metadata defaults, `JsonCollectionValue`, homogeneous and heterogeneous collection item types, required array item declarations, nullable and uninitialized properties, default state, inheritance, empty classes, invalid attribute arguments, union/intersection types, magic-access safety, repeated inspection, and PHP 8.4+ virtual properties. The virtual-property case is skipped on PHP 8.3. Run the suite inside Docker after C changes.

## Next milestones

Do not implement these until the user requests them:

1. `json_encode_object(object $object): string`, reusing `ext/json` where appropriate and defining behavior for uninitialized values.
2. `json_decode_object(string $json, string $className): object`, with type-safe assignments, class allowlisting, and explicit constructor/readonly policies.
3. Encoding and decoding of nested values and collections. `itemTypes` and `JsonCollectionValue` currently report declared metadata only; they do not validate runtime collection values or choose a class for heterogeneous decoding.
4. Metadata caching and benchmarks after correctness is established.

## Engineering rules

- Use native Zend APIs; avoid userland Reflection and side effects where practical.
- Never construct objects or invoke magic accessors during schema inspection.
- Maintain zval and zend_string ownership, including failure paths and reference-valued properties.
- Keep PHPT coverage for behavior and edge cases; do not implement encoding or decoding before schema behavior remains stable.
- Preserve `config.m4`/`phpize`; do not replace the build with CMake.
- Prefer debug builds with `-O0 -g3 -fno-omit-frame-pointer` while developing.
- Diagnose PHP 8.3–8.5 API differences instead of suppressing incompatibilities.
- Keep the README and this file aligned with the implementation and commands that were actually run.
