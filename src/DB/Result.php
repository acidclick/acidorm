<?php

namespace AcidORM\DB;

class Result
{
	private array|false|null $result;

	public function __construct(array|false|null $result)
	{
		$this->result = $result;
	}

	public function isInitialized(): bool
	{
		return $this->result !== null && $this->result !== false;
	}

	public function getAliasData(string $alias): array|null|false
	{
		if ($this->isInitialized() === false) return false;
		$data = [];
		foreach ($this->result as $columnName => $columnValue) {
			if (preg_match("/^{$alias}_([^\$]+)$/", $columnName, $regs)) {
				if ($columnValue !== null) $data[$regs[1]] = $columnValue;
			}
		}
		return \count($data) > 0 ? $data : null;
	}
}
