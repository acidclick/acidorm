<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Tester\Assert;
use Tester\TestCase;
use AcidORM\Utils\AnnotationValue;

final class AnnotationValueTest extends TestCase
{
	private AnnotationValue $value;

	protected function setUp(): void
	{
		$this->value = new AnnotationValue([
			'className'    => 'User',
			'propertyName' => 'userId',
			'count'        => 42,
			'flag'         => true,
		]);
	}

	// --- ArrayAccess ---

	public function testOffsetExistsReturnsTrueForExistingKey(): void
	{
		Assert::true(isset($this->value['className']));
	}

	public function testOffsetExistsReturnsFalseForMissingKey(): void
	{
		Assert::false(isset($this->value['missing']));
	}

	public function testOffsetGetReturnsStringValue(): void
	{
		Assert::same('User', $this->value['className']);
	}

	public function testOffsetGetReturnsIntValue(): void
	{
		Assert::same(42, $this->value['count']);
	}

	public function testOffsetGetReturnsBoolValue(): void
	{
		Assert::true($this->value['flag']);
	}

	public function testOffsetGetReturnsNullForMissingKey(): void
	{
		Assert::null($this->value['missing']);
	}

	public function testOffsetSetIsNoOp(): void
	{
		$this->value['className'] = 'Article';
		Assert::same('User', $this->value['className']);
	}

	public function testOffsetUnsetIsNoOp(): void
	{
		unset($this->value['className']);
		Assert::same('User', $this->value['className']);
	}

	// --- magic __get ---

	public function testMagicGetReturnsStringValue(): void
	{
		Assert::same('User', $this->value->className);
	}

	public function testMagicGetReturnsIntValue(): void
	{
		Assert::same(42, $this->value->count);
	}

	public function testMagicGetReturnsNullForMissingKey(): void
	{
		Assert::null($this->value->missing);
	}

	// --- magic __isset ---

	public function testMagicIssetReturnsTrueForExistingKey(): void
	{
		Assert::true(isset($this->value->className));
	}

	public function testMagicIssetReturnsFalseForMissingKey(): void
	{
		Assert::false(isset($this->value->missing));
	}
}

(new AnnotationValueTest())->run();
