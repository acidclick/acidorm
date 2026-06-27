<?php

namespace AcidORM\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
readonly class OneToMany
{
	public function __construct(
		public string $className,
		public string $foreignKey,
	) {}

}
