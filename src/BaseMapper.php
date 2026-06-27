<?php

namespace AcidORM;

use AcidORM\Utils\AttributeReader;
use AcidORM\Attributes;

class BaseMapper
{
	use \Nette\SmartObject;

	private string $namespace = 'Model\\Data\\';
	private ?BaseObject $object = null;
	private ?string $table = null;
	private ?array $oneToOneRelations = null;
	private ?array $manyToManyRelations = null;
	private ?array $oneToManyRelations = null;

	public function __construct()
	{
		$reflection = new \ReflectionClass($this);
		if (preg_match('/([a-zA-Z0-9]+)Mapper$/', $reflection->getName(), $regs)) {
			$className = $this->namespace . $regs[1];
			$this->object = new $className();
			$this->table = $regs[1];
		}
	}

	public function toArray(BaseObject $object): array
	{
		if (method_exists($object, 'tierDown')) $object->tierDown();
		$array = [];
		foreach (new \ReflectionClass($this->object)->getProperties() as $property) {
			if (!$this->isRelationship($property) && !AttributeReader::has($property, Attributes\DontMap::class)) {
				if ($object->{$property->name} !== null || preg_match('/Date/', $property->name) || preg_match('/Time$/', $property->name) || preg_match('/Id/', $property->name)) {
					$array[$property->name] = $object->{$property->name};
				}
			}
		}
		return $array;
	}

	public function map(mixed $data): ?BaseObject
	{
		if (empty($data) || \count($data) === 0) return null;
		$object = clone $this->object;
		$reflection = new \ReflectionClass($object);
		foreach ($data as $property => $value) {
			if ($reflection->hasProperty($property)) {
				$object->{$property} = $value;
			}
		}
		if (method_exists($object, 'setUp')) $object->setUp();
		return $object;
	}

	public function create(): BaseObject
	{
		return clone $this->object;
	}

	public function getColumns(string $alias): array
	{
		$columns = [];
		foreach (new \ReflectionClass($this->object)->getProperties() as $property) {
			if (!$this->isRelationship($property) && !AttributeReader::has($property, Attributes\DontMap::class)) {
				$columns[$alias . '.' . $property->name] = $alias . '_' . $property->name;
			}
		}
		return $columns;
	}

	/** @return array<string, Attributes\OneToOne> */
	public function getOneToOneRelationships(): array
	{
		if ($this->oneToOneRelations === null) {
			$this->oneToOneRelations = [];
			foreach (new \ReflectionClass($this->object)->getProperties() as $property) {
				$attr = AttributeReader::get($property, Attributes\OneToOne::class);
				if ($attr !== null) {
					$this->oneToOneRelations[$property->name] = $attr;
				}
			}
		}
		return $this->oneToOneRelations;
	}

	/** @return array<string, Attributes\OneToMany> */
	public function getOneToManyRelationships(): array
	{
		if ($this->oneToManyRelations === null) {
			$this->oneToManyRelations = [];
			foreach (new \ReflectionClass($this->object)->getProperties() as $property) {
				$attr = AttributeReader::get($property, Attributes\OneToMany::class);
				if ($attr !== null) {
					$this->oneToManyRelations[$property->name] = $attr;
				}
			}
		}
		return $this->oneToManyRelations;
	}

	/** @return array<string, Attributes\ManyToMany> */
	public function getManyToManyRelationships(): array
	{
		if ($this->manyToManyRelations === null) {
			$this->manyToManyRelations = [];
			foreach (new \ReflectionClass($this->object)->getProperties() as $property) {
				$attr = AttributeReader::get($property, Attributes\ManyToMany::class);
				if ($attr !== null) {
					$this->manyToManyRelations[$property->name] = $attr;
				}
			}
		}
		return $this->manyToManyRelations;
	}

	public function getTable(): string
	{
		return $this->table;
	}

	private function isRelationship(\ReflectionProperty $property): bool
	{
		return AttributeReader::has($property, Attributes\OneToOne::class)
			|| AttributeReader::has($property, Attributes\OneToMany::class)
			|| AttributeReader::has($property, Attributes\ManyToMany::class);
	}
}
