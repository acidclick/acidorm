<?php

namespace AcidORM\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Name
{
	public function __construct(public string $value) {}
}
