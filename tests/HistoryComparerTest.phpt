<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use AcidORM\Attributes\EnumAttr;
use AcidORM\Attributes\Label;
use AcidORM\BaseObject;
use AcidORM\Utils\HistoryComparer;
use Tester\Assert;

class HistoryTestStatus
{
	/** Stejně jako enumy v aplikacích vrací pro neznámou / prázdnou hodnotu null. */
	public static function getName($value): ?string
	{
		return [1 => 'Nový', 2 => 'Hotový'][$value] ?? null;
	}
}

class HistoryTestEntity extends BaseObject
{
	public ?int $id = null;

	#[Label('Status')]
	#[EnumAttr(className: 'HistoryTestStatus')]
	public ?int $status = null;

	#[Label('Název')]
	public ?string $name = null;
}

$old = new HistoryTestEntity;
$new = new HistoryTestEntity;
$new->name = 'Test';

// prázdná hodnota enumu nesmí shodit porovnání (getName vrací null)
Assert::same('<strong>Název</strong>: Test', HistoryComparer::getChanges($old, $new));

$new->status = 2;
Assert::same('<strong>Status</strong>: Hotový<br /><strong>Název</strong>: Test', HistoryComparer::getChanges($old, $new));
Assert::same('', HistoryComparer::getValue(null, new ReflectionProperty(HistoryTestEntity::class, 'status')));
