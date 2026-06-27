<?php

namespace AcidORM\DB\Relationships;

readonly class ManyToMany
{
	public function __construct(
		public string $className,
		public string $table,
		public string $foreignKey,
		public string $column,
	) {}

	public function getTable(): string
	{
		return $this->table;
	}

	public function getForeignKey(): string
	{
		return $this->foreignKey;
	}

	public function getColumn(): string
	{
		return $this->column;
	}

	public function getClassName(): string
	{
		return $this->className;
	}
}
