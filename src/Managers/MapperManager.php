<?php

namespace AcidORM\Managers;

use Nette;

class MapperManager extends BaseManager
{
	protected string $namespace = 'Model\\Mappers\\';

	public function getMapper($name)
	{
		$className = $this->namespace . $name . 'Mapper';
		if (!isset($this->data[$className])) {
			$this->data[$className] = new $className();
		}
		return $this->data[$className];
	}

	public function &__get($name)
	{
		if (preg_match('/^(.+)Mapper$/', $name, $regs)) {
			$mapper = $this->getMapper(Nette\Utils\Strings::firstUpper($regs[1]));
			return $mapper;
		}
		throw new \Exception("Undefined property: $name");
	}
}
