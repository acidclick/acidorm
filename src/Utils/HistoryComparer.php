<?php

namespace AcidORM\Utils;

use AcidORM\Interfaces\IHistoryProxy;
use AcidORM\Attributes;

class HistoryComparer
{
	use \Nette\SmartObject;

	public static function hasChanges($objectOld, $objectNew): bool
	{
		$reflection = new \ReflectionClass($objectNew);
		foreach ($reflection->getProperties() as $property) {
			if (!AttributeReader::has($property, Attributes\Label::class)) continue;
			if (is_array($objectNew->{$property->name})) {
				foreach ($objectNew->{$property->name} as $index => $tmp) {
					$old = isset($objectOld->{$property->name}[$index]) ? self::getValue($objectOld->{$property->name}[$index], $property) : '';
					$new = self::getValue($tmp, $property);
					if ($old !== $new) return true;
				}
			} else {
				if (self::getValue($objectOld->{$property->name}, $property) !== self::getValue($objectNew->{$property->name}, $property)) {
					return true;
				}
			}
		}
		return false;
	}

	public static function getChanges($objectOld, $objectNew): string
	{
		$changes = '';
		$reflection = new \ReflectionClass($objectNew);
		foreach ($reflection->getProperties() as $property) {
			$labelAttr = AttributeReader::get($property, Attributes\Label::class);
			if ($labelAttr === null) continue;
			if (is_array($objectNew->{$property->name})) {
				foreach ($objectNew->{$property->name} as $index => $tmp) {
					$old = isset($objectOld->{$property->name}[$index]) ? self::getValue($objectOld->{$property->name}[$index], $property) : '';
					$new = self::getValue($tmp, $property);
					if ($old !== $new) {
						$changes .= ($changes === '' ? '' : '<br />') . sprintf('<strong>%s</strong>: %s', $labelAttr->value, $new);
					}
				}
			} else {
				$new = self::getValue($objectNew->{$property->name}, $property);
				$old = self::getValue($objectOld->{$property->name}, $property);
				if ($old !== $new) {
					$changes .= ($changes === '' ? '' : '<br />') . sprintf('<strong>%s</strong>: %s', $labelAttr->value, $new);
				}
			}
		}
		return $changes;
	}

	public static function getValue(mixed $value, \ReflectionProperty $property): string
	{
		if (is_object($value) && method_exists($value, '__toString') && !($value instanceof \DateTimeInterface)) {
			return (string) $value;
		}
		if ($value instanceof \DateTimeInterface) {
			return $value->format('Y-m-d H:i:s');
		}
		if (is_bool($value)) {
			return $value ? 'Ano' : 'Ne';
		}
		$enumAttr = AttributeReader::get($property, Attributes\EnumAttr::class);
		if ($enumAttr !== null) {
			return call_user_func($enumAttr->className . '::getName', $value);
		}
		$formatterAttr = AttributeReader::get($property, Attributes\Formatter::class);
		if ($formatterAttr !== null) {
			return call_user_func($formatterAttr->className . '::format', $value, $property);
		}
		return (string) $value;
	}
}
