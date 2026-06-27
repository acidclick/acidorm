<?php
declare(strict_types=1);

namespace Model\Data;

use acidorm\BaseObject;

class Tag extends BaseObject
{
	public ?int $id = null;

	/** @label Název */
	public ?string $name = null;
}
