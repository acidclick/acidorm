<?php
declare(strict_types=1);

namespace Model\Data;

use acidorm\BaseObject;

class Article extends BaseObject
{
	public ?int $id = null;

	/** @label Titulek */
	public ?string $title = null;

	/** @label Obsah */
	public ?string $body = null;

	public ?int $userId = null;

	/** @oneToOne(className=User, propertyName=userId, canBeNull=true) */
	public ?User $author = null;
}
