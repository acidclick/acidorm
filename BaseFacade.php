<?php

namespace AcidORM;

use Nette;
use AcidORM\Managers;
use AcidORM\Utils\AnnotationParser;
use AcidORM\Interfaces\IHistoryProxy;
use AcidORM\Interfaces\IHistoryObject;
use AcidORM\Traits\HistoryObject;

/**
 * @property string $name
 * @property Managers\PersistorManager $persistorManager
 * @property Managers\MapperManager $mapperManager
 * @property Nette\Caching\Cache $cache
 * @property Managers\FacadeManager $facadeManager
 * @property array $parameters
 * @property BasePersistor $persistor
 */
class BaseFacade
{
	use \Nette\SmartObject;
	
	protected ?string $name = null;

	protected ?Managers\PersistorManager $persistorManager = null;

	protected ?Managers\MapperManager $mapperManager = null;

	private ?Nette\Caching\Cache $cache = null;

	protected ?Managers\FacadeManager $facadeManager = null;

	protected ?array $parameters = null;

	public function __construct(){
		$reflection = new \ReflectionClass($this);
		if(preg_match('/\\\([a-zA-Z0-9]+)Facade$/', $reflection->getName(), $regs)){
			$this->name = $regs[1];
		}	
	}

	public function startup()
	{

	}

	public function setPersistorManager(Managers\PersistorManager $persistorManager){
		$this->persistorManager = $persistorManager;
	}

	public function setMapperManager(Managers\MapperManager $mapperManager){
		$this->mapperManager = $mapperManager;
	}	

	public function mapDependencies(BaseObject &$baseObject = null, $withDependencies = false, $dependencies = null){
		if($baseObject === null) return;
		$properties = $this->mapperManager->getMapper($this->name)->getOneToManyRelationships();
		foreach($properties as $propertyName => $oneToMany){
			if($dependencies === null || in_array($propertyName, $dependencies))
				$baseObject->{$propertyName} = 
					$this->persistorManager->getPersistor($oneToMany->className)->getAllForOneToMany(
						$oneToMany,
						$baseObject->id,
						true
					);
		}

		$properties = $this->mapperManager->getMapper($this->name)->getManyToManyRelationships();
		foreach ($properties as $propertyName => $oneToMany) {
			if($dependencies === null || in_array($propertyName, $dependencies))
				$baseObject->{$propertyName} = 
					$this->persistorManager->getPersistor($oneToMany->className)->getAllForManyToMany(
						$oneToMany,
						$baseObject->id,
						true
					);

		}
		if(method_exists($baseObject, 'setUp')){
			$baseObject->setUp();
		}
	}

	public function getPersistor(){
		return $this->persistorManager->getPersistor($this->name);
	}

	public function setCache(Nette\Caching\Cache $cache)
	{
		$this->cache = $cache;
	}

	public function getCache()
	{
		return $this->cache;
	}

	public function getKeyValuePairs($className = null, $key = 'id', $value = 'name')
	{
		if($className === null) $className = $this->name;
		$keyValuePairs = $this->persistorManager->getPersistor($className)->getKeyValuePairs($key, $value);
		return $keyValuePairs;
	}

	public function getKeyValue($key = 'id', $value = 'name', $restrictions = [])
	{
		return $this->persistor->getKeyValuePairs($key, $value, $restrictions);
	}

	public function setFacadeManager(Managers\FacadeManager &$facadeManager)
	{
		$this->facadeManager = $facadeManager;
	}

	public function setParameters($parameters)
	{
		$this->parameters = $parameters;
	}

	public function &__call($name, $args)
	{

		if(method_exists($this, $name)){
			$result = call_user_func_array([$this, $name], $args);
			return $result;
		}

		if(preg_match('/^get([A-Z]{1}.+)By([A-Z]{1}.+)$/', $name, $regs)){
			if($this->isCallable($regs[1])){
				if($this->isPlural($regs[1])){
					$result = $this->simpleGetAllBy($this->getSingular($regs[1]), $regs[2], $args);
					return $result;
				} else {
					$result = $this->simpleGetBy($regs[1], $regs[2], $args);
					return $result;
				}
			}

		}

		if(preg_match('/^get([A-Z]{1}.+)$/', $name, $regs)){
			$result = $this->simpleGetAll($this->getSingular($regs[1]), $args);
			return $result;
		}

		if(preg_match('/^delete([A-Z]{1}.+)$/', $name, $regs)){
			$result = $this->simpleDelete($regs[1], $args[0]);
			return $result;
		}

		if(preg_match('/^insertUpdate([A-Z]{1}.+)$/', $name, $regs)){
			$result = $this->simpleInsertUpdate($regs[1], $args[0], isset($args[1]) ? $args[1] : null);
			return $result;
		}
	}

	private function isCallable($class)
	{
		
		if($class !== $this->name && !$this->isPlural($class)){
			throw new \Exception(sprintf('Object %s cannot be called from %sFacade.', $class, $this->name));
		}		

		return true;
	}

	private function isPlural($class)
	{
		if($class === $this->name) return false;

		$reflection = new \ReflectionClass('Model\\Data\\' . $this->name);
		$plural = AnnotationParser::getAnnotation($reflection, 'plural');

		return $class === $this->name . 's' || $plural === $class;
	}

	private function getSingular($class)
	{
		return $this->name;
	}

	private function simpleGetBy($class, $calledProperties, $values)
	{
		foreach(preg_split('/And/', $calledProperties) as $index => $property){
			$property = Nette\Utils\Strings::lower(Nette\Utils\Strings::substring($property, 0, 1)) . Nette\Utils\Strings::substring($property, 1);
			if(!property_exists('Model\\Data\\' . $class, $property)){
				throw new \Exception(sprintf('Object %s has no property called %s.', $class, $property));
			}

			if(!isset($values[$index])){
				throw new \Exception(sprintf('Missing %s param.', $property));
			}

			$params[$property] = $values[$index];
		}

		$object = $this->persistor->getByProperties($params, true);
		if($object !== null) $this->mapDependencies($object);
		return $object;
	}

	private function simpleGetAllBy($class, $calledProperties, $values)
	{
		foreach(preg_split('/And/', $calledProperties) as $index => $property){
			$property = Nette\Utils\Strings::lower(Nette\Utils\Strings::substring($property, 0, 1)) . Nette\Utils\Strings::substring($property, 1);
			if(!property_exists('Model\\Data\\' . $class, $property)){
				throw new \Exception(sprintf('Object %s has no property called %s.', $class, $property));
			}

			if(!isset($values[$index])){
				throw new \Exception(sprintf('Missing %s param.', $property));
			}

			$params[$property] = $values[$index];
		}

		$count = 0;
		$objects = $this->persistor->getAllByProperties(
			$params, 
			true, 
			null,   
			sizeof($values) >= sizeof($params) + 1 ? $values[sizeof($params)] : null, 
			sizeof($values) >= sizeof($params) + 2 ? $values[sizeof($params) + 1] : null,
			$count,
			sizeof($values) >= sizeof($params) + 3 ? $values[sizeof($params) + 2] : null, 
			sizeof($values) >= sizeof($params) + 4 ? $values[sizeof($params) + 3] : null 
		);
		foreach($objects as $object){
			if($object !== null) $this->mapDependencies($object);
		}
		if(sizeof($values) >= sizeof($params) + 5 && is_callable($values[sizeof($params) + 4])){
			$values[sizeof($params) + 4]($count);
		}
		return $objects;
	}	

	private function simpleGetAll($class, $values)
	{
		$count = 0;
		$objects = $this->persistor->getAll(
			isset($values[0]) ? $values[0] : null, 
			isset($values[1]) ? $values[1] : null, 
			true,
			null,
			$count,
			isset($values[2]) ? $values[2] : null,
			isset($values[3]) ? $values[3] : null,
		);
		foreach($objects as $object){
			if($object !== null) $this->mapDependencies($object);
		}
		if(isset($values[4]) && is_callable($values[4])){
			$values[4]($count);
		}
		return $objects;
	}	

	private function simpleDelete($class, $id)
	{
		$this->isCallable($class);

		$this->persistor->delete($id);
	}

	private function simpleInsertUpdate($class, $object, $userId = null, $callback = null)
	{
		$this->isCallable($class);



		if((int)$userId === 0 && preg_match('/^[\d]+$/', $userId)) $userId = \Model\Utils\Helpers::$userId;
		$new = $object->id === null;
		if(!$new && ($this instanceof IHistoryProxy || $object instanceof IHistoryObject)){
			$oldObject = $this->simpleGetBy($class, 'Id', [$object->id]);
		}
		if($object instanceof IHistoryObject && empty($object->key)){
			do{
				$unique = false;
				$object->key = HistoryObject::generateUniqueId();
				$q = $this->persistorManager->getDb()->select('id')->from('%n', HistoryObject::getTableName())->where('[key] = %s', $object->key)->limit(1);
				foreach($q as $r){
					$unique = true;
				}
			} while($unique);
		}

		if(method_exists($object, 'tearDown')) $object->tearDown();
		$this->persistor->insertUpdate($object); 

		if($this instanceof IHistoryProxy)
		{
			if($new){
				$namespacedClass = sprintf('Model\\Data\\%s', $class);
				$oldObject = new $namespacedClass();
			}
			$newObject = $this->simpleGetBy($class, 'Id', [$object->id]);
			if($newObject === null) return;
			$reflection = new \ReflectionClass($newObject);
			foreach($reflection->getProperties() as $property){
				if(AnnotationParser::hasAnnotation($property, 'label') && AnnotationParser::hasAnnotation($property, 'historyDontMap')) $oldObject->{$property->name} = $object->{$property->name};
			}

			if(Utils\HistoryComparer::hasChanges($oldObject, $newObject)){
				$history = new \Model\Data\History;
				if(AnnotationParser::hasAnnotation($reflection, 'historyBinding')){
					$objectKey = AnnotationParser::getAnnotation($reflection, 'historyBinding');
					$objectId = $object->$objectKey;
				} else {
					$objectKey = Nette\Utils\Strings::lower(Nette\Utils\Strings::substring($this->name, 0, 1)) . Nette\Utils\Strings::substring($this->name, 1) . 'Id';
					$objectId = $object->id;
				}

				$history->$objectKey = $objectId;
				$history->userId = $userId;
				$history->created = date('Y-m-d H:i:s');
				$changes = Utils\HistoryComparer::getChanges($oldObject, $newObject);
				if(AnnotationParser::hasAnnotation($reflection, 'historyBinding') && AnnotationParser::hasAnnotation($reflection, 'name')){
					$changes = sprintf('<strong style="font-size:120%%;">%s</strong><br />%s', AnnotationParser::getAnnotation($reflection, 'name'), $changes);
				}
				if($callback !== null) $changes = $callback($changes);
				$history->changes = $changes;
				$this->facadeManager->historyFacade->insertUpdateHistory($history);
			}
		} else if($object instanceof IHistoryObject){
			if($new){
				$namespacedClass = sprintf('Model\\Data\\%s', $class);
				$oldObject = new $namespacedClass();
			}
			$newObject = $this->simpleGetBy($class, 'Id', [$object->id]);
			if($newObject === null) return;
			$reflection = new \ReflectionClass($newObject);
			foreach($reflection->getProperties() as $property){
				if(AnnotationParser::hasAnnotation($property, 'label') && AnnotationParser::hasAnnotation($property, 'historyDontMap')) $oldObject->{$property->name} = $object->{$property->name};
			}
			if(Utils\HistoryComparer::hasChanges($oldObject, $newObject)){
				$reflect = new \ReflectionClass($object);

				$history = new \Model\Data\History2;
				$history->key = $object->key;
				$history->class = $reflect->getShortName();
				$history->userId = $userId;
				$history->created = date('Y-m-d H:i:s');
				$changes = Utils\HistoryComparer::getChanges($oldObject, $newObject);
				if($callback !== null) $changes = $callback($changes);
				$history->changes = $changes;
				$this->facadeManager->history2Facade->insertUpdateHistory2($history);
			}				
		}
	}	

	public function cleanCacheByTag($tag)
	{
		$this->cache->clean([Nette\Caching\Cache::TAGS => [$tag]]);
	}

	public function getKeyValuePairsHierarchy($full = false){
		$data = [];
		if($full){
			$this->getPersistor()->getKeyValuePairsHierarchy($data, 0, false);
		} else {
			$this->getPersistor()->getKeyValuePairsHierarchy($data);
		}
		
		return $data;
	}	

}