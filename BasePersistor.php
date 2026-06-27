<?php

namespace AcidORM;

use Nette;
use AcidORM\Utils\AnnotationParser;

/**
 * @property \DibiConnection $db
 * @property BaseObject $object
 * @property BaseMapper $mapper
 * @property string $table
 * @property AcidORM\Managers\MapperManager $mapperManager
 * @property Nette\Caching\Cache $cache
 */
class BasePersistor
{

	use \Nette\SmartObject;
	private ?\Dibi\Connection $db = null;
	private ?BaseObject $object = null;
	private ?BaseMapper $mapper = null;
	private ?string $table = null;

	private ?Managers\MapperManager $mapperManager = null;

	private ?Nette\Caching\Cache $cache = null;

	public function __construct($db, $mapperManager){
		$this->db = $db;
		$this->mapperManager = $mapperManager;
		$reflection = new \ReflectionClass($this);
		if(preg_match('/\\\([a-zA-Z0-9]+)Persistor$/', $reflection->getName(), $regs)){
			$class = 'Model\\Data\\'.$regs[1];
			$this->object = new $class;
			$this->table = $regs[1];
			$this->mapper = $this->mapperManager->getMapper($regs[1]);
		}
	}

	public function getDb(){
		return $this->db;
	}

	public function setDb($db){
		$this->db = $db;
	}

	public function getMapperManager(){
		return $this->mapperManager;
	}

	public function getMapper(){
		return $this->mapper;
	}

	public function getObject(){
		return $this->object;
	}

	public function getTable()
	{
		return $this->table;
	}

	public function insertUpdate(BaseObject $baseObject){
		$array = $this->mapper->toArray($baseObject);
		
		if($this->db->getConfig('driver') === 'mysqli' || $this->db->getConfig('driver') === 'mysql'){
			$this->db->query('insert ignore into [' . $this->mapper->table . '] ', $array, ' on duplicate key update %a', $array);
		} else {
			if($baseObject->id === null){
				$this->db->insert($this->mapper->table, $array)->execute();
				$baseObject->id = $this->db->insertId;
			} else {
				unset($array['id']);
				$this->db->update($this->mapper->table, $array)->where('[id] = %i', $baseObject->id)->execute();
			}
		} 

		if($baseObject->id === null){
			try{
			$baseObject->id = $this->db->insertId();
			} catch (\Exception $ex){
				
			}
		}
	}

	public function delete($id){
		$this->db->delete($this->mapper->table)->where('id = %i', $id)->execute();
	}

	public function getById($id, $withDependencies = false, $dependencies = null){
		if($withDependencies) $dependencies = $this->getDependencies($dependencies);

		$q = $this->createQuery($withDependencies, $dependencies);
		$q = $q->where('[object].[id] = %i', $id);

		foreach($q as $r){
			return $this->map($r, $withDependencies, $dependencies);
		}

		return null;
	}

	public function getAll($limit = null, $offset = null, $withDependencies = false, $dependencies = null, &$count = null, $orderBy = null,  $direction = 0){
		if($withDependencies) $dependencies = $this->getDependencies($dependencies);
		
		$objects = [];

		$q = $this->createQuery($withDependencies, $dependencies);
		$count = $q->count();
		if($orderBy){
			$q = $q->orderBy($orderBy . ' ' . ($direction === 1 ? 'desc' : 'asc'));
		}
		if($limit !== null) $q = $q->limit($limit);
		if($offset !== null) $q = $q->offset($offset);

		foreach($q as $r){
			$objects[] = $this->map($r, $withDependencies, $dependencies);
		}

		return $objects;
	}

	public function getByProperty($propertyName, $propertyValue, $withDependencies = false, $dependencies = null){
		if($withDependencies) $dependencies = $this->getDependencies($dependencies);

		$q = $this->createQuery($withDependencies, $dependencies);
		$q = $q->where('[object].['.$propertyName.'] ' . (is_array($propertyValue) ? ' in %in ' : ' = %s '), $propertyValue);	
		$q->limit(1);
		foreach($q as $r){
			return $this->map($r, $withDependencies, $dependencies);
		}

		return null;		
	}

	public function getByProperties($properties, $withDependencies = false, $dependencies = null){
		if($withDependencies) $dependencies = $this->getDependencies($dependencies);

		$q = $this->createQuery($withDependencies, $dependencies);
		foreach($properties as $propertyName => $propertyValue){
			$q = $q->where('[object].['.$propertyName.'] ' . (is_array($propertyValue) ? ' in %in ' : ' = %s '), $propertyValue);	
		}
		$q->limit(1);
		
		foreach($q as $r){
			return $this->map($r, $withDependencies, $dependencies);
		}

		return null;		
	}	

	public function getAllByProperty($propertyName, $propertyValue, $withDependencies = false, $dependencies = null, $limit = null, $offset = null, &$count = null, $orderBy = null,  $direction = 0){
		if($withDependencies) $dependencies = $this->getDependencies($dependencies);
		$objects = [];

		$q = $this->createQuery($withDependencies, $dependencies);
		$q = $q->where('[object].['.$propertyName.'] ' . (is_array($propertyValue) ? ' in %in ' : ' = %s '), $propertyValue);
		if($orderBy){
			$q = $q->orderBy('[' . $orderBy . '] ' . ($direction ? 'desc' : 'asc'));
		}
		if($count !== null) $count = $q->count('*');

		if($limit !== null) $q = $q->limit($limit);
		if($offset !== null) $q = $q->offset($offset);			

		foreach($q as $r){
			$objects[] = $this->map($r, $withDependencies, $dependencies);
		}

		return $objects;
	}

	public function getAllByProperties($properties, $withDependencies = false, $dependencies = null, $limit = null, $offset = null, &$count = null, $orderBy = null, $direction = null){
		if($withDependencies) $dependencies = $this->getDependencies($dependencies);
		$objects = [];

		$q = $this->createQuery($withDependencies, $dependencies);
		foreach($properties as $propertyName => $propertyValue){
			$q = $q->where('[object].['.$propertyName.'] ' . (is_array($propertyValue) ? ' in %in ' : ' = %s '), $propertyValue);
		}
		if($orderBy){
			$q = $q->orderBy($orderBy . ' ' . ($direction === 1 ? 'desc' : 'asc'));
		}

		if($count !== null) $count = $q->count('*');

		if($limit !== null) $q = $q->limit($limit);
		if($offset !== null) $q = $q->offset($offset);		
		foreach($q as $r){
			$objects[] = $this->map($r, $withDependencies, $dependencies);
		}

		return $objects;
	}	

	public function getAllForOneToMany(DB\Relationships\OneToMany $oneToMany, $value, $withDependencies = false, $dependencies = null){
		return $this->getAllByProperty($oneToMany->getPropertyName(), $value, $withDependencies, $dependencies);
	}

	public function getAllForManyToMany(DB\Relationships\ManyToMany $manyToMany, $value, $withDependencies = false, $dependencies = null){
		if($withDependencies) $dependencies = $this->getDependencies($dependencies);

		$objects = [];

		$q = $this->createQuery($withDependencies, $dependencies)
				  ->join('['.$manyToMany->table.'] [rel1]')->on(sprintf('[object].[id] = [rel1].[%s]', $manyToMany->foreignKey))
				  ->where(sprintf('[rel1].[%s] = %%s', $manyToMany->column), $value);

		foreach($q as $r){
			$objects[] = $this->map($r, $withDependencies, $dependencies);
		}

		return $objects;
	}	

	public function map($r, $withDependencies = false, $dependencies = null){
		$result = new DB\Result($r);
		$object = $this->mapper->map($result->getAliasData('object'));
		if($withDependencies){
			foreach($dependencies as $property => $dependency){
				$aliasData = $result->getAliasData($property);
				$dependencyObject = $this->mapperManager->getMapper($dependency->className)->map($result->getAliasData($property));
				$object->$property = $dependencyObject;
			}
		}
		return $object;		
	}

	public function getDependencies($dependencies = null){
		if($dependencies === null){
			$newDependencies = [];
			foreach($this->mapper->getOneToOneRelationships() as $property => $hasOne){
				$newDependencies[] = $property;
			}
			$dependencies = $newDependencies;
		}

		$data  = [];
		foreach ($dependencies as $propertyName) {
			$reflection = new \ReflectionClass($this->object);
			$property = $reflection->getProperty($propertyName);
			if(AnnotationParser::hasAnnotation($property, 'oneToOne')){
				$annotation = AnnotationParser::getAnnotation($property, 'oneToOne');
				$data[$propertyName] = new DB\Relationships\OneToOne(
					$annotation['className'], 
					$annotation['propertyName'], 
					isset($annotation['canBeNull']) ? $annotation['canBeNull'] : false
				);
			}
		}
		return $data;
	}

	public function createQuery($withDependencies = false, $dependencies = null){
		$columns = $this->mapper->getColumns('object');
		if($withDependencies){
			foreach($dependencies as $propertyName => $dependency){
				$columns = array_merge($columns, $this->mapperManager->getMapper($dependency->className)->getColumns($propertyName));
			}
		}

		$q = $this->db->select($columns)
					  ->from(sprintf('[%s] [%s]', $this->mapper->getTable(), 'object'));

		if($withDependencies){
			foreach($dependencies as $property => $dependency){
				$table = $this->mapperManager->getMapper($dependency->className)->getTable();
				$alias = $property;
				if($dependency->canBeNull){
					$q = $q->leftJoin(sprintf('[%s] [%s]', $table, $alias))
						   ->on(sprintf('[%s].[%s] = [%s].[%s]', $alias, 'id', 'object', $dependency->propertyName));
				} else {
					$q = $q->join(sprintf('[%s] [%s]', $table, $alias))
						   ->on(sprintf('[%s].[%s] = [%s].[%s]', $alias, 'id', 'object', $dependency->propertyName));
				}

			}
		}

		return $q;
	}

	public function setCache(Nette\Caching\Cache $cache)
	{
		$this->cache = $cache;
	}

	public function getCache()
	{
		return $this->cache;
	}	

	public function getKeyValuePairs($key = 'id', $value = 'name', $restrictions = [])
	{
		$q = $this->db->select(sprintf('[%s], [%s]', $key, $value))
					  ->from($this->mapper->table);
		foreach($restrictions as $restrictionKey => $restrictionValue){
			$q->where('%n = %s', $restrictionKey, $restrictionValue);
		}
		$q->orderBy('%n asc', $value);
		return $q->fetchPairs($key, $value);
	}

	public function getKeyHierarchy($key = 'id', $parent = 'parent')
	{	
		$data = [];

		$this->getHierarchy($data, $key, $parent);

		return $data;
	}

	public function getHierarchy(&$data, $key, $parentKey = 'parent', $parent = 0)
	{
		foreach($this->getAllByProperty($parentKey, $parent) as $object){
			$children = [];
			$this->getHierarchy($children, $key, $parentKey, $object->{$key}, $object->{$parentKey});
			$data[$object->{$key}] = $children;
		}		
	}	

	public function getKeyValuePairsHierarchy(&$data, $parent = 0, $lvl = 1, $parentText = '')
	{
		$q = $this->db->select('id, parent, name')
				  	  ->from($this->table)
				  	  ->where('parent = %i', $parent);
		if($lvl !== false){
			$delimiter = '';
			for($i = 0; $i < $lvl; $i++){
				$delimiter.='--';
			}		
			$delimiter .= ' ';	
		} else {
			$delimiter = $parentText . ($parentText !== '' ? ' > ' : '') ;
		}

		foreach ($q as $r) {
			$data[$r->id] = $delimiter . $r->name;
			$this->getKeyValuePairsHierarchy($data, $r->id, $lvl === false ? $lvl : $lvl+1, $data[$r->id]);
		}
		
	}	
}