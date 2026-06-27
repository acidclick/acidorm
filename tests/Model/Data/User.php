<?php
declare(strict_types=1);

namespace Model\Data;

use AcidORM\BaseObject;

/**
 * @name Uživatel
 * @plural Uzivatele
 */
class User extends BaseObject
{
	/** @label ID */
	public ?int $id = null;

	/** @label Jméno */
	public ?string $name = null;

	/** @label Email */
	public ?string $email = null;

	/** @dontMap */
	public ?string $computed = null;

	/** @oneToMany(className=Article, foreignKey=userId) */
	public ?array $articles = null;

	/** @manyToMany(className=Tag, table=user_tag, foreignKey=tagId, column=userId) */
	public ?array $tags = null;
}
