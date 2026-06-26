<?php

namespace AcidORM\Managers;

use Nette;
/**
 * @property array $data
 */
class BaseManager
{
	use \Nette\SmartObject;
	
	protected $data;

	protected function getData(){
		return $this->data;
	}

	protected function setData($data){
		$this->data = $data;
	}

}