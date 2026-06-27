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

}
