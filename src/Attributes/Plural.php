<?php

namespace AcidORM\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Plural
{
	public function __construct(public string $value) {}
}
