<?php

namespace AcidORM\Attributes;

/** Specify an enum class used to resolve display names in HistoryComparer. */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
readonly class EnumAttr
{
	public function __construct(public string $className) {}
}
