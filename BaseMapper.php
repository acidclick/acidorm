<?php

namespace AcidORM;

use Nette,
	AcidORM\DB;
/**
 * @property BaseObject $object
 * @property string $namespace
 * @property string $table
 * @property array $oneToOneRelations
 * @property array $manyToManyRelations
 * @property array $oneToManyRelations
 */
class BaseMapper
{
	use \Nette\SmartObject;
	private $namespace = 'Model\\Data\\';

	private $object;

	private $table;
	private $oneToOneRelations;
	private $manyToManyRelations;
	private $oneToManyRelations;

	public function __construct(){
		$reflection = Nette\Reflection\ClassType::from($this);
		if(preg_match('/([a-zA-Z0-9]+)Mapper$/', $reflection->name, $regs)){
			$className = $this->namespace . $regs[1];
			$this->object = new $className();
			$this->table = $regs[1];
		}
	}

	public function toArray(BaseObject $object){
		if(method_exists($object, 'tierDown')) $object->tierDown();
		$array = [];
		$reflection = Nette\Reflection\ClassType::from($this->object);		
		foreach($reflection->getProperties() as $property){
			if(!$property->hasAnnotation('oneToOne') && !$property->hasAnnotation('oneToMany') && !$property->hasAnnotation('manyToMany') && !$property->hasAnnotation('dontMap')){
				if($object->{$property->name} !== null || preg_match('/Date/', $property->name) || preg_match('/Time$/', $property->name) || preg_match('/Id/', $property->name)) $array[$property->name] = $object->{$property->name};	
			}
			
		}
		return $array;
	}

	public function map($data){
		if(empty($data) || sizeof($data) === 0) return null;
		$object = clone $this->object;
		$reflection = Nette\Reflection\ClassType::from($object);
		foreach($data as $property => $value){
			if($reflection->hasProperty($property)){
				$object->{$property} = $value;
			}
		}
		if(method_exists($object, 'setUp')) $object->setUp();
		return $object;
	}

	public function create(){
		return clone $this->object;
	}

	public function getColumns($alias){
		$columns = [];
		$reflection = Nette\Reflection\ClassType::from($this->object);
		foreach($reflection->getProperties() as $property){
			if(!$property->hasAnnotation('oneToOne') && !$property->hasAnnotation('manyToMany') && !$property->hasAnnotation('oneToMany') && !$property->hasAnnotation('dontMap')){
				$columns[$alias . '.' . $property->name] =  $alias . '_' . $property->name;
			}
		}		
		return $columns;
	}

	public function getManyToManyRelationships(){
		if($this->manyToManyRelations === null){
			$this->manyToManyRelations = [];
			$reflection = Nette\Reflection\ClassType::from($this->object);
			foreach($reflection->getProperties() as $property){
				if($property->hasAnnotation('manyToMany')){
					$annotation = $property->getAnnotation('manyToMany');
					$manyToMany = new DB\Relationships\ManyToMany(
						$annotation['className'],
						$annotation['table'],
						$annotation['foreignKey'],
						$annotation['column']
					);
					$this->manyToManyRelations[$property->name] = $manyToMany;
				}
			}
		}
		return $this->manyToManyRelations;
	}	

	public function getOneToOneRelationships(){
		if($this->oneToOneRelations === null){
			$this->oneToOneRelations = [];
			$reflection = Nette\Reflection\ClassType::from($this->object);
			foreach($reflection->getProperties() as $property){
				if($property->hasAnnotation('oneToOne')){
					$annotation = $property->getAnnotation('oneToOne');
					$oneToOne = new DB\Relationships\OneToOne(
						$annotation['className'],
						$annotation['propertyName'],
						isset($annotation['canBeNull']) ? $annotation['canBeNull'] : false
					);
					$this->oneToOneRelations[$property->name] = $oneToOne;
				}
			}
		}
		return $this->oneToOneRelations;
	}	

	public function getOneToManyRelationships(){
		if($this->oneToManyRelations === null){
			$this->oneToManyRelations = [];
			$reflection = Nette\Reflection\ClassType::from($this->object);
			foreach($reflection->getProperties() as $property){
				if($property->hasAnnotation('oneToMany')){
					$annotation = $property->getAnnotation('oneToMany');
					$oneToMany = new DB\Relationships\OneToMany(
						$annotation['className'],
						$annotation['foreignKey']
					);
					$this->oneToManyRelations[$property->name] = $oneToMany;
				}
			}
		}
		return $this->oneToManyRelations;
	}	

	public function getTable(){
		return $this->table;
	}	

}