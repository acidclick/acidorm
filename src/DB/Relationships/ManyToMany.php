<?php

namespace AcidORM\DB\Relationships;

class ManyToMany
{
	use \Nette\SmartObject;

	public function __construct(
		private string $className,
		private string $table,
		private string $foreignKey,
		private string $column,
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
