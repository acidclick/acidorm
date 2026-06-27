<?php

namespace AcidORM\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Label
{
	public function __construct(public string $value) {}
}
