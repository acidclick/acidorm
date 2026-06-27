<?php

namespace AcidORM;

use AcidORM\Utils\AnnotationParser;

class BaseObject implements \JsonSerializable
{
	use \Nette\SmartObject;

	public function jsonSerialize(): mixed
	{
		$data = [];
		$reflection = new \ReflectionClass($this);
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
				$property = (new \ReflectionClass($this))->getProperty($name);
				if(AnnotationParser::hasAnnotation($property, 'label')){
					return AnnotationParser::getAnnotation($property, 'label');
				}
			}
		}

		return '@' . $name;
	}

}