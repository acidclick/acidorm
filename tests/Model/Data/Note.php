<?php
declare(strict_types=1);

namespace Model\Data;

use AcidORM\BaseObject;
use AcidORM\Interfaces\IHistoryObject;
use AcidORM\Traits\HistoryObject;

class Note extends BaseObject implements IHistoryObject
{
	use HistoryObject;

	public ?int $id = null;
	public ?string $text = null;
}
