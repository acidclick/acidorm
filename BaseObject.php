<?php

namespace AcidORM;

use Nette;

class BaseObject implements \JsonSerializable
{
	use \Nette\SmartObject;
	
	public function jsonSerialize()
	{
		$data = [];
		$reflection = Nette\Reflection\ClassType::from($this);
		foreach($reflection->getProperties() as $property){
			$data[$property->name] = $this->{$property->name};
		}
		return $data;
	}

	public function getLabel($name = null)
	{
		if(property_exists($this, 'label')){
			return $this->label;
		} else if($name !== null){
			if(property_exists($this, $name)){
				if(Nette\Reflection\ClassType::from($this)->getProperty($name)->hasAnnotation('label')){
					return Nette\Reflection\ClassType::from($this)->getProperty($name)->getAnnotation('label');
				}
			}
		}

		return '@' . $name;
	}

}