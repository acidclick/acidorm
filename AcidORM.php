<?php

namespace AcidORM;

use Nette,
	AcidORM\Managers;

/**
 * @property Managers\PersistorManager $persistorManager
 * @property Managers\MapperManager $mapperManager
 * @property Managers\GridManager $gridManager
 * @property Managers\FacadeManager $facadeManager
 * @property \Dibi\Connection $db
 * @property Nette\Caching\Cache
 * @property array $parameters
 * @property array $generators
 */

class AcidORM
{
	use \Nette\SmartObject;
	private $persistorManager;
	private $mapperManager;
	private $gridManager;
	private $facadeManager;

	private $db;
	private $cacheProvider;

	private $parameters = [];

	private $generators = [];

	public function setDb(\Dibi\Connection $db)
	{
		$this->db = $db;
	}

	public function getDb()
	{
		return $this->db;
	}

	public function getCacheProvider()
	{
		return $this->cacheProvider;
	}

	public function setCacheProvider($cacheProvider)
	{
		$this->cacheProvider = $cacheProvider;
	}

	public function getParameters()
	{
		return $this->parameters;
	}

	public function setParameters($parameters)
	{
		$this->parameters = $parameters;
	}

	public function startup()
	{
		$this->mapperManager = $this->createMapperManager();

		$this->persistorManager = $this->createPersistorManager();

		$this->facadeManager = $this->createFacadeManager();

		$this->gridManager = $this->createGridManager();
	}

	private function createMapperManager()
	{
		return new Managers\MapperManager;
	}

	private function createPersistorManager()
	{
		$persistorManager = new Managers\PersistorManager;
		$persistorManager->mapperManager = $this->mapperManager;
		$persistorManager->db = $this->db;
		$persistorManager->cache = $this->cacheProvider;

		return $persistorManager;
	}

	public function createGridManager()
	{
		$gridManager = new Managers\GridManager;
		$gridManager->db = $this->db;

		return $gridManager;
	}

	public function createFacadeManager()
	{
		$facadeManager = new Managers\FacadeManager;
		$facadeManager->persistorManager = $this->persistorManager;
		$facadeManager->mapperManager = $this->mapperManager;
		$facadeManager->cache = $this->cacheProvider;
		$facadeManager->parameters = $this->parameters;

		return $facadeManager;
	}

    public function &__get($name)
    {
    	if(preg_match('/^(.+)Facade$/', $name, $regs)){
    		$facade = $this->facadeManager->{$name};
    		return $facade;
    	}

    	if(preg_match('/^(.+)Mapper$/', $name, $regs)){
    		$mapper = $this->mapperManager->{$name};
    		return $mapper;
    	}    	

    	if(preg_match('/^(.+)Persistor$/', $name, $regs)){
    		$persistor = $this->persistorManager->{$name};
    		return $persistor;
    	}   

    	if(preg_match('/^(.+)Grid$/', $name, $regs)){
    		$grid = $this->gridManager->{$name};
    		return $grid;
    	}

    	if(isset($this->{$name})) return $this->{$name};
    	throw new \Exception;
    }

    public function getFacadeManager()
    {
    	return $this->facadeManager;
    }	

    public function getFacade($name)
    {
    	$facade = $this->facadeManager->getFacade($name);
    	return $this->facadeManager->getFacade($name);
    }

    public function getGrid($name)
    {
    	return $this->gridManager->getGrid($name);
    }

    public function getMapper($name)
    {
    	return $this->mapperManager->getMapper($name);
    }

    public function getPersistor($name)
    {
    	return $this->persistorManager->getPersistor($name);
    }

    public function getMapperManager()
    {
    	return $this->mapperManager;
    }

    public function getPersistorManager()
    {
    	return $this->persistorManager;
    }

    public function getGridManager()
    {
    	return $this->gridManager;
    }    

    public function getGenerator($type)
    {
    	if(!isset($this->generators[$type])){
    		$this->generators[$type] = $this->{'create' . $type . 'Generator'}();
    	}

    	return $this->generators[$type];
    }

	public function createDatabaseFirstGenerator()
	{
		$generator = new Generators\DatabaseFirst;
		$generator->db = $this->db;
		$generator->databaseDriver = $this->parameters['databaseDriver'];
		$generator->appDir = $this->parameters['appDir'];

		return $generator;
	}

	public function createDirStructure()
	{
		$dataDir = $this->parameters['appDir'] . '/model/Data';
		if(!is_dir($dataDir)) mkdir($dataDir, 0755);

		$enumsDir = $this->parameters['appDir'] . '/model/Enums';
		if(!is_dir($enumsDir)) mkdir($enumsDir, 0755);

		$interfacesDir = $this->parameters['appDir'] . '/model/Interfaces';
		if(!is_dir($interfacesDir)) mkdir($interfacesDir, 0755);		

		$mappersDir = $this->parameters['appDir'] . '/model/Mappers';
		if(!is_dir($mappersDir)) mkdir($mappersDir, 0755);		

		$persistorsDir = $this->parameters['appDir'] . '/model/Persistors';
		if(!is_dir($persistorsDir)) mkdir($persistorsDir, 0755);		

		$facadesDir = $this->parameters['appDir'] . '/model/Facades';
		if(!is_dir($facadesDir)) mkdir($facadesDir, 0755);

		$gridsDir = $this->parameters['appDir'] . '/model/Grids';
		if(!is_dir($gridsDir)) mkdir($gridsDir, 0755);		

		$formsDir = $this->parameters['appDir'] . '/model/Forms';
		if(!is_dir($formsDir)) mkdir($formsDir, 0755);		
	}
}