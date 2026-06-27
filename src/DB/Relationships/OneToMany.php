<?php

namespace AcidORM\DB\Relationships;

class OneToMany
{
	use \Nette\SmartObject;

	public function __construct(
		private string $className,
		private string $foreignKey,
	) {}

	public function getClassName(): string
	{
		return $this->className;
	}

	public function getPropertyName(): string
	{
		return $this->foreignKey;
	}
}
