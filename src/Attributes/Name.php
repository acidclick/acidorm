<?php

namespace AcidORM\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
readonly class Name
{
	public function __construct(public string $value) {}
}
