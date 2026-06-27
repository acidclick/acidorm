<?php

namespace AcidORM\Utils;

class AttributeReader
{
	public static function has(\ReflectionClass|\ReflectionProperty $reflector, string $attributeClass): bool
	{
		return !empty($reflector->getAttributes($attributeClass));
	}

	public static function get(\ReflectionClass|\ReflectionProperty $reflector, string $attributeClass): ?object
	{
		$attrs = $reflector->getAttributes($attributeClass);
		return isset($attrs[0]) ? $attrs[0]->newInstance() : null;
	}
}
