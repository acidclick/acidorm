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

	public function testOneToOneCanBeNullExplicitTrue(): void
	{
		$rel = new OneToOne('User', 'userId', true);
		Assert::true($rel->canBeNull);
	}

	// --- OneToMany ---

	public function testOneToManyPublicProperties(): void
	{
		$rel = new OneToMany('Article', 'userId');
		Assert::same('Article', $rel->className);
		Assert::same('userId', $rel->foreignKey);
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

	// --- readonly (immutability) ---

	public function testOneToOneIsReadonly(): void
	{
		$rel = new OneToOne('User', 'userId');
		Assert::exception(
			fn() => ($rel->className = 'Modified'),
			\Error::class,
		);
		Assert::same('User', $rel->className);
	}

	public function testManyToManyIsReadonly(): void
	{
		$rel = new ManyToMany('Tag', 'user_tag', 'tagId', 'userId');
		Assert::exception(
			fn() => ($rel->table = 'modified'),
			\Error::class,
		);
		Assert::same('user_tag', $rel->table);
	}
}

(new RelationshipsTest())->run();
