<?php

namespace AcidORM;

use Nette;
use AcidORM\Utils\AttributeReader;
use AcidORM\Attributes;

class BasePersistor
{
	use \Nette\SmartObject;

	private ?\Dibi\Connection $db = null;
	private ?BaseObject $object = null;
	private ?BaseMapper $mapper = null;
	private ?string $table = null;
	private ?Managers\MapperManager $mapperManager = null;
	private ?Nette\Caching\Cache $cache = null;

	public function __construct($db, $mapperManager)
	{
		$this->db = $db;
		$this->mapperManager = $mapperManager;
		$reflection = new \ReflectionClass($this);
		if (preg_match('/\\\([a-zA-Z0-9]+)Persistor$/', $reflection->getName(), $regs)) {
			$class = "Model\\Data\\{$regs[1]}";
			$this->object = new $class;
			$this->table = $regs[1];
			$this->mapper = $this->mapperManager->getMapper($regs[1]);
		}
	}

	public function getDb() { return $this->db; }
	public function setDb($db): void { $this->db = $db; }
	public function getMapperManager() { return $this->mapperManager; }
	public function getMapper() { return $this->mapper; }
	public function getObject() { return $this->object; }
	public function getTable(): ?string { return $this->table; }

	public function insertUpdate(BaseObject $baseObject): void
	{
		$array = $this->mapper->toArray($baseObject);

		if ($this->db->getConfig('driver') === 'mysqli' || $this->db->getConfig('driver') === 'mysql') {
			$this->db->query("insert ignore into [{$this->mapper->getTable()}] ", $array, ' on duplicate key update %a', $array);
		} else {
			if ($baseObject->id === null) {
				$this->db->insert($this->mapper->getTable(), $array)->execute();
				try {
					$baseObject->id = $this->db->insertId;
				} catch (\Exception | \Error $ex) {}
			} else {
				unset($array['id']);
				$this->db->update($this->mapper->getTable(), $array)->where('[id] = %i', $baseObject->id)->execute();
			}
		}

		if ($baseObject->id === null) {
			try {
				$baseObject->id = $this->db->insertId();
			} catch (\Exception | \Error $ex) {}
		}
	}

	public function delete($id): void
	{
		$this->db->delete($this->mapper->getTable())->where('id = %i', $id)->execute();
	}

	public function getById($id, $withDependencies = false, $dependencies = null): ?BaseObject
	{
		if ($withDependencies) $dependencies = $this->getDependencies($dependencies);
		$q = $this->createQuery($withDependencies, $dependencies)->where('[object].[id] = %i', $id);
		foreach ($q as $r) {
			return $this->map($r, $withDependencies, $dependencies);
		}
		return null;
	}

	public function getAll($limit = null, $offset = null, $withDependencies = false, $dependencies = null, &$count = null, $orderBy = null, $direction = 0): array
	{
		if ($withDependencies) $dependencies = $this->getDependencies($dependencies);
		$objects = [];
		$q = $this->createQuery($withDependencies, $dependencies);
		$count = $q->count();
		if ($orderBy) {
			$q = $q->orderBy($orderBy . ' ' . ($direction === 1 ? 'desc' : 'asc'));
		}
		if ($limit !== null) $q = $q->limit($limit);
		if ($offset !== null) $q = $q->offset($offset);
		foreach ($q as $r) {
			$objects[] = $this->map($r, $withDependencies, $dependencies);
		}
		return $objects;
	}

	public function getByProperty($propertyName, $propertyValue, $withDependencies = false, $dependencies = null): ?BaseObject
	{
		if ($withDependencies) $dependencies = $this->getDependencies($dependencies);
		$q = $this->createQuery($withDependencies, $dependencies)
			->where("[object].[$propertyName] " . (is_array($propertyValue) ? ' in %in ' : ' = %s '), $propertyValue)
			->limit(1);
		foreach ($q as $r) {
			return $this->map($r, $withDependencies, $dependencies);
		}
		return null;
	}

	public function getByProperties($properties, $withDependencies = false, $dependencies = null): ?BaseObject
	{
		if ($withDependencies) $dependencies = $this->getDependencies($dependencies);
		$q = $this->createQuery($withDependencies, $dependencies);
		foreach ($properties as $propertyName => $propertyValue) {
			$q = $q->where("[object].[$propertyName] " . (is_array($propertyValue) ? ' in %in ' : ' = %s '), $propertyValue);
		}
		foreach ($q->limit(1) as $r) {
			return $this->map($r, $withDependencies, $dependencies);
		}
		return null;
	}

	public function getAllByProperty($propertyName, $propertyValue, $withDependencies = false, $dependencies = null, $limit = null, $offset = null, &$count = null, $orderBy = null, $direction = 0): array
	{
		if ($withDependencies) $dependencies = $this->getDependencies($dependencies);
		$objects = [];
		$q = $this->createQuery($withDependencies, $dependencies)
			->where("[object].[$propertyName] " . (is_array($propertyValue) ? ' in %in ' : ' = %s '), $propertyValue);
		if ($orderBy) {
			$q = $q->orderBy("[$orderBy] " . ($direction ? 'desc' : 'asc'));
		}
		if ($count !== null) $count = $q->count('*');
		if ($limit !== null) $q = $q->limit($limit);
		if ($offset !== null) $q = $q->offset($offset);
		foreach ($q as $r) {
			$objects[] = $this->map($r, $withDependencies, $dependencies);
		}
		return $objects;
	}

	public function getAllByProperties($properties, $withDependencies = false, $dependencies = null, $limit = null, $offset = null, &$count = null, $orderBy = null, $direction = null): array
	{
		if ($withDependencies) $dependencies = $this->getDependencies($dependencies);
		$objects = [];
		$q = $this->createQuery($withDependencies, $dependencies);
		foreach ($properties as $propertyName => $propertyValue) {
			$q = $q->where("[object].[$propertyName] " . (is_array($propertyValue) ? ' in %in ' : ' = %s '), $propertyValue);
		}
		if ($orderBy) {
			$q = $q->orderBy($orderBy . ' ' . ($direction === 1 ? 'desc' : 'asc'));
		}
		if ($count !== null) $count = $q->count('*');
		if ($limit !== null) $q = $q->limit($limit);
		if ($offset !== null) $q = $q->offset($offset);
		foreach ($q as $r) {
			$objects[] = $this->map($r, $withDependencies, $dependencies);
		}
		return $objects;
	}

	public function getAllForOneToMany(Attributes\OneToMany $oneToMany, $value, $withDependencies = false, $dependencies = null): array
	{
		return $this->getAllByProperty($oneToMany->getPropertyName(), $value, $withDependencies, $dependencies);
	}

	public function getAllForManyToMany(Attributes\ManyToMany $manyToMany, $value, $withDependencies = false, $dependencies = null): array
	{
		if ($withDependencies) $dependencies = $this->getDependencies($dependencies);
		$objects = [];
		$q = $this->createQuery($withDependencies, $dependencies)
			->join("[{$manyToMany->table}] [rel1]")
			->on(sprintf('[object].[id] = [rel1].[%s]', $manyToMany->foreignKey))
			->where(sprintf('[rel1].[%s] = %%s', $manyToMany->column), $value);
		foreach ($q as $r) {
			$objects[] = $this->map($r, $withDependencies, $dependencies);
		}
		return $objects;
	}

	public function map($r, $withDependencies = false, $dependencies = null): BaseObject
	{
		$result = new DB\Result($r);
		$object = $this->mapper->map($result->getAliasData('object'));
		if ($withDependencies) {
			foreach ($dependencies as $property => $dependency) {
				$object->$property = $this->mapperManager->getMapper($dependency->className)->map($result->getAliasData($property));
			}
		}
		return $object;
	}

	/** @return array<string, Attributes\OneToOne> */
	public function getDependencies($dependencies = null): array
	{
		if ($dependencies === null) {
			$dependencies = array_keys($this->mapper->getOneToOneRelationships());
		}
		$data = [];
		$reflection = new \ReflectionClass($this->object);
		foreach ($dependencies as $propertyName) {
			$attr = AttributeReader::get($reflection->getProperty($propertyName), Attributes\OneToOne::class);
			if ($attr !== null) {
				$data[$propertyName] = $attr;
			}
		}
		return $data;
	}

	public function createQuery($withDependencies = false, $dependencies = null)
	{
		$columns = $this->mapper->getColumns('object');
		if ($withDependencies) {
			foreach ($dependencies as $propertyName => $dependency) {
				$columns = array_merge($columns, $this->mapperManager->getMapper($dependency->className)->getColumns($propertyName));
			}
		}

		$q = $this->db->select($columns)->from(sprintf('[%s] [%s]', $this->mapper->getTable(), 'object'));

		if ($withDependencies) {
			foreach ($dependencies as $property => $dependency) {
				$table = $this->mapperManager->getMapper($dependency->className)->getTable();
				if ($dependency->canBeNull) {
					$q = $q->leftJoin(sprintf('[%s] [%s]', $table, $property))
						->on(sprintf('[%s].[id] = [object].[%s]', $property, $dependency->propertyName));
				} else {
					$q = $q->join(sprintf('[%s] [%s]', $table, $property))
						->on(sprintf('[%s].[id] = [object].[%s]', $property, $dependency->propertyName));
				}
			}
		}

		return $q;
	}

	public function setCache(Nette\Caching\Cache $cache): void { $this->cache = $cache; }
	public function getCache(): ?Nette\Caching\Cache { return $this->cache; }

	public function getKeyValuePairs($key = 'id', $value = 'name', $restrictions = []): array
	{
		$q = $this->db->select(sprintf('[%s], [%s]', $key, $value))->from($this->mapper->getTable());
		foreach ($restrictions as $restrictionKey => $restrictionValue) {
			$q->where('%n = %s', $restrictionKey, $restrictionValue);
		}
		$q->orderBy('%n asc', $value);
		return $q->fetchPairs($key, $value);
	}

	public function getKeyHierarchy($key = 'id', $parent = 'parent'): array
	{
		$data = [];
		$this->getHierarchy($data, $key, $parent);
		return $data;
	}

	public function getHierarchy(&$data, $key, $parentKey = 'parent', $parent = 0): void
	{
		foreach ($this->getAllByProperty($parentKey, $parent) as $object) {
			$children = [];
			$this->getHierarchy($children, $key, $parentKey, $object->{$key}, $object->{$parentKey});
			$data[$object->{$key}] = $children;
		}
	}

	public function getKeyValuePairsHierarchy(&$data, $parent = 0, $lvl = 1, $parentText = ''): void
	{
		$q = $this->db->select('id, parent, name')->from($this->table)->where('parent = %i', $parent);
		if ($lvl !== false) {
			$delimiter = str_repeat('--', $lvl) . ' ';
		} else {
			$delimiter = $parentText . ($parentText !== '' ? ' > ' : '');
		}
		foreach ($q as $r) {
			$data[$r->id] = $delimiter . $r->name;
			$this->getKeyValuePairsHierarchy($data, $r->id, $lvl === false ? $lvl : $lvl + 1, $data[$r->id]);
		}
	}
}
