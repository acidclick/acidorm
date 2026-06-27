<?php

namespace AcidORM\Managers;

use Nette;

class FacadeManager extends BaseManager
{
	protected string $namespace = 'Model\\Facades\\';

	public ?PersistorManager $persistorManager = null;
	public ?MapperManager $mapperManager = null;
	public ?Nette\Caching\Cache $cache = null;
	public ?array $parameters = null;

	public function getFacade($name)
	{
		$className = $this->namespace . $name . 'Facade';
		if (!isset($this->data[$className])) {
			$facade = $this->data[$className] = new $className;
			$facade->persistorManager = $this->persistorManager;
			$facade->mapperManager = $this->mapperManager;
			$facade->cache = $this->cache;
			$facade->facadeManager = $this;
			$facade->parameters = $this->parameters;
			$facade->startup();
		}
		return $this->data[$className];
	}

	public function getDb(): ?\Dibi\Connection
	{
		return $this->persistorManager?->db;
	}

	public function &__get($name)
	{
		if (preg_match('/^(.+)Facade$/', $name, $regs)) {
			$facade = $this->getFacade(Nette\Utils\Strings::firstUpper($regs[1]));
			return $facade;
		}
		throw new \Exception("Undefined property: $name");
	}
}
