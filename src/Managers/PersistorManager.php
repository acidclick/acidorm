<?php

namespace AcidORM\Managers;

use Nette;

class PersistorManager extends BaseManager
{
	protected string $namespace = 'Model\\Persistors\\';

	public ?MapperManager $mapperManager = null;
	public ?\Dibi\Connection $db = null;
	public ?Nette\Caching\Cache $cache = null;

	public function getPersistor($name)
	{
		$className = $this->namespace . $name . 'Persistor';
		if (!isset($this->data[$className])) {
			$this->data[$className] = $persistor = new $className($this->db, $this->mapperManager);
			if ($this->cache !== null) {
				$persistor->cache = $this->cache;
			}
		}
		return $this->data[$className];
	}

	public function &__get($name)
	{
		if (preg_match('/^(.+)Persistor$/', $name, $regs)) {
			$persistor = $this->getPersistor(Nette\Utils\Strings::firstUpper($regs[1]));
			return $persistor;
		}
		throw new \Exception("Undefined property: $name");
	}
}
