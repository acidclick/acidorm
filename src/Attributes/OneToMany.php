<?php

namespace AcidORM\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
readonly class OneToMany
{
	public function __construct(
		public string $className,
		public string $foreignKey,
	) {}

	public function getClassName(): string { return $this->className; }
	public function getPropertyName(): string { return $this->foreignKey; }
}
