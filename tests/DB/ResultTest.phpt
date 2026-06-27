<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use Tester\Assert;
use Tester\TestCase;
use acidorm\DB\Result;

final class ResultTest extends TestCase
{
	// --- isInitialized ---

	public function testNullIsNotInitialized(): void
	{
		Assert::false((new Result(null))->isInitialized());
	}

	public function testFalseIsNotInitialized(): void
	{
		Assert::false((new Result(false))->isInitialized());
	}

	public function testEmptyArrayIsInitialized(): void
	{
		Assert::true((new Result([]))->isInitialized());
	}

	public function testNonEmptyArrayIsInitialized(): void
	{
		Assert::true((new Result(['object_id' => 1]))->isInitialized());
	}

	// --- getAliasData: neinicializovaný výsledek ---

	public function testGetAliasDataReturnsFalseWhenNotInitialized(): void
	{
		Assert::false((new Result(false))->getAliasData('object'));
	}

	// --- getAliasData: správné matchování ---

	public function testGetAliasDataExtractsMatchingColumns(): void
	{
		$res  = new Result(['object_id' => 1, 'object_name' => 'John', 'other_id' => 99]);
		$data = $res->getAliasData('object');
		Assert::equal(['id' => 1, 'name' => 'John'], $data);
	}

	public function testGetAliasDataExcludesNullValues(): void
	{
		$res  = new Result(['object_id' => 1, 'object_email' => null]);
		$data = $res->getAliasData('object');
		Assert::equal(['id' => 1], $data);
	}

	public function testGetAliasDataReturnsNullWhenNoMatch(): void
	{
		$res = new Result(['object_id' => 1]);
		Assert::null($res->getAliasData('person'));
	}

	public function testGetAliasDataReturnsNullWhenAllMatchingColumnsAreNull(): void
	{
		$res = new Result(['object_id' => null, 'object_name' => null]);
		Assert::null($res->getAliasData('object'));
	}

	public function testGetAliasDataDoesNotMatchPartialPrefix(): void
	{
		$res  = new Result(['objectfoo_id' => 5, 'object_id' => 1]);
		$data = $res->getAliasData('object');
		Assert::equal(['id' => 1], $data);
	}

	// --- getAliasData: různé aliasy ---

	public function testGetAliasDataWithDifferentAlias(): void
	{
		$res  = new Result(['author_id' => 7, 'author_name' => 'Jane', 'object_id' => 1]);
		$data = $res->getAliasData('author');
		Assert::equal(['id' => 7, 'name' => 'Jane'], $data);
	}
}

(new ResultTest())->run();
