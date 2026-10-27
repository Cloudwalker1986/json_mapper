[![PHP 8.3 tests](https://github.com/Cloudwalker1986/json_mapper/actions/workflows/php-8.3.yml/badge.svg)](https://github.com/Cloudwalker1986/json_mapper/actions/workflows/php-8.3.yml)
[![PHP 8.4 tests](https://github.com/Cloudwalker1986/json_mapper/actions/workflows/php-8.4.yml/badge.svg)](https://github.com/Cloudwalker1986/json_mapper/actions/workflows/php-8.4.yml)
[![PHP 8.5 tests](https://github.com/Cloudwalker1986/json_mapper/actions/workflows/php-8.5.yml/badge.svg)](https://github.com/Cloudwalker1986/json_mapper/actions/workflows/php-8.5.yml)
# json_mapper

`json_mapper` is an experimental native PHP extension for inspecting and eventually mapping JSON to strongly typed PHP objects. It is written in C with the Zend API, uses the standard `phpize` / `config.m4` build, and targets PHP 8.3 and newer.

The current milestone implements native object schema inspection plus the `JsonField` and `JsonCollectionValue` property attributes. Encoding and decoding are not implemented; the extension is not production-ready.

For a function-by-function explanation of the C implementation, see the [schema guide](JSON_MAPPER_C_GUIDE.md) and the [native attribute guide](JSON_MAPPER_ATTRIBUTES_C_GUIDE.md). Schema inspection and native attribute registration live in separate C source files.

## Build and test with Docker

Docker Compose is the supported development environment. No host PHP headers or development packages are needed.

```bash
docker compose up -d --build
docker compose exec -T php bash -lc './scripts/build.sh'
docker compose exec -T php bash -lc './scripts/test.sh'
```

The build script uses `phpize`, `./configure --enable-json_mapper`, and `make`, then checks that the extension loads. It regenerates the phpize and configure output when `config.m4` changes. The test script runs the PHPT suite. C builds default to `-O0 -g3 -fno-omit-frame-pointer` for native debugging.

Three independent GitHub Actions workflows build and test PHP 8.3, 8.4, and 8.5. To switch the local container version, clean the extension artifacts inside the current container, stop it, and rebuild with the desired version:

```bash
docker compose exec -T php bash -lc './scripts/clean.sh'
docker compose down
PHP_VERSION=8.4 docker compose up -d --build
docker compose exec -T php bash -lc './scripts/build.sh && ./scripts/test.sh'
```

Repeat with `PHP_VERSION=8.3` or `PHP_VERSION=8.5`. Never load a `.so` built for a different PHP version. Use separate worktrees when developing against multiple PHP versions at once.

## Install into a local PHP

The Docker instructions above build the extension for PHP inside the container; they do not install it into the host PHP. To install locally on Linux, use the development tools and headers for the same PHP installation you want to enable. This extension supports PHP 8.3, 8.4, and 8.5.

For example, on Debian or Ubuntu, install the compiler/build tools and the matching PHP development package (replace `8.4` with your target version):

```bash
sudo apt install build-essential autoconf php8.4-dev
```

From the repository root, make sure `phpize` and `php-config` match your target PHP. If the checkout has generated files from a different PHP build, clean them first:

```bash
phpize --version
php-config --version
phpize --clean
phpize
./configure --with-php-config=/path/to/matching/php-config --enable-json_mapper
make -j"$(nproc)"
sudo make install
```

With multiple PHP versions installed, use the versioned tools for the target version—for example, `phpize8.4` and `/usr/bin/php-config8.4`—and pass that `php-config` path to `./configure`. The `phpize` build process is described in the [PHP manual](https://www.php.net/manual/en/install.pecl.phpize.php).

After installation, enable the extension in the `php.ini` or additional `.ini` directory reported by `php --ini`:

```ini
extension=json_mapper.so
```

On Debian or Ubuntu, a CLI configuration file can be created at `/etc/php/8.4/cli/conf.d/20-json_mapper.ini` (adjust the PHP version and SAPI directory). Verify the CLI installation with:

```bash
php --ri json_mapper
```

PHP-FPM and Apache may use separate `.ini` directories; enable the extension for the SAPI that runs your application and restart that service. Always build against the target PHP version rather than reusing a module compiled for another PHP installation.

## API

```php
json_schema_for_object(object|string $object): array;
```

The function accepts either an object or a class name. It returns the extension's schema metadata; it is not the JSON Schema standard. `properties` is keyed by each PHP property name, and each entry's `field` contains the JSON field name. Static and dynamic properties are excluded.

```php
use JsonMapper\Attributes\JsonField;

class User
{
    public int $id = 42;

    #[JsonField(fieldName: 'first_name', description: 'Given name', required: true)]
    public string $firstName = 'John';

    public ?string $email = null;
    public string $pending;
}

$schema = json_schema_for_object(new User());
var_dump($schema['properties']['firstName']);
```

A property entry includes `field`, `description`, `required`, `item_types`, and the declared PHP `type`. Public properties with standard Zend object storage also include `value` and `initialized` when an instance is supplied:

```php
[
    'field' => 'first_name',
    'description' => 'Given name',
    'required' => true,
    'item_types' => [],
    'type' => 'string',
    'value' => 'John',
    'initialized' => true,
]
```

The map key is the original PHP property name; the `field` value is the JSON name, which defaults to the PHP name. Without `JsonField`, the description is `null` and required is `false`. An uninitialized public property has `value => null` and `initialized => false`; an initialized nullable property containing `null` has `initialized => true`. Runtime fields appear only for public properties on standard object storage.

Visibility and declared-default flags are not part of this compact schema entry. Private and protected properties are still listed, but their runtime values and initialization state are omitted. When parent and child declarations share a PHP property name, the child declaration is used.

With a class name, the function returns class and property metadata without constructing an object. It omits `value` and `initialized`, because runtime state is unavailable. Looking up an unloaded class follows PHP's normal autoloading behavior, but does not call its constructor.

Inherited properties are included. If a child declares a property with the same PHP name as an inherited property, the child declaration is used. Private properties also use their unqualified PHP name as the map key; therefore, a child private property with the same name takes precedence over the parent's private property.

The `JsonMapper\JsonSchema` class provides an OOP entry point to the same native schema builder:

```php
use JsonMapper\JsonSchema;

$schema = (new JsonSchema(User::class))->draw();
```

Its constructor accepts an object or class name. Each `draw()` call returns the same shape as `json_schema_for_object()`.

## `JsonField` attribute

`JsonMapper\Attributes\JsonField` is registered by the extension, so no PHP source file or autoloader is required. It targets properties and takes a non-empty field name, an optional nullable description, an optional required flag, and an optional list of collection element class names:

```php
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class JsonField
{
    public function __construct(
        public string $fieldName,
        public ?string $description = null,
        public bool $required = false,
        public array $itemTypes = [],
    ) {}
}
```

`itemTypes` describes the classes permitted as collection elements. Give one class for a homogeneous custom collection or array, and multiple classes when an array may contain different object types:

```php
#[JsonField(fieldName: 'products', itemTypes: [Product::class])]
public ProductCollection $products;

#[JsonField(fieldName: 'events', itemTypes: [CreatedEvent::class, DeletedEvent::class])]
public array $events;
```

For the common homogeneous case, `JsonMapper\Attributes\JsonCollectionValue` provides a dedicated attribute:

```php
use JsonMapper\Attributes\JsonCollectionValue;

class Product {}
class ProductCollection {}

class Order
{
    public Product $featuredProduct;

    #[JsonCollectionValue(Product::class)]
    public array $products = [];

    #[JsonCollectionValue(Product::class)]
    public ProductCollection $productCollection;
}
```

A property typed as `Product` reports `Product` as its `type`; nested properties are not traversed yet. `JsonCollectionValue(Product::class)` declares that the array or custom collection contains `Product` instances. It contributes a single entry to `item_types`, the same schema key used by `JsonField(itemTypes: [...])`. Do not combine it with a non-empty `JsonField(itemTypes)` list; schema inspection reports an error if both are specified.

The native attribute has this shape:

```php
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class JsonCollectionValue
{
    public function __construct(public string $className) {}
}
```

The declared property `type` identifies the collection itself (`ProductCollection` or `array`); the schema's `item_types` array reports the allowed element class names. An omitted `itemTypes` value appears as an empty array. `JsonField` requires `itemTypes` to be a list of non-empty strings, while `JsonCollectionValue` requires one non-empty `className`; both support positional and named arguments and validate raw attribute arguments during schema inspection. The extension does not resolve those names to loaded classes, check that the property is actually a collection, or inspect collection contents, so it does not yet enforce the element types. For heterogeneous decoding, a later milestone must also define how each JSON item selects one of the allowed classes, such as a discriminator field.

Every user-declared property typed as `array` must provide at least one item class through `JsonField(itemTypes: [...])` or `JsonCollectionValue(...)`. This also applies to nullable arrays and union types that include `array`. Schema inspection throws `Error` if such a property has no item class. Internal PHP classes are exempt because their property declarations cannot be amended with these attributes. A property declared `mixed` is not treated as an array declaration, even when its current value happens to be an array.

The attribute preserves the PHP property name as the `properties` map key and returns the mapped name in `field`, alongside `description`, `required`, and `item_types`.

## Current limitations

- Runtime values are inspected only for public properties on objects using standard Zend object storage. The extension reads the property slot directly, so it does not invoke magic getters or setters. PHP 8.4+ virtual properties are metadata-only because they have no stored slot to inspect safely.
- Private and protected properties are included in schema metadata. Their runtime values and initialization state are omitted.
- Objects with custom property handlers, including some internal classes and proxies, receive metadata only because safely reading their values could invoke custom behavior.
- Class-string input has no runtime values. Unloaded class names may run an autoloader as part of PHP's normal class lookup.
- Dynamic and static properties are ignored. Nested object values are returned as zval copies; they are not recursively traversed or JSON encoded.
- Duplicate JSON field names are not checked. `field` is a mapping label, not a claim that the metadata is valid JSON Schema.
- `item_types` is declarative metadata only. The annotated property's collection behavior and runtime contents are not checked, and class-name strings are not resolved or autoloaded during schema inspection.
- Encoding, decoding, constructor hydration, readonly handling, and deserialization security policies are not implemented.

## CLion development

Open this repository in CLion on Linux and edit the C and PHPT sources locally. Build and test through Docker Compose so the PHP headers match the runtime. Keep the `phpize` and `config.m4` build; do not replace it with CMake. If CLion needs a compilation database, run `bear -- make -B` inside the container after configuration and map `/workspace` to the host repository path.

## Native debugging with GDB

The debug Compose override adds `SYS_PTRACE` and disables seccomp restrictions for trusted local debugging:

```bash
docker compose -f compose.yaml -f compose.debug.yaml up -d --build
docker compose exec php bash
./scripts/build.sh
gdb --args php -d extension=/workspace/modules/json_mapper.so /workspace/examples/basic.php
```

At the GDB prompt:

```gdb
set breakpoint pending on
break zif_json_schema_for_object
run
bt
info locals
next
```

Extension symbols are built with debug information. PHP's own internals may need a separate debug-symbol build for useful source-level stepping. CLion remote GDB and `gdbserver` require source path mappings and are not preconfigured.

## Roadmap

1. **Schema inspection:** native property metadata, public runtime value and initialization state, inheritance, `JsonField` mapping, and collection element type declarations. Properties are keyed by PHP name and expose the mapped JSON name as `field`. The PHPT suite runs on PHP 8.3, 8.4, and 8.5; the virtual-property case runs on 8.4 and newer.
2. **Encoding:** `json_encode_object(object $object): string`, with a documented policy for uninitialized properties.
3. **Decoding:** `json_decode_object(string $json, string $className): object`, with typed assignment errors and an explicit construction policy.
4. **Advanced mapping:** ignored/type attributes, nested object traversal, collection content validation, and heterogeneous item class selection.
5. **Performance:** metadata caching after correctness is established, plus benchmarks against native JSON and established serializers.

Decoding must define class allowlisting and readonly/constructor behavior before it is used with untrusted class names or JSON.
