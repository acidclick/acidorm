<?php

namespace AcidORM\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class HistoryBinding
{
	public function __construct(public string $key) {}
}
