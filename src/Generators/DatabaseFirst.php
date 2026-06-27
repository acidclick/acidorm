<?php

namespace AcidORM\Generators;

use Nette;
use AcidORM\Generators\DB\BaseDatabase;

class DatabaseFirst implements IDatabaseFirst
{
	public ?\Dibi\Connection $db = null;
	public ?string $databaseDriver = null;
	public ?string $appDir = null;
	private ?BaseDatabase $adapter = null;

	public function createAdapter(): void
	{
		$class = 'AcidORM\\Generators\\DB\\' . Nette\Utils\Strings::firstUpper($this->databaseDriver);
		$this->adapter = new $class();
		$this->adapter->db = $this->db;
		$this->adapter->appDir = $this->appDir;
	}

	public function createFromTable($table): void
	{
		$this->adapter || $this->createAdapter();
		$this->adapter->createFromTable($table);
	}

	public function createAll(): void
	{
		$this->adapter || $this->createAdapter();
		$this->adapter->createAll();
	}
}
