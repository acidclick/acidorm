<?php
declare(strict_types=1);

namespace Model\Data;

use AcidORM\BaseObject;
use AcidORM\Attributes\Name;
use AcidORM\Attributes\Plural;
use AcidORM\Attributes\Label;
use AcidORM\Attributes\DontMap;
use AcidORM\Attributes\OneToMany;
use AcidORM\Attributes\ManyToMany;

#[Name('Uživatel')]
#[Plural('Uzivatele')]
class User extends BaseObject
{
	#[Label('ID')]
	public ?int $id = null;

	#[Label('Jméno')]
	public ?string $name = null;

	#[Label('Email')]
	public ?string $email = null;

	#[DontMap]
	public ?string $computed = null;

	#[OneToMany(className: 'Article', foreignKey: 'userId')]
	public ?array $articles = null;

	#[ManyToMany(className: 'Tag', table: 'user_tag', foreignKey: 'tagId', column: 'userId')]
	public ?array $tags = null;
}
