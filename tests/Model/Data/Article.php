<?php
declare(strict_types=1);

namespace Model\Data;

use AcidORM\BaseObject;
use AcidORM\Attributes\Label;
use AcidORM\Attributes\OneToOne;

class Article extends BaseObject
{
	public ?int $id = null;

	#[Label('Titulek')]
	public ?string $title = null;

	#[Label('Obsah')]
	public ?string $body = null;

	public ?int $userId = null;

	#[OneToOne(className: 'User', propertyName: 'userId', canBeNull: true)]
	public ?User $author = null;
}
