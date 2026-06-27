<?php

namespace AcidORM\DB;

use Nette;

/**
 * @property array $result
 */
class Result
{
	use \Nette\SmartObject;
	
	private $result;

	public function __construct($result){
		$this->result = $result;
	}

	public function isInitialized(){
		return $this->result !== null && $this->result !== false;
	}

	public function getAliasData($alias){
		if($this->isInitialized() === false) return false;
		$data = [];
		foreach ($this->result as $columnName => $columnValue) {
			if(preg_match('/^'.$alias.'_([^$]+)$/', $columnName, $regs)){
				if($columnValue !== null) $data[$regs[1]] = $columnValue;
			}
		}
		if(sizeof($data)>0) return $data;
		return null;

	}

}