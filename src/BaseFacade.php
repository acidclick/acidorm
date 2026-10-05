<?php

namespace AcidORM;

use Nette;
use AcidORM\Managers;
use AcidORM\Utils\AttributeReader;
use AcidORM\Attributes;

/**
 * @property-read string $name
 * @property-write Managers\PersistorManager $persistorManager
 * @property-write Managers\MapperManager $mapperManager
 * @property Nette\Caching\Cache $cache
 * @property-write Managers\FacadeManager $facadeManager
 * @property-write array $parameters
 * @property-read BasePersistor $persistor
 */
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

	public function mapDependencies(?BaseObject &$baseObject = null, $withDependencies = false, $dependencies = null): void
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
			$result = $this->simpleDelete($regs[1], $args[0], $args[1] ?? null);
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

	private function simpleDelete($class, $id, $userId = null): void
	{
		$this->isCallable($class);

		$prototype = (new \ReflectionClass("Model\\Data\\$class"))->newInstanceWithoutConstructor();
		$listeners = $this->facadeManager !== null ? $this->facadeManager->getEntityListeners($prototype) : [];
		if ($listeners === []) {
			$this->persistor->delete($id);
			return;
		}

		$userId = $this->resolveUserId($userId);
		$this->transaction(function () use ($class, $id, $userId, $listeners) {
			$oldObject = $this->simpleGetBy($class, 'Id', [$id]);
			$this->persistor->delete($id);
			if ($oldObject === null) return;
			foreach ($listeners as $listener) {
				$listener->afterDelete($oldObject, $userId);
			}
		});
	}

	/**
	 * Spustí callback v transakci. Pokud už transakce běží (otevřená mimo dibi::transaction(),
	 * např. přes begin()), nová se neotevírá – řídí ji ten, kdo ji začal.
	 */
	private function transaction(callable $callback): void
	{
		$db = $this->persistorManager->getDb();
		$driver = $db->getDriver();
		if (method_exists($driver, 'inTransaction') && $driver->inTransaction()) {
			$callback();
			return;
		}
		$db->transaction($callback);
	}

	private function resolveUserId($userId): ?int
	{
		if ((int)$userId === 0 && preg_match('/^[\d]+$/', (string) $userId) && $this->facadeManager !== null) {
			$userId = $this->facadeManager->getDefaultUserId();
		}
		return $userId === null || $userId === '' ? null : (int) $userId;
	}

	private function simpleInsertUpdate($class, $object, $userId = null): void
	{
		$this->isCallable($class);

		$new = $object->id === null;
		$listeners = $this->facadeManager !== null ? $this->facadeManager->getEntityListeners($object) : [];
		$oldObject = !$new && $listeners !== [] ? $this->simpleGetBy($class, 'Id', [$object->id]) : null;

		if (method_exists($object, 'tearDown')) $object->tearDown();

		if ($listeners === []) {
			$this->persistor->insertUpdate($object);
			return;
		}

		$userId = $this->resolveUserId($userId);
		$this->transaction(function () use ($class, $object, $oldObject, $listeners, $userId) {
			$this->persistor->insertUpdate($object);
			$newObject = $this->simpleGetBy($class, 'Id', [$object->id]);
			if ($newObject === null) return;
			foreach ($listeners as $listener) {
				$listener->afterSave($oldObject, $newObject, $userId);
			}
		});
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
