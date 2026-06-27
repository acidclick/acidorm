<?php

namespace AcidORM\Utils;

class AnnotationValue implements \ArrayAccess
{
	public function __construct(private array $data) {}

	public function offsetExists(mixed $offset): bool
	{
		return isset($this->data[$offset]);
	}

	public function offsetGet(mixed $offset): mixed
	{
		return $this->data[$offset] ?? null;
	}

	public function offsetSet(mixed $offset, mixed $value): void {}

	public function offsetUnset(mixed $offset): void {}

	public function __get(string $name): mixed
	{
		return $this->data[$name] ?? null;
	}

	public function __isset(string $name): bool
	{
		return isset($this->data[$name]);
	}
}
