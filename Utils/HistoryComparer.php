<?php

namespace AcidORM\Utils;

use Nette,
	AcidORM\Managers,
	AcidORM\Interfaces\IHistoryProxy;

class HistoryComparer
{
	use \Nette\SmartObject;
	
	public static function hasChanges($objectOld, $objectNew)
	{
		$changes = false;
		$reflection = Nette\Reflection\ClassType::from($objectNew);
		foreach($reflection->getProperties() as $property){
			if($property->hasAnnotation('label')){
				if(is_array($objectNew->{$property->name})){
					$tmp1 = $objectNew->{$property->name};
					$tmp2 = $objectOld->{$property->name};
					foreach($tmp1 as $index => $tmp){
						if(isset($tmp2[$index])){
							$old = self::getValue($tmp2[$index], $property);
						} else {
							$old = '';
						}
						$new = self::getValue($tmp, $property);
						if($old !== $new) $changes = true;
					}
				} else {
					$new = self::getValue($objectNew->{$property->name}, $property);
					$old = self::getValue($objectOld->{$property->name}, $property);					
					if($old !== $new) $changes = true;
				}
			}
		}
		return $changes;
	}

	public static function getChanges($objectOld, $objectNew)
	{
		/*Nette\Diagnostics\Debugger::dump($objectOld);
		Nette\Diagnostics\Debugger::dump($objectNew);
		exit;*/
		$changes = '';
		$reflection = Nette\Reflection\ClassType::from($objectNew);
		foreach($reflection->getProperties() as $property){
			if($property->hasAnnotation('label')){
				if(is_array($objectNew->{$property->name})){
					$tmp1 = $objectNew->{$property->name};
					$tmp2 = $objectOld->{$property->name};
					foreach($tmp1 as $index => $tmp){
						if(isset($tmp2[$index])){
							$old = self::getValue($tmp2[$index], $property);
						} else {
							$old = '';
						}
						$new = self::getValue($tmp, $property);
						if($old !== $new) $changes .= ($changes === '' ? '' : '<br />') . sprintf('<strong>%s</strong>: %s', $property->getAnnotation('label'), $new);
					}
				} else {
					$new = self::getValue($objectNew->{$property->name}, $property);
					$old = self::getValue($objectOld->{$property->name}, $property);					
					if($old !== $new) $changes .= ($changes === '' ? '' : '<br />') . sprintf('<strong>%s</strong>: %s', $property->getAnnotation('label'), $new);
				}
			}
		}
		return $changes;
	}

	public static function getValue($property, $reflection)
	{
		if($property instanceof Nette\Object){
			$value = $property->__toString();
		} else if($property instanceof \DateTimeInterface){
			$value = $property->format('Y-m-d H:i:s');
		} else if(is_bool($property)){
			$value = $property === true ? 'Ano' : 'Ne';
		} else if($reflection->hasAnnotation('enum')){
			$value = call_user_func($reflection->getAnnotation('enum') . '::getName', $property);
		} else if($reflection->hasAnnotation('formatter')){
			$annotation = $reflection->getAnnotation('formatter');
			if(is_string($annotation)){
				$value = call_user_func($annotation . '::format', $property, $reflection);
			} else {
				$value = call_user_func($annotation->class . '::format', $property, $reflection, $annotation);
			}
		} else {
			$value = (string)$property;
		}	
		return $value;	
	}	
}