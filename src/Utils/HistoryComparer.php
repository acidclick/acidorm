<?php

namespace acidorm\Utils;

use acidorm\Managers;
use acidorm\Interfaces\IHistoryProxy;
use acidorm\Utils\AnnotationParser;

class HistoryComparer
{
	use \Nette\SmartObject;
	
	public static function hasChanges($objectOld, $objectNew)
	{
		$changes = false;
		$reflection = new \ReflectionClass($objectNew);
		foreach($reflection->getProperties() as $property){
			if(AnnotationParser::hasAnnotation($property, 'label')){
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
		$reflection = new \ReflectionClass($objectNew);
		foreach($reflection->getProperties() as $property){
			if(AnnotationParser::hasAnnotation($property, 'label')){
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
						if($old !== $new) $changes .= ($changes === '' ? '' : '<br />') . sprintf('<strong>%s</strong>: %s', AnnotationParser::getAnnotation($property, 'label'), $new);
					}
				} else {
					$new = self::getValue($objectNew->{$property->name}, $property);
					$old = self::getValue($objectOld->{$property->name}, $property);
					if($old !== $new) $changes .= ($changes === '' ? '' : '<br />') . sprintf('<strong>%s</strong>: %s', AnnotationParser::getAnnotation($property, 'label'), $new);
				}
			}
		}
		return $changes;
	}

	public static function getValue($value, \ReflectionProperty $property)
	{
		if(is_object($value) && method_exists($value, '__toString') && !($value instanceof \DateTimeInterface)){
			$result = (string) $value;
		} else if($value instanceof \DateTimeInterface){
			$result = $value->format('Y-m-d H:i:s');
		} else if(is_bool($value)){
			$result = $value === true ? 'Ano' : 'Ne';
		} else if(AnnotationParser::hasAnnotation($property, 'enum')){
			$result = call_user_func(AnnotationParser::getAnnotation($property, 'enum') . '::getName', $value);
		} else if(AnnotationParser::hasAnnotation($property, 'formatter')){
			$annotation = AnnotationParser::getAnnotation($property, 'formatter');
			if(is_string($annotation)){
				$result = call_user_func($annotation . '::format', $value, $property);
			} else {
				$result = call_user_func($annotation->class . '::format', $value, $property, $annotation);
			}
		} else {
			$result = (string) $value;
		}
		return $result;
	}	
}