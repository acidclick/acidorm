<?php

namespace AcidORM\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
readonly class ManyToMany
{
	public function __construct(
		public string $className,
		public string $table,
		public string $foreignKey,
		public string $column,
	) {}

	public function getClassName(): string { return $this->className; }
	public function getTable(): string { return $this->table; }
	public function getForeignKey(): string { return $this->foreignKey; }
	public function getColumn(): string { return $this->column; }
}
