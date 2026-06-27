<?php

namespace AcidORM\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
readonly class Formatter
{
	public function __construct(public string $className) {}
}
