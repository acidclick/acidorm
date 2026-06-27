<?php

namespace AcidORM\DB\Relationships;

class OneToOne
{
	use \Nette\SmartObject;

	public function __construct(
		private string $className,
		private string $propertyName,
		private bool $canBeNull = false,
	) {}

	public function getClassName(): string
	{
		return $this->className;
	}

	public function getPropertyName(): string
	{
		return $this->propertyName;
	}

	public function getCanBeNull(): bool
	{
		return $this->canBeNull;
	}
}
