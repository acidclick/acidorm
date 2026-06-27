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
