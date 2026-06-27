<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Tester\Assert;
use Tester\TestCase;
use Model\Data\User;
use Model\Data\Article;
use Model\Data\Tag;
use Model\Mappers\UserMapper;
use Model\Mappers\ArticleMapper;
use AcidORM\DB\Relationships\OneToMany;
use AcidORM\DB\Relationships\ManyToMany;
use AcidORM\DB\Relationships\OneToOne;

final class BaseMapperTest extends TestCase
{
	private UserMapper $mapper;
	private ArticleMapper $articleMapper;

	protected function setUp(): void
	{
		$this->mapper        = new UserMapper();
		$this->articleMapper = new ArticleMapper();
	}

	// --- getTable ---

	public function testGetTableReturnsEntityName(): void
	{
		Assert::same('User', $this->mapper->getTable());
	}

	public function testGetTableForArticleMapper(): void
	{
		Assert::same('Article', $this->articleMapper->getTable());
	}

	// --- create ---

	public function testCreateReturnsUserInstance(): void
	{
		Assert::type(User::class, $this->mapper->create());
	}

	public function testCreateReturnsClone(): void
	{
		$a = $this->mapper->create();
		$b = $this->mapper->create();
		Assert::notSame($a, $b);
	}

	// --- map ---

	public function testMapEmptyArrayReturnsNull(): void
	{
		Assert::null($this->mapper->map([]));
	}

	public function testMapNullReturnsNull(): void
	{
		Assert::null($this->mapper->map(null));
	}

	public function testMapPopulatesProperties(): void
	{
		$user = $this->mapper->map(['id' => 3, 'name' => 'Alice', 'email' => 'alice@test.com']);
		Assert::type(User::class, $user);
		Assert::same(3, $user->id);
		Assert::same('Alice', $user->name);
		Assert::same('alice@test.com', $user->email);
	}

	public function testMapIgnoresUnknownKeys(): void
	{
		$user = $this->mapper->map(['id' => 1, 'unknownColumn' => 'x']);
		Assert::same(1, $user->id);
	}

	public function testMapDoesNotModifyInternalObject(): void
	{
		$this->mapper->map(['id' => 99, 'name' => 'Bob']);
		$fresh = $this->mapper->create();
		Assert::null($fresh->id);
	}

	// --- toArray ---

	public function testToArrayExcludesDontMapProperty(): void
	{
		$user           = new User();
		$user->id       = 1;
		$user->computed = 'skip me';
		$data = $this->mapper->toArray($user);
		Assert::false(array_key_exists('computed', $data));
	}

	public function testToArrayExcludesOneToManyProperty(): void
	{
		$user           = new User();
		$user->id       = 1;
		$user->articles = [];
		$data = $this->mapper->toArray($user);
		Assert::false(array_key_exists('articles', $data));
	}

	public function testToArrayExcludesManyToManyProperty(): void
	{
		$user       = new User();
		$user->id   = 1;
		$user->tags = [];
		$data = $this->mapper->toArray($user);
		Assert::false(array_key_exists('tags', $data));
	}

	public function testToArrayIncludesNonNullRegularFields(): void
	{
		$user        = new User();
		$user->id    = 2;
		$user->name  = 'Bob';
		$user->email = 'bob@test.com';
		$data = $this->mapper->toArray($user);
		Assert::same(2, $data['id']);
		Assert::same('Bob', $data['name']);
		Assert::same('bob@test.com', $data['email']);
	}

	public function testToArrayOmitsNullEmailWithoutIdPattern(): void
	{
		$user = new User();
		$data = $this->mapper->toArray($user);
		Assert::false(array_key_exists('email', $data));
	}

	public function testToArrayIncludesNullUserIdDueToIdPattern(): void
	{
		$article = new Article();
		$data    = $this->articleMapper->toArray($article);
		Assert::true(array_key_exists('userId', $data));
		Assert::null($data['userId']);
	}

	// --- getColumns ---

	public function testGetColumnsReturnsAliasedColumns(): void
	{
		$cols = $this->mapper->getColumns('u');
		Assert::true(array_key_exists('u.id', $cols));
		Assert::same('u_id', $cols['u.id']);
		Assert::same('u_name', $cols['u.name']);
		Assert::same('u_email', $cols['u.email']);
	}

	public function testGetColumnsExcludesDontMapProperty(): void
	{
		$cols = $this->mapper->getColumns('u');
		Assert::false(array_key_exists('u.computed', $cols));
	}

	public function testGetColumnsExcludesOneToManyProperty(): void
	{
		$cols = $this->mapper->getColumns('u');
		Assert::false(array_key_exists('u.articles', $cols));
	}

	public function testGetColumnsExcludesManyToManyProperty(): void
	{
		$cols = $this->mapper->getColumns('u');
		Assert::false(array_key_exists('u.tags', $cols));
	}

	public function testGetColumnsUsesProvidedAlias(): void
	{
		$cols = $this->mapper->getColumns('object');
		Assert::true(array_key_exists('object.id', $cols));
		Assert::same('object_id', $cols['object.id']);
	}

	// --- getOneToOneRelationships ---

	public function testUserHasNoOneToOneRelationships(): void
	{
		Assert::equal([], $this->mapper->getOneToOneRelationships());
	}

	public function testArticleHasOneToOneRelationship(): void
	{
		$rels = $this->articleMapper->getOneToOneRelationships();
		Assert::true(array_key_exists('author', $rels));
		Assert::type(OneToOne::class, $rels['author']);
		Assert::same('User', $rels['author']->getClassName());
		Assert::same('userId', $rels['author']->getPropertyName());
		Assert::true($rels['author']->getCanBeNull());
	}

	// --- getOneToManyRelationships ---

	public function testUserHasOneToManyArticlesRelationship(): void
	{
		$rels = $this->mapper->getOneToManyRelationships();
		Assert::true(array_key_exists('articles', $rels));
		Assert::type(OneToMany::class, $rels['articles']);
		Assert::same('Article', $rels['articles']->getClassName());
		Assert::same('userId', $rels['articles']->getPropertyName());
	}

	public function testArticleHasNoOneToManyRelationships(): void
	{
		Assert::equal([], $this->articleMapper->getOneToManyRelationships());
	}

	// --- getManyToManyRelationships ---

	public function testUserHasManyToManyTagsRelationship(): void
	{
		$rels = $this->mapper->getManyToManyRelationships();
		Assert::true(array_key_exists('tags', $rels));
		Assert::type(ManyToMany::class, $rels['tags']);
		Assert::same('Tag', $rels['tags']->getClassName());
		Assert::same('user_tag', $rels['tags']->getTable());
		Assert::same('tagId', $rels['tags']->getForeignKey());
		Assert::same('userId', $rels['tags']->getColumn());
	}

	public function testArticleHasNoManyToManyRelationships(): void
	{
		Assert::equal([], $this->articleMapper->getManyToManyRelationships());
	}

	// --- lazy loading (relationships se načítají jen jednou) ---

	public function testRelationshipsAreCached(): void
	{
		$first  = $this->mapper->getOneToManyRelationships();
		$second = $this->mapper->getOneToManyRelationships();
		Assert::same($first, $second);
	}
}

(new BaseMapperTest())->run();
