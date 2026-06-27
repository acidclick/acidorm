<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Tester\Assert;
use Tester\TestCase;
use AcidORM\Utils\AttributeReader;
use AcidORM\Attributes\Name;
use AcidORM\Attributes\DontMap;
use AcidORM\Attributes\Label;
use AcidORM\Attributes\OneToOne;

#[Name('TestClass')]
class AttributeReaderFixture
{
	#[DontMap]
	public mixed $dontMapProp = null;

	#[Label('Jméno')]
	public mixed $labelProp = null;

	#[OneToOne(className: 'User', propertyName: 'userId')]
	public mixed $complexProp = null;

	public mixed $noProp = null;
}

final class AttributeReaderTest extends TestCase
{
	private \ReflectionClass $rc;

	protected function setUp(): void
	{
		$this->rc = new \ReflectionClass(AttributeReaderFixture::class);
	}

	// --- has() on class ---

	public function testClassHasExistingAttribute(): void
	{
		Assert::true(AttributeReader::has($this->rc, Name::class));
	}

	public function testClassDoesNotHaveMissingAttribute(): void
	{
		Assert::false(AttributeReader::has($this->rc, DontMap::class));
	}

	// --- get() on class ---

	public function testClassGetNameAttributeValue(): void
	{
		$attr = AttributeReader::get($this->rc, Name::class);
		Assert::type(Name::class, $attr);
		Assert::same('TestClass', $attr->value);
	}

	public function testClassGetMissingAttributeReturnsNull(): void
	{
		Assert::null(AttributeReader::get($this->rc, DontMap::class));
	}

	// --- marker attribute (DontMap) ---

	public function testPropertyHasDontMapAttribute(): void
	{
		Assert::true(AttributeReader::has($this->rc->getProperty('dontMapProp'), DontMap::class));
	}

	public function testPropertyDontMapGetReturnsInstance(): void
	{
		Assert::type(DontMap::class, AttributeReader::get($this->rc->getProperty('dontMapProp'), DontMap::class));
	}

	// --- Label ---

	public function testPropertyHasLabelAttribute(): void
	{
		Assert::true(AttributeReader::has($this->rc->getProperty('labelProp'), Label::class));
	}

	public function testPropertyLabelValue(): void
	{
		Assert::same('Jméno', AttributeReader::get($this->rc->getProperty('labelProp'), Label::class)->value);
	}

	// --- complex attribute (OneToOne) ---

	public function testPropertyComplexAttributeReturnsInstance(): void
	{
		Assert::type(OneToOne::class, AttributeReader::get($this->rc->getProperty('complexProp'), OneToOne::class));
	}

	public function testPropertyComplexAttributeFields(): void
	{
		$attr = AttributeReader::get($this->rc->getProperty('complexProp'), OneToOne::class);
		Assert::same('User', $attr->className);
		Assert::same('userId', $attr->propertyName);
		Assert::false($attr->canBeNull);
	}

	// --- no attribute ---

	public function testPropertyWithoutAttributeHasReturnsFalse(): void
	{
		Assert::false(AttributeReader::has($this->rc->getProperty('noProp'), Label::class));
	}

	public function testPropertyWithoutAttributeGetReturnsNull(): void
	{
		Assert::null(AttributeReader::get($this->rc->getProperty('noProp'), Label::class));
	}

	// --- repeated call is stable ---

	public function testRepeatedGetReturnsSameValue(): void
	{
		$prop = $this->rc->getProperty('labelProp');
		Assert::same(
			AttributeReader::get($prop, Label::class)->value,
			AttributeReader::get($prop, Label::class)->value,
		);
	}
}

(new AttributeReaderTest())->run();
