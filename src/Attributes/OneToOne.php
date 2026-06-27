<?php

namespace AcidORM\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
readonly class OneToOne
{
	public function __construct(
		public string $className,
		public string $propertyName,
		public bool $canBeNull = false,
	) {}

}
