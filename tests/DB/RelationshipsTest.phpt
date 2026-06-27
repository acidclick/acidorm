<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use Tester\Assert;
use Tester\TestCase;
use AcidORM\Attributes\OneToOne;
use AcidORM\Attributes\OneToMany;
use AcidORM\Attributes\ManyToMany;

final class RelationshipsTest extends TestCase
{
	// --- OneToOne ---

	public function testOneToOnePublicProperties(): void
	{
		$rel = new OneToOne('User', 'userId');
		Assert::same('User', $rel->className);
		Assert::same('userId', $rel->propertyName);
		Assert::false($rel->canBeNull);
	}

	public function testOneToOneGetters(): void
	{
		$rel = new OneToOne('User', 'userId');
		Assert::same('User', $rel->getClassName());
		Assert::same('userId', $rel->getPropertyName());
		Assert::false($rel->getCanBeNull());
	}

	public function testOneToOneCanBeNullExplicitTrue(): void
	{
		$rel = new OneToOne('User', 'userId', true);
		Assert::true($rel->canBeNull);
		Assert::true($rel->getCanBeNull());
	}

	// --- OneToMany ---

	public function testOneToManyPublicProperties(): void
	{
		$rel = new OneToMany('Article', 'userId');
		Assert::same('Article', $rel->className);
		Assert::same('userId', $rel->foreignKey);
	}

	public function testOneToManyGetters(): void
	{
		$rel = new OneToMany('Article', 'userId');
		Assert::same('Article', $rel->getClassName());
		Assert::same('userId', $rel->getPropertyName());
	}

	// --- ManyToMany ---

	public function testManyToManyPublicProperties(): void
	{
		$rel = new ManyToMany('Tag', 'user_tag', 'tagId', 'userId');
		Assert::same('Tag', $rel->className);
		Assert::same('user_tag', $rel->table);
		Assert::same('tagId', $rel->foreignKey);
		Assert::same('userId', $rel->column);
	}

	public function testManyToManyGetters(): void
	{
		$rel = new ManyToMany('Tag', 'user_tag', 'tagId', 'userId');
		Assert::same('Tag', $rel->getClassName());
		Assert::same('user_tag', $rel->getTable());
		Assert::same('tagId', $rel->getForeignKey());
		Assert::same('userId', $rel->getColumn());
	}

}

(new RelationshipsTest())->run();
