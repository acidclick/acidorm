# AcidORM

A lightweight PHP ORM built on top of [dibi](https://dibiphp.com/) and [Nette](https://nette.org/), using **PHP 8 native attributes** to define entity mappings and relationships.

**Requires PHP 8.4+**

> **Migrating from v1.0?** See [Migration from docblock annotations](#migration-from-docblock-annotations) below.

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
use AcidORM\Attributes\Name;
use AcidORM\Attributes\Plural;
use AcidORM\Attributes\Label;
use AcidORM\Attributes\DontMap;
use AcidORM\Attributes\OneToOne;
use AcidORM\Attributes\OneToMany;
use AcidORM\Attributes\ManyToMany;

#[Name('Article')]
#[Plural('Articles')]
class Article extends BaseObject
{
    public ?int    $id        = null;

    #[Label('Title')]
    public ?string $title     = null;

    #[Label('Body')]
    public ?string $body      = null;

    public ?int    $authorId  = null;

    #[Label('Published')]
    public ?string $published = null;

    #[DontMap]
    public ?string $computed  = null;

    #[OneToOne(className: 'User', propertyName: 'authorId', canBeNull: true)]
    public ?User $author = null;

    #[OneToMany(className: 'Comment', foreignKey: 'articleId')]
    public ?array $comments = null;

    #[ManyToMany(className: 'Tag', table: 'article_tag', foreignKey: 'tagId', column: 'articleId')]
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

// With total count (for building a paginator)
$total = 0;
$users = $facade->getUsers(10, 0, 'name', 0, function (int $count) use (&$total) {
    $total = $count;
});
```

**Filter + pagination:**

```php
// Signature: getUsersByStatus($status, $limit, $offset, $orderBy, $direction, $countCallback)

$total = 0;
$users = $facade->getUsersByStatus(
    'active',
    10,
    0,
    'name',
    0,
    function (int $count) use (&$total) { $total = $count; }
);
```

**Compound filter + pagination:**

```php
$users = $facade->getUsersByRoleAndStatus('admin', 'active', 25, 0, 'email', 0);
```

## Relationships

### One-to-One

The foreign key lives on the owning entity. Set `canBeNull: true` to produce a `LEFT JOIN` instead of `INNER JOIN`.

```php
#[OneToOne(className: 'User', propertyName: 'authorId', canBeNull: true)]
public ?User $author = null;
```

### One-to-Many

```php
#[OneToMany(className: 'Comment', foreignKey: 'articleId')]
public ?array $comments = null;
```

### Many-to-Many

```php
#[ManyToMany(className: 'Tag', table: 'article_tag', foreignKey: 'tagId', column: 'articleId')]
public ?array $tags = null;
```

## Optimizing Dependency Loading

By default every persistor query loads **only the entity itself** — no JOINs, no extra queries. Dependencies are opt-in:

| Layer | Relationship type | Mechanism |
|---|---|---|
| `getDependencies` | `OneToOne` | SQL JOIN added to the main query |
| `mapDependencies` | `OneToMany`, `ManyToMany` | Separate query per relationship after the main fetch |

### getDependencies — controlling JOINs (OneToOne)

```php
$persistor = $engine->getPersistor('Article');

// No JOINs — fastest
$article = $persistor->getById(1);

// All OneToOne JOINs
$article = $persistor->getById(1, true);

// Only the 'author' JOIN
$deps    = $persistor->getDependencies(['author']);
$article = $persistor->getById(1, true, $deps);
```

### mapDependencies — controlling collections (OneToMany / ManyToMany)

```php
$facade  = $engine->getFacade('Article');
$article = $facade->getPersistor()->getById(1);

// Load all collections
$facade->mapDependencies($article);

// Load only comments, skip tags
$facade->mapDependencies($article, false, ['comments']);
```

### Combining both layers

```php
$deps    = $persistor->getDependencies(['author']);
$article = $persistor->getById(1, true, $deps);

$facade->mapDependencies($article, false, ['comments']);
```

## HistoryComparer

`AcidORM\Utils\HistoryComparer` compares two versions of an entity and produces a human-readable change summary. Only properties with `#[Label]` are compared.

### hasChanges

```php
$old = $persistor->getById(5);
$new = clone $old;
$new->name = 'Updated name';

if (HistoryComparer::hasChanges($old, $new)) {
    // something changed
}
```

### getChanges

```php
$html = HistoryComparer::getChanges($old, $new);
// → "<strong>Name</strong>: Updated name<br />"
```

### getValue

Converts a single property value to a human-readable string:

| Condition | Result |
|---|---|
| `DateTimeInterface` | `Y-m-d H:i:s` |
| Object with `__toString()` | `(string) $value` |
| `bool` | `'Ano'` / `'Ne'` |
| Property has `#[EnumAttr]` | `{ClassName}::getName($value)` |
| Property has `#[Formatter]` | `{ClassName}::format($value, $property)` |
| Anything else | `(string) $value` |

**EnumAttr:**

```php
use AcidORM\Attributes\EnumAttr;

#[Label('Status')]
#[EnumAttr(className: StatusEnum::class)]
public ?int $status = null;

// StatusEnum must implement static getName($value): string
class StatusEnum
{
    public static function getName(int $value): string
    {
        return match ($value) {
            1 => 'Active',
            0 => 'Inactive',
            default => (string) $value,
        };
    }
}
```

**Formatter:**

```php
use AcidORM\Attributes\Formatter;

#[Label('Price')]
#[Formatter(className: PriceFormatter::class)]
public ?float $price = null;

// Formatter must implement static format($value, ReflectionProperty $property): string
class PriceFormatter
{
    public static function format($value, \ReflectionProperty $property): string
    {
        return number_format((float) $value, 2, ',', ' ') . ' Kč';
    }
}
```

**HistoryDontMap** — excludes a property from history even when it has `#[Label]`:

```php
use AcidORM\Attributes\HistoryDontMap;

#[Label('Internal note')]
#[HistoryDontMap]
public ?string $internalNote = null;
```

## Attributes Reference

### Property attributes

| Attribute | Description |
|---|---|
| `#[Label('text')]` | Human-readable label, returned by `getLabel()` and used in history |
| `#[DontMap]` | Excluded from `toArray()` and DB column list |
| `#[OneToOne(className, propertyName, canBeNull)]` | Eager-loaded JOIN relationship |
| `#[OneToMany(className, foreignKey)]` | Lazy-loaded collection resolved via facade |
| `#[ManyToMany(className, table, foreignKey, column)]` | Lazy-loaded collection via pivot table |
| `#[EnumAttr(className)]` | Enum class for `HistoryComparer::getValue()` |
| `#[Formatter(className)]` | Formatter class for `HistoryComparer::getValue()` |
| `#[HistoryDontMap]` | Excludes property from history comparison |

### Class attributes

| Attribute | Description |
|---|---|
| `#[Name('text')]` | Display name used in history / audit trails |
| `#[Plural('text')]` | Plural form used by facade dynamic methods |
| `#[HistoryBinding(key: 'key')]` | History log binding key |

## PHP 8.4 features

v3.0 targets PHP 8.4 and takes advantage of the following features:

**Readonly attribute classes** — all `AcidORM\Attributes\*` classes are declared `readonly class`. Attribute instances returned by `AttributeReader` are immutable; any attempt to assign to their properties throws an `Error`:

```php
$rel = new OneToOne(className: 'User', propertyName: 'userId');
$rel->className = 'Other'; // Error: Cannot modify readonly property
```

**Deprecated alias** — `AcidORM\AcidORM` triggers `E_USER_DEPRECATED` in its constructor. Use `AcidORM\Engine` instead:

```php
// deprecated — triggers E_USER_DEPRECATED at runtime
$engine = new AcidORM\AcidORM();

// correct
$engine = new AcidORM\Engine();
```

**New-without-parentheses chaining** — internal code uses PHP 8.4's `new Foo->method()` syntax to chain calls on freshly created objects without extra parentheses.

## Migration from docblock annotations

v3.0 replaces PHPDoc annotations with PHP 8 native attributes. A migration script is included to convert existing entity files automatically.

```bash
# Preview changes without modifying files
php migrate-to-attributes.php /path/to/model --dry-run --verbose

# Apply changes in place
php migrate-to-attributes.php /path/to/model
```

The script handles all built-in annotations and automatically adds the required `use AcidORM\Attributes\*;` imports.

**Before (v1.0):**

```php
/**
 * @name Article
 * @plural Articles
 */
class Article extends BaseObject
{
    /** @label Title */
    public ?string $title = null;

    /** @oneToMany(className=Comment, foreignKey=articleId) */
    public ?array $comments = null;
}
```

**After (v3.0):**

```php
#[Name('Article')]
#[Plural('Articles')]
class Article extends BaseObject
{
    #[Label('Title')]
    public ?string $title = null;

    #[OneToMany(className: 'Comment', foreignKey: 'articleId')]
    public ?array $comments = null;
}
```

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

| Branch | PHP  | Annotations | Notes |
|--------|------|-------------|-------|
| v1.0   | 7.4+ | PHPDoc (`/** @label ... */`) | |
| v2.0   | 8.0+ | PHP attributes (`#[Label(...)]`) | |
| v3.0   | 8.4+ | PHP attributes (`#[Label(...)]`) | `readonly class` on attribute objects |

## License

BSD-3-Clause / GPL-2.0 / GPL-3.0
