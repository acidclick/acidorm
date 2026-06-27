<?php

namespace AcidORM\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
readonly class Plural
{
	public function __construct(public string $value) {}
}
