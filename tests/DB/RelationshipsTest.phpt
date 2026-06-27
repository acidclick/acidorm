<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use Tester\Assert;
use Tester\TestCase;
use acidorm\DB\Relationships\OneToOne;
use acidorm\DB\Relationships\OneToMany;
use acidorm\DB\Relationships\ManyToMany;

final class RelationshipsTest extends TestCase
{
	// --- OneToOne ---

	public function testOneToOneGetClassName(): void
	{
		$rel = new OneToOne('User', 'userId');
		Assert::same('User', $rel->getClassName());
	}

	public function testOneToOneGetPropertyName(): void
	{
		$rel = new OneToOne('User', 'userId');
		Assert::same('userId', $rel->getPropertyName());
	}

	public function testOneToOneCanBeNullDefaultsFalse(): void
	{
		$rel = new OneToOne('User', 'userId');
		Assert::false($rel->getCanBeNull());
	}

	public function testOneToOneCanBeNullExplicitTrue(): void
	{
		$rel = new OneToOne('User', 'userId', true);
		Assert::true($rel->getCanBeNull());
	}

	public function testOneToOneCanBeNullExplicitFalse(): void
	{
		$rel = new OneToOne('User', 'userId', false);
		Assert::false($rel->getCanBeNull());
	}

	// --- OneToMany ---

	public function testOneToManyGetClassName(): void
	{
		$rel = new OneToMany('Article', 'userId');
		Assert::same('Article', $rel->getClassName());
	}

	public function testOneToManyGetPropertyNameReturnsForeignKey(): void
	{
		$rel = new OneToMany('Article', 'userId');
		Assert::same('userId', $rel->getPropertyName());
	}

	// --- ManyToMany ---

	public function testManyToManyGetClassName(): void
	{
		$rel = new ManyToMany('Tag', 'user_tag', 'tagId', 'userId');
		Assert::same('Tag', $rel->getClassName());
	}

	public function testManyToManyGetTable(): void
	{
		$rel = new ManyToMany('Tag', 'user_tag', 'tagId', 'userId');
		Assert::same('user_tag', $rel->getTable());
	}

	public function testManyToManyGetForeignKey(): void
	{
		$rel = new ManyToMany('Tag', 'user_tag', 'tagId', 'userId');
		Assert::same('tagId', $rel->getForeignKey());
	}

	public function testManyToManyGetColumn(): void
	{
		$rel = new ManyToMany('Tag', 'user_tag', 'tagId', 'userId');
		Assert::same('userId', $rel->getColumn());
	}
}

(new RelationshipsTest())->run();
