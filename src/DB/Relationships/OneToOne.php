<?php

namespace AcidORM\DB\Relationships;

readonly class OneToOne
{
	public function __construct(
		public string $className,
		public string $propertyName,
		public bool $canBeNull = false,
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
