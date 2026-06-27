<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Tester\Assert;
use Tester\TestCase;
use AcidORM\Utils\AnnotationParser;
use AcidORM\Utils\AnnotationValue;

/**
 * @name TestClass
 * @description Multi word description
 */
class AnnotationParserFixture
{
	/** @dontMap */
	public $boolProp;

	/** @label Jméno */
	public $stringProp;

	/** @score 42 */
	public $numericProp;

	/** @ratio 3.14 */
	public $floatProp;

	/** @oneToOne(className=User, propertyName=userId) */
	public $complexProp;

	/**
	 * @label První
	 * @label Druhý
	 */
	public $multiProp;

	public $noAnnotationProp;
}

final class AnnotationParserTest extends TestCase
{
	private \ReflectionClass $rc;

	protected function setUp(): void
	{
		$this->rc = new \ReflectionClass(AnnotationParserFixture::class);
	}

	// --- hasAnnotation na třídě ---

	public function testClassHasExistingAnnotation(): void
	{
		Assert::true(AnnotationParser::hasAnnotation($this->rc, 'name'));
	}

	public function testClassDoesNotHaveMissingAnnotation(): void
	{
		Assert::false(AnnotationParser::hasAnnotation($this->rc, 'missing'));
	}

	// --- getAnnotation na třídě ---

	public function testClassGetSingleWordAnnotation(): void
	{
		Assert::same('TestClass', AnnotationParser::getAnnotation($this->rc, 'name'));
	}

	public function testClassGetMultiWordAnnotation(): void
	{
		Assert::same('Multi word description', AnnotationParser::getAnnotation($this->rc, 'description'));
	}

	public function testClassGetMissingAnnotationReturnsNull(): void
	{
		Assert::null(AnnotationParser::getAnnotation($this->rc, 'missing'));
	}

	// --- bool annotation (@dontMap) ---

	public function testPropertyBoolAnnotationExists(): void
	{
		$prop = $this->rc->getProperty('boolProp');
		Assert::true(AnnotationParser::hasAnnotation($prop, 'dontMap'));
	}

	public function testPropertyBoolAnnotationReturnsTrue(): void
	{
		$prop = $this->rc->getProperty('boolProp');
		Assert::true(AnnotationParser::getAnnotation($prop, 'dontMap'));
	}

	// --- string annotation (@label Jméno) ---

	public function testPropertyStringAnnotationExists(): void
	{
		$prop = $this->rc->getProperty('stringProp');
		Assert::true(AnnotationParser::hasAnnotation($prop, 'label'));
	}

	public function testPropertyStringAnnotationValue(): void
	{
		$prop = $this->rc->getProperty('stringProp');
		Assert::same('Jméno', AnnotationParser::getAnnotation($prop, 'label'));
	}

	// --- numeric annotation (@score 42) ---

	public function testPropertyNumericAnnotationReturnsInt(): void
	{
		$prop = $this->rc->getProperty('numericProp');
		Assert::same(42, AnnotationParser::getAnnotation($prop, 'score'));
	}

	// --- float annotation (@ratio 3.14) ---

	public function testPropertyFloatAnnotationReturnsFloat(): void
	{
		$prop = $this->rc->getProperty('floatProp');
		Assert::same(3.14, AnnotationParser::getAnnotation($prop, 'ratio'));
	}

	// --- complex annotation (@oneToOne(...)) ---

	public function testPropertyComplexAnnotationReturnsAnnotationValue(): void
	{
		$prop = $this->rc->getProperty('complexProp');
		$value = AnnotationParser::getAnnotation($prop, 'oneToOne');
		Assert::type(AnnotationValue::class, $value);
	}

	public function testPropertyComplexAnnotationArrayAccess(): void
	{
		$prop = $this->rc->getProperty('complexProp');
		$value = AnnotationParser::getAnnotation($prop, 'oneToOne');
		Assert::same('User', $value['className']);
		Assert::same('userId', $value['propertyName']);
	}

	public function testPropertyComplexAnnotationPropertyAccess(): void
	{
		$prop = $this->rc->getProperty('complexProp');
		$value = AnnotationParser::getAnnotation($prop, 'oneToOne');
		Assert::same('User', $value->className);
		Assert::same('userId', $value->propertyName);
	}

	// --- vícenásobná stejnojmenná anotace ---

	public function testMultiAnnotationHasAnnotationReturnsTrue(): void
	{
		$prop = $this->rc->getProperty('multiProp');
		Assert::true(AnnotationParser::hasAnnotation($prop, 'label'));
	}

	public function testMultiAnnotationGetReturnsLast(): void
	{
		$prop = $this->rc->getProperty('multiProp');
		Assert::same('Druhý', AnnotationParser::getAnnotation($prop, 'label'));
	}

	// --- property bez anotace ---

	public function testPropertyWithoutAnnotationHasAnnotationReturnsFalse(): void
	{
		$prop = $this->rc->getProperty('noAnnotationProp');
		Assert::false(AnnotationParser::hasAnnotation($prop, 'label'));
	}

	public function testPropertyWithoutAnnotationGetReturnsNull(): void
	{
		$prop = $this->rc->getProperty('noAnnotationProp');
		Assert::null(AnnotationParser::getAnnotation($prop, 'label'));
	}

	// --- caching (opakované volání vrací stejný výsledek) ---

	public function testCachingReturnsSameResult(): void
	{
		$prop = $this->rc->getProperty('stringProp');
		$first  = AnnotationParser::getAnnotation($prop, 'label');
		$second = AnnotationParser::getAnnotation($prop, 'label');
		Assert::same($first, $second);
	}
}

(new AnnotationParserTest())->run();
