# AcidORM

A lightweight PHP ORM built on top of [dibi](https://dibiphp.com/) and [Nette](https://nette.org/), using PHPDoc annotations to define entity mappings and relationships.

**Requires PHP 7.4+**

## Installation

```bash
composer require acidclick/acidorm
```

## Quick Start

### 1. Bootstrap the Engine

```php
$engine = new AcidORM\Engine();

$engine->setDb(new \Dibi\Connection([
    'driver'   => 'mysqli',
    'host'     => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'mydb',
]));

$engine->setCacheProvider(new Nette\Caching\Cache(
    new Nette\Caching\Storages\FileStorage('/tmp/cache')
));

$engine->setParameters([
    'appDir'         => __DIR__,
    'databaseDriver' => 'mysqli',
]);

$engine->startup();
```

### 2. Define an Entity

Entities live in `model/Data/` and extend `AcidORM\BaseObject`.

```php
// model/Data/Article.php
namespace Model\Data;

use AcidORM\BaseObject;

/**
 * @name Article
 * @plural Articles
 */
class Article extends BaseObject
{
    public ?int    $id        = null;

    /** @label Title */
    public ?string $title     = null;

    /** @label Body */
    public ?string $body      = null;

    public ?int    $authorId  = null;

    /** @label Published */
    public ?string $published = null;

    /** @dontMap */
    public ?string $computed  = null;

    /** @oneToOne(className=User, propertyName=authorId, canBeNull=true) */
    public ?User $author = null;

    /** @oneToMany(className=Comment, foreignKey=articleId) */
    public ?array $comments = null;

    /** @manyToMany(className=Tag, table=article_tag, foreignKey=tagId, column=articleId) */
    public ?array $tags = null;
}
```

### 3. Create a Mapper

Mappers live in `model/Mappers/` and extend `AcidORM\BaseMapper`. The class name must follow the pattern `{Entity}Mapper`.

```php
// model/Mappers/ArticleMapper.php
namespace Model\Mappers;

use AcidORM\BaseMapper;

class ArticleMapper extends BaseMapper {}
```

### 4. Create a Persistor

Persistors live in `model/Persistors/` and extend `AcidORM\BasePersistor`. The class name must follow the pattern `{Entity}Persistor`.

```php
// model/Persistors/ArticlePersistor.php
namespace Model\Persistors;

use AcidORM\BasePersistor;

class ArticlePersistor extends BasePersistor {}
```

### 5. Create a Facade

Facades live in `model/Facades/` and extend `AcidORM\BaseFacade`. They provide a high-level API including dynamic method resolution.

```php
// model/Facades/ArticleFacade.php
namespace Model\Facades;

use AcidORM\BaseFacade;

class ArticleFacade extends BaseFacade
{
    public function getPublished(int $limit = 10): array
    {
        return $this->getPersistor()->getAllByProperty('published', '1', false, null, $limit);
    }
}
```

## CRUD Operations

All operations go through a persistor, accessible via the engine.

```php
$persistor = $engine->getPersistor('Article');

// Fetch by ID
$article = $persistor->getById(1);

// Fetch all (with optional limit and offset)
$articles = $persistor->getAll(10, 0);

// Fetch by a single property
$article = $persistor->getByProperty('title', 'Hello World');

// Insert or update (id === null → INSERT, id set → UPDATE)
$article = new \Model\Data\Article();
$article->title = 'Hello World';
$article->body  = 'My first article.';
$persistor->insertUpdate($article);
// $article->id is now set after insert

// Delete by ID
$persistor->delete($article->id);
```

## Facades and Dynamic Methods

Facades expose a dynamic call API derived from the entity name. For a `UserFacade` bound to a `User` entity:

```php
$facade = $engine->getFacade('User');

// → getById(1) + mapDependencies()
$user = $facade->getUserById(1);

// → getAllByProperty('status', 'active') + mapDependencies()
$users = $facade->getUsersByStatus('active');

// Compound property filter
$user = $facade->getUserByNameAndEmail('John', 'john@example.com');

// Insert or update
$facade->insertUpdateUser($user);

// Delete
$facade->deleteUser($user->id);
```

Facades automatically resolve one-to-many and many-to-many relationships via `mapDependencies()`.

### Pagination and Sorting

Dynamic `get*` methods accept additional trailing arguments for limit, offset, sort column, sort direction (`0` = ASC, `1` = DESC), and an optional total-count callback.

**Fetch all with pagination:**

```php
// Signature: getUsers($limit, $offset, $orderBy, $direction, $countCallback)

// First page, 10 records, sorted by name ASC
$users = $facade->getUsers(10, 0, 'name', 0);

// Second page
$users = $facade->getUsers(10, 10, 'name', 0);

// Sorted by registration date DESC
$users = $facade->getUsers(10, 0, 'createdAt', 1);

// With total count (for building a paginator)
$total = 0;
$users = $facade->getUsers(10, 0, 'name', 0, function (int $count) use (&$total) {
    $total = $count;
});
// $total now holds the total number of matching rows
```

**Filter + pagination:**

When filtering, the filter value(s) come first, followed by the same pagination arguments.

```php
// Signature: getUsersByStatus($status, $limit, $offset, $orderBy, $direction, $countCallback)

$total = 0;
$users = $facade->getUsersByStatus(
    'active',           // filter value
    10,                 // limit
    0,                  // offset
    'name',             // order by column
    0,                  // direction: 0 = ASC, 1 = DESC
    function (int $count) use (&$total) {
        $total = $count;
    }
);
```

**Compound filter + pagination:**

```php
// Signature: getUsersByRoleAndStatus($role, $status, $limit, $offset, $orderBy, $direction, $countCallback)

$users = $facade->getUsersByRoleAndStatus('admin', 'active', 25, 0, 'email', 0);
```

## Relationships

### One-to-One

The foreign key lives on the owning entity. Use `canBeNull=true` to produce a `LEFT JOIN` instead of `INNER JOIN`.

```php
/** @oneToOne(className=User, propertyName=authorId, canBeNull=true) */
public ?User $author = null;
```

### One-to-Many

```php
/** @oneToMany(className=Comment, foreignKey=articleId) */
public ?array $comments = null;
```

### Many-to-Many

```php
/** @manyToMany(className=Tag, table=article_tag, foreignKey=tagId, column=articleId) */
public ?array $tags = null;
```

## Optimizing Dependency Loading

By default every persistor query loads **only the entity itself** — no JOINs, no extra queries. Dependencies are opt-in and come in two independent layers:

| Layer | Relationship type | Mechanism |
|---|---|---|
| `getDependencies` | `@oneToOne` | SQL JOIN added to the main query |
| `mapDependencies` | `@oneToMany`, `@manyToMany` | Separate query per relationship after the main fetch |

This separation lets you choose exactly what to load per use-case.

### getDependencies — controlling JOINs (oneToOne)

Every persistor query method accepts two optional parameters: `$withDependencies` (bool) and `$dependencies` (array of property names or null).

```php
$persistor = $engine->getPersistor('Article');

// No JOINs — fastest, only the article row
$article = $persistor->getById(1);

// All @oneToOne JOINs (author, editor, …)
$article = $persistor->getById(1, true);

// Only the 'author' JOIN — skip 'editor' and any other oneToOne
$deps    = $persistor->getDependencies(['author']);
$article = $persistor->getById(1, true, $deps);
```

The same pattern works for every query method:

```php
// Selective JOIN on a list
$deps     = $persistor->getDependencies(['author']);
$articles = $persistor->getAll(10, 0, true, $deps);

$article  = $persistor->getByProperty('slug', 'hello-world', true, $deps);
```

When `$dependencies` is `null` and `$withDependencies` is `true`, all `@oneToOne` relationships are joined.

### mapDependencies — controlling collections (oneToMany / manyToMany)

`BaseFacade::mapDependencies()` fires one extra query per relationship to populate collection properties. Call it after fetching the entity.

```php
$facade  = $engine->getFacade('Article');
$article = $facade->getPersistor()->getById(1);

// Load all collections (@oneToMany comments, @manyToMany tags)
$facade->mapDependencies($article);

// Load only comments, skip tags
$facade->mapDependencies($article, false, ['comments']);

// Load only tags
$facade->mapDependencies($article, false, ['tags']);
```

The second argument (`$withDependencies`) controls whether the sub-queries themselves also JOIN their own oneToOne relationships.

### Combining both layers

```php
$persistor = $engine->getPersistor('Article');
$facade    = $engine->getFacade('Article');

// 1. Main query: JOIN only 'author', skip 'editor'
$deps    = $persistor->getDependencies(['author']);
$article = $persistor->getById(1, true, $deps);

// 2. Collections: load only 'comments', skip 'tags'
$facade->mapDependencies($article, false, ['comments']);
```

Generated SQL is then roughly:

```sql
-- Step 1: one query with a single JOIN
SELECT object.*, author.*
FROM Article AS object
LEFT JOIN User AS author ON author.id = object.authorId
WHERE object.id = 1

-- Step 2: one query per requested collection
SELECT * FROM Comment WHERE articleId = 1
```

Compare that to the default facade call `$facade->getArticleById(1)`, which would JOIN **all** oneToOne relationships and then fire **one extra query for every** `@oneToMany` and `@manyToMany` property defined on `Article`.

### Fetching a list with selective dependencies

```php
$persistor = $engine->getPersistor('Article');
$facade    = $engine->getFacade('Article');

$deps     = $persistor->getDependencies(['author']);
$articles = $persistor->getAll(20, 0, true, $deps);

foreach ($articles as $article) {
    // Populate only comments for each article
    $facade->mapDependencies($article, false, ['comments']);
}
```

## HistoryComparer

`AcidORM\Utils\HistoryComparer` is a utility class for comparing two versions of an entity and producing a human-readable change summary. It is used automatically by `BaseFacade` when the facade implements `IHistoryProxy` or the entity implements `IHistoryObject`, but it can also be called directly.

Only properties annotated with `@label` are compared — everything else is ignored.

### hasChanges

Returns `true` if at least one `@label`-annotated property differs between the two objects.

```php
$old = $persistor->getById(5);

$new = clone $old;
$new->name = 'Updated name';

if (HistoryComparer::hasChanges($old, $new)) {
    // something changed
}
```

### getChanges

Returns an HTML string listing every changed property with its label and new value.

```php
$html = HistoryComparer::getChanges($old, $new);
// Example output:
// <strong>Name</strong>: Updated name<br /><strong>Status</strong>: active
```

### getValue

`getValue($value, ReflectionProperty $property): string`

Converts a single property value to a human-readable string. `hasChanges` and `getChanges` call it internally for every compared property, but you can also use it standalone.

The conversion rules, in order of priority:

| Value type | Result |
|---|---|
| `DateTimeInterface` | `Y-m-d H:i:s` formatted string |
| Object with `__toString()` | Result of `(string) $value` |
| `bool` | `'Ano'` (true) / `'Ne'` (false) |
| Property has `@enum` annotation | `{EnumClass}::getName($value)` |
| Property has `@formatter` annotation | `{FormatterClass}::format($value, $property[, $annotation])` |
| Anything else | `(string) $value` |

**DateTime:**

```php
$property = (new ReflectionClass($article))->getProperty('publishedAt');

$value = new \DateTime('2024-06-01 12:00:00');
echo HistoryComparer::getValue($value, $property);
// → "2024-06-01 12:00:00"
```

**Bool:**

```php
echo HistoryComparer::getValue(true,  $property); // → "Ano"
echo HistoryComparer::getValue(false, $property); // → "Ne"
```

**Enum — simple string annotation:**

```php
// Entity property:
// /** @label Status @enum StatusEnum */
// public ?int $status = null;

// Enum class must implement a static getName() method:
class StatusEnum
{
    const ACTIVE   = 1;
    const INACTIVE = 0;

    public static function getName(int $value): string
    {
        return match ($value) {
            self::ACTIVE   => 'Active',
            self::INACTIVE => 'Inactive',
            default        => (string) $value,
        };
    }
}

echo HistoryComparer::getValue(1, $property);
// → "Active"
```

**Formatter — simple string annotation:**

The class must expose a static `format($value, ReflectionProperty $property): string` method.

```php
// /** @label Price @formatter PriceFormatter */
// public ?float $price = null;

class PriceFormatter
{
    public static function format($value, \ReflectionProperty $property): string
    {
        return number_format((float) $value, 2, ',', ' ') . ' Kč';
    }
}

echo HistoryComparer::getValue(1990.5, $property);
// → "1 990,50 Kč"
```

**Formatter — with annotation parameters:**

When the annotation has named parameters, the full `AnnotationValue` is passed as the third argument.

```php
// /** @label Weight @formatter(class=UnitFormatter, unit=kg, decimals=3) */
// public ?float $weight = null;

class UnitFormatter
{
    public static function format($value, \ReflectionProperty $property, $annotation): string
    {
        $decimals = (int) ($annotation['decimals'] ?? 2);
        $unit     = $annotation['unit'] ?? '';
        return number_format((float) $value, $decimals, '.', '') . ' ' . $unit;
    }
}

echo HistoryComparer::getValue(12.5, $property);
// → "12.500 kg"
```

### Annotations Reference (HistoryComparer)

| Annotation | Target | Description |
|---|---|---|
| `@label <text>` | property | Marks the property for comparison; used as the field label in change output |
| `@enum <ClassName>` | property | Class with `static getName($value): string` for human-readable enum values |
| `@formatter <ClassName>` | property | Class with `static format($value, $property): string` |
| `@formatter(class=X, ...)` | property | Formatter with extra parameters passed as `AnnotationValue` |
| `@historyDontMap` | property | Excludes the property from history even when it has `@label` |

## Annotations Reference

| Annotation | Target | Description |
|---|---|---|
| `@label <text>` | property | Human-readable label, returned by `getLabel()` |
| `@dontMap` | property | Excluded from `toArray()` and DB column list |
| `@oneToOne(...)` | property | Eager-loaded JOIN relationship |
| `@oneToMany(...)` | property | Lazy-loaded collection resolved via facade |
| `@manyToMany(...)` | property | Lazy-loaded collection via pivot table |
| `@name <text>` | class | Display name used in history / audit trails |
| `@plural <text>` | class | Plural form used by facade dynamic methods |

## Directory Structure

```
app/
└── model/
    ├── Data/           ← Entities  (extend BaseObject)
    ├── Mappers/        ← Mappers   (extend BaseMapper)
    ├── Persistors/     ← Persistors (extend BasePersistor)
    ├── Facades/        ← Facades   (extend BaseFacade)
    ├── Grids/
    ├── Forms/
    ├── Enums/
    └── Interfaces/
```

You can scaffold this structure automatically:

```php
$engine->setParameters(['appDir' => __DIR__]);
$engine->createDirStructure();
```

## Running Tests

```bash
composer install
./vendor/bin/tester tests/
```

## Compatibility

| Version | PHP  |
|---------|------|
| v1.0.x  | 7.4+ |

## License

BSD-3-Clause / GPL-2.0 / GPL-3.0
