<?php

namespace AcidORM;

use Nette;
use AcidORM\Managers;
use AcidORM\Utils\AttributeReader;
use AcidORM\Attributes;
use AcidORM\Interfaces\IHistoryProxy;
use AcidORM\Interfaces\IHistoryObject;
use AcidORM\Traits\HistoryObject;

class BaseFacade
{
	use \Nette\SmartObject;

	protected ?string $name = null;
	protected ?Managers\PersistorManager $persistorManager = null;
	protected ?Managers\MapperManager $mapperManager = null;
	private ?Nette\Caching\Cache $cache = null;
	protected ?Managers\FacadeManager $facadeManager = null;
	protected ?array $parameters = null;

	public function __construct()
	{
		$reflection = new \ReflectionClass($this);
		if (preg_match('/\\\([a-zA-Z0-9]+)Facade$/', $reflection->getName(), $regs)) {
			$this->name = $regs[1];
		}
	}

	public function startup(): void {}

	public function setPersistorManager(Managers\PersistorManager $persistorManager): void
	{
		$this->persistorManager = $persistorManager;
	}

	public function setMapperManager(Managers\MapperManager $mapperManager): void
	{
		$this->mapperManager = $mapperManager;
	}

	public function mapDependencies(BaseObject &$baseObject = null, $withDependencies = false, $dependencies = null): void
	{
		if ($baseObject === null) return;

		foreach ($this->mapperManager->getMapper($this->name)->getOneToManyRelationships() as $propertyName => $oneToMany) {
			if ($dependencies === null || in_array($propertyName, $dependencies)) {
				$baseObject->{$propertyName} = $this->persistorManager
					->getPersistor($oneToMany->className)
					->getAllForOneToMany($oneToMany, $baseObject->id, true);
			}
		}

		foreach ($this->mapperManager->getMapper($this->name)->getManyToManyRelationships() as $propertyName => $manyToMany) {
			if ($dependencies === null || in_array($propertyName, $dependencies)) {
				$baseObject->{$propertyName} = $this->persistorManager
					->getPersistor($manyToMany->className)
					->getAllForManyToMany($manyToMany, $baseObject->id, true);
			}
		}

		if (method_exists($baseObject, 'setUp')) {
			$baseObject->setUp();
		}
	}

	public function getPersistor()
	{
		return $this->persistorManager->getPersistor($this->name);
	}

	public function setCache(Nette\Caching\Cache $cache): void { $this->cache = $cache; }
	public function getCache(): ?Nette\Caching\Cache { return $this->cache; }

	public function getKeyValuePairs($className = null, $key = 'id', $value = 'name'): array
	{
		if ($className === null) $className = $this->name;
		return $this->persistorManager->getPersistor($className)->getKeyValuePairs($key, $value);
	}

	public function getKeyValue($key = 'id', $value = 'name', $restrictions = []): array
	{
		return $this->persistor->getKeyValuePairs($key, $value, $restrictions);
	}

	public function setFacadeManager(Managers\FacadeManager &$facadeManager): void
	{
		$this->facadeManager = $facadeManager;
	}

	public function setParameters($parameters): void
	{
		$this->parameters = $parameters;
	}

	public function &__call($name, $args)
	{
		if (method_exists($this, $name)) {
			$result = call_user_func_array([$this, $name], $args);
			return $result;
		}

		if (preg_match('/^get([A-Z]{1}.+)By([A-Z]{1}.+)$/', $name, $regs)) {
			if ($this->isCallable($regs[1])) {
				if ($this->isPlural($regs[1])) {
					$result = $this->simpleGetAllBy($this->getSingular($regs[1]), $regs[2], $args);
					return $result;
				} else {
					$result = $this->simpleGetBy($regs[1], $regs[2], $args);
					return $result;
				}
			}
		}

		if (preg_match('/^get([A-Z]{1}.+)$/', $name, $regs)) {
			$result = $this->simpleGetAll($this->getSingular($regs[1]), $args);
			return $result;
		}

		if (preg_match('/^delete([A-Z]{1}.+)$/', $name, $regs)) {
			$result = $this->simpleDelete($regs[1], $args[0]);
			return $result;
		}

		if (preg_match('/^insertUpdate([A-Z]{1}.+)$/', $name, $regs)) {
			$result = $this->simpleInsertUpdate($regs[1], $args[0], $args[1] ?? null);
			return $result;
		}
	}

	private function isCallable($class): bool
	{
		if ($class !== $this->name && !$this->isPlural($class)) {
			throw new \Exception(sprintf('Object %s cannot be called from %sFacade.', $class, $this->name));
		}
		return true;
	}

	private function isPlural($class): bool
	{
		if ($class === $this->name) return false;
		$reflection = new \ReflectionClass("Model\\Data\\{$this->name}");
		$pluralAttr = AttributeReader::get($reflection, Attributes\Plural::class);
		return $class === $this->name . 's' || ($pluralAttr !== null && $pluralAttr->value === $class);
	}

	private function getSingular($class): string
	{
		return $this->name;
	}

	private function simpleGetBy($class, $calledProperties, $values): ?BaseObject
	{
		foreach (preg_split('/And/', $calledProperties) as $index => $property) {
			$property = Nette\Utils\Strings::lower(Nette\Utils\Strings::substring($property, 0, 1)) . Nette\Utils\Strings::substring($property, 1);
			if (!property_exists("Model\\Data\\$class", $property)) {
				throw new \Exception(sprintf('Object %s has no property called %s.', $class, $property));
			}
			if (!isset($values[$index])) {
				throw new \Exception(sprintf('Missing %s param.', $property));
			}
			$params[$property] = $values[$index];
		}
		$object = $this->persistor->getByProperties($params, true);
		if ($object !== null) $this->mapDependencies($object);
		return $object;
	}

	private function simpleGetAllBy($class, $calledProperties, $values): array
	{
		foreach (preg_split('/And/', $calledProperties) as $index => $property) {
			$property = Nette\Utils\Strings::lower(Nette\Utils\Strings::substring($property, 0, 1)) . Nette\Utils\Strings::substring($property, 1);
			if (!property_exists("Model\\Data\\$class", $property)) {
				throw new \Exception(sprintf('Object %s has no property called %s.', $class, $property));
			}
			if (!isset($values[$index])) {
				throw new \Exception(sprintf('Missing %s param.', $property));
			}
			$params[$property] = $values[$index];
		}
		$count = 0;
		$objects = $this->persistor->getAllByProperties(
			$params, true, null,
			\count($values) >= \count($params) + 1 ? $values[\count($params)] : null,
			\count($values) >= \count($params) + 2 ? $values[\count($params) + 1] : null,
			$count,
			\count($values) >= \count($params) + 3 ? $values[\count($params) + 2] : null,
			\count($values) >= \count($params) + 4 ? $values[\count($params) + 3] : null,
		);
		foreach ($objects as $object) {
			if ($object !== null) $this->mapDependencies($object);
		}
		if (\count($values) >= \count($params) + 5 && is_callable($values[\count($params) + 4])) {
			$values[\count($params) + 4]($count);
		}
		return $objects;
	}

	private function simpleGetAll($class, $values): array
	{
		$count = 0;
		$objects = $this->persistor->getAll(
			$values[0] ?? null,
			$values[1] ?? null,
			true, null, $count,
			$values[2] ?? null,
			$values[3] ?? null,
		);
		foreach ($objects as $object) {
			if ($object !== null) $this->mapDependencies($object);
		}
		if (isset($values[4]) && is_callable($values[4])) {
			$values[4]($count);
		}
		return $objects;
	}

	private function simpleDelete($class, $id): void
	{
		$this->isCallable($class);
		$this->persistor->delete($id);
	}

	private function simpleInsertUpdate($class, $object, $userId = null, $callback = null): void
	{
		$this->isCallable($class);

		if ((int)$userId === 0 && preg_match('/^[\d]+$/', $userId)) $userId = \Model\Utils\Helpers::$userId;
		$new = $object->id === null;
		if (!$new && ($this instanceof IHistoryProxy || $object instanceof IHistoryObject)) {
			$oldObject = $this->simpleGetBy($class, 'Id', [$object->id]);
		}
		if ($object instanceof IHistoryObject && empty($object->key)) {
			do {
				$unique = false;
				$object->key = HistoryObject::generateUniqueId();
				$q = $this->persistorManager->getDb()->select('id')->from('%n', HistoryObject::getTableName())->where('[key] = %s', $object->key)->limit(1);
				foreach ($q as $r) { $unique = true; }
			} while ($unique);
		}

		if (method_exists($object, 'tearDown')) $object->tearDown();
		$this->persistor->insertUpdate($object);

		if ($this instanceof IHistoryProxy) {
			if ($new) {
				$namespacedClass = "Model\\Data\\$class";
				$oldObject = new $namespacedClass();
			}
			$newObject = $this->simpleGetBy($class, 'Id', [$object->id]);
			if ($newObject === null) return;
			$reflection = new \ReflectionClass($newObject);
			foreach ($reflection->getProperties() as $property) {
				if (AttributeReader::has($property, Attributes\Label::class) && AttributeReader::has($property, Attributes\HistoryDontMap::class)) {
					$oldObject->{$property->name} = $object->{$property->name};
				}
			}
			if (Utils\HistoryComparer::hasChanges($oldObject, $newObject)) {
				$history = new \Model\Data\History;
				$historyBindingAttr = AttributeReader::get($reflection, Attributes\HistoryBinding::class);
				if ($historyBindingAttr !== null) {
					$objectKey = $historyBindingAttr->key;
					$objectId = $object->$objectKey;
				} else {
					$objectKey = Nette\Utils\Strings::lower(Nette\Utils\Strings::substring($this->name, 0, 1)) . Nette\Utils\Strings::substring($this->name, 1) . 'Id';
					$objectId = $object->id;
				}
				$history->$objectKey = $objectId;
				$history->userId = $userId;
				$history->created = date('Y-m-d H:i:s');
				$changes = Utils\HistoryComparer::getChanges($oldObject, $newObject);
				$nameAttr = AttributeReader::get($reflection, Attributes\Name::class);
				if ($historyBindingAttr !== null && $nameAttr !== null) {
					$changes = sprintf('<strong style="font-size:120%%;">%s</strong><br />%s', $nameAttr->value, $changes);
				}
				if ($callback !== null) $changes = $callback($changes);
				$history->changes = $changes;
				$this->facadeManager->historyFacade->insertUpdateHistory($history);
			}
		} elseif ($object instanceof IHistoryObject) {
			if ($new) {
				$namespacedClass = "Model\\Data\\$class";
				$oldObject = new $namespacedClass();
			}
			$newObject = $this->simpleGetBy($class, 'Id', [$object->id]);
			if ($newObject === null) return;
			$reflection = new \ReflectionClass($newObject);
			foreach ($reflection->getProperties() as $property) {
				if (AttributeReader::has($property, Attributes\Label::class) && AttributeReader::has($property, Attributes\HistoryDontMap::class)) {
					$oldObject->{$property->name} = $object->{$property->name};
				}
			}
			if (Utils\HistoryComparer::hasChanges($oldObject, $newObject)) {
				$history = new \Model\Data\History2;
				$history->key = $object->key;
				$history->class = (new \ReflectionClass($object))->getShortName();
				$history->userId = $userId;
				$history->created = date('Y-m-d H:i:s');
				$changes = Utils\HistoryComparer::getChanges($oldObject, $newObject);
				if ($callback !== null) $changes = $callback($changes);
				$history->changes = $changes;
				$this->facadeManager->history2Facade->insertUpdateHistory2($history);
			}
		}
	}

	public function cleanCacheByTag($tag): void
	{
		$this->cache->clean([Nette\Caching\Cache::TAGS => [$tag]]);
	}

	public function getKeyValuePairsHierarchy($full = false): array
	{
		$data = [];
		if ($full) {
			$this->getPersistor()->getKeyValuePairsHierarchy($data, 0, false);
		} else {
			$this->getPersistor()->getKeyValuePairsHierarchy($data);
		}
		return $data;
	}
}
