<?php

namespace acidorm\Utils;

class AnnotationValue implements \ArrayAccess
{
	/** @var array */
	private $data;

	public function __construct(array $data)
	{
		$this->data = $data;
	}

	public function offsetExists($offset): bool
	{
		return isset($this->data[$offset]);
	}

	/**
	 * @return mixed
	 */
	public function offsetGet($offset)
	{
		return $this->data[$offset] ?? null;
	}

	public function offsetSet($offset, $value): void {}

	public function offsetUnset($offset): void {}

	/**
	 * @return mixed
	 */
	public function __get(string $name)
	{
		return $this->data[$name] ?? null;
	}

	public function __isset(string $name): bool
	{
		return isset($this->data[$name]);
	}
}
