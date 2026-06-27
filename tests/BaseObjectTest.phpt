<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Tester\Assert;
use Tester\TestCase;
use Model\Data\User;

class ObjectWithLabel extends \acidorm\BaseObject
{
	public ?string $label = 'Vlastní popis';
	public ?int $id = null;
}

final class BaseObjectTest extends TestCase
{
	// --- jsonSerialize ---

	public function testJsonSerializeReturnsArray(): void
	{
		Assert::type('array', (new User())->jsonSerialize());
	}

	public function testJsonSerializeContainsAllProperties(): void
	{
		$data = (new User())->jsonSerialize();
		Assert::true(array_key_exists('id', $data));
		Assert::true(array_key_exists('name', $data));
		Assert::true(array_key_exists('email', $data));
		Assert::true(array_key_exists('computed', $data));
		Assert::true(array_key_exists('articles', $data));
		Assert::true(array_key_exists('tags', $data));
	}

	public function testJsonSerializeNullPropertiesAreNull(): void
	{
		$data = (new User())->jsonSerialize();
		Assert::null($data['id']);
		Assert::null($data['name']);
	}

	public function testJsonSerializePopulatedValues(): void
	{
		$user = new User();
		$user->id   = 5;
		$user->name = 'John';
		$data = $user->jsonSerialize();
		Assert::same(5, $data['id']);
		Assert::same('John', $data['name']);
	}

	public function testJsonSerializeIsJsonEncodable(): void
	{
		$user = new User();
		$user->id   = 1;
		$user->name = 'Test';
		$json = json_encode($user->jsonSerialize());
		Assert::type('string', $json);
		$decoded = json_decode($json, true);
		Assert::same(1, $decoded['id']);
		Assert::same('Test', $decoded['name']);
	}

	// --- getLabel() ---

	public function testGetLabelUsesLabelPropertyWhenPresent(): void
	{
		$obj = new ObjectWithLabel();
		Assert::same('Vlastní popis', $obj->getLabel());
	}

	public function testGetLabelUsesAnnotationForNamedProperty(): void
	{
		$user = new User();
		Assert::same('Jméno', $user->getLabel('name'));
	}

	public function testGetLabelUsesAnnotationForIdProperty(): void
	{
		$user = new User();
		Assert::same('ID', $user->getLabel('id'));
	}

	public function testGetLabelReturnsFallbackForPropertyWithoutLabelAnnotation(): void
	{
		$user = new User();
		// 'computed' má @dontMap, ale nemá @label → fallback '@computed'
		Assert::same('@computed', $user->getLabel('computed'));
	}

	public function testGetLabelReturnsFallbackForNonExistentProperty(): void
	{
		$user = new User();
		Assert::same('@nonExistent', $user->getLabel('nonExistent'));
	}

	public function testGetLabelWithNullNameReturnsFallback(): void
	{
		$user = new User();
		Assert::same('@', $user->getLabel());
	}
}

(new BaseObjectTest())->run();
