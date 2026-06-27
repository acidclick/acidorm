<?php

namespace AcidORM;

use AcidORM\Utils\AttributeReader;
use AcidORM\Attributes\Label;

class BaseObject implements \JsonSerializable
{
	public function jsonSerialize(): mixed
	{
		$data = [];
		foreach (new \ReflectionClass($this)->getProperties() as $property) {
			$data[$property->name] = $this->{$property->name};
		}
		return $data;
	}

	public function getLabel(?string $name = null): string
	{
		if (property_exists($this, 'label')) {
			return $this->label;
		}

		if ($name !== null && property_exists($this, $name)) {
			$attr = AttributeReader::get(new \ReflectionClass($this)->getProperty($name), Label::class);
			if ($attr !== null) {
				return $attr->value;
			}
		}

		return '@' . $name;
	}
}
