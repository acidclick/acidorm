<?php

namespace AcidORM\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
readonly class Label
{
	public function __construct(public string $value) {}
}
