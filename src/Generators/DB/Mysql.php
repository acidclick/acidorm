<?php

namespace AcidORM\Generators\DB;

use Nette,
	AcidORM\Generators;
/**
 * @property \DibiConnection $db
 * @property string $appDir
 */
class Mysql extends BaseDatabase implements Generators\IDatabaseFirst
{

	private $db;
	private $appDir; 

	public function getDb()
	{
		return $this->db;
	}

	public function setDb($db)
	{
		$this->db = $db;
	}

	public function getAppDir()
	{
		return $this->appDir;
	}

	public function setAppDir($appDir)
	{
		$this->appDir = $appDir;
	}

	public function createFromTable($table)
	{
		$rt = $this->db->query('SELECT * FROM [information_schema].[TABLES] where [TABLE_SCHEMA] = %s and [TABLE_NAME] = %s', $this->db->config['database'], $table)->fetch();
		$qc = $this->db->query('select * from [information_schema].[columns] where [table_name] = %s and [table_schema] = %s order by [ORDINAL_POSITION] desc', $table, $this->db->config['database']);
		$properties = [];
		foreach($qc as $qr){
			$properties[] = (Object)['name' => $qr->COLUMN_NAME];
		}
		$properties = array_reverse($properties);


		$dependencies = $this->getDependencies($rt->TABLE_COMMENT);	
		$annotations = $this->getAnnotations($rt->TABLE_COMMENT);		

		$this->createData($table, $properties, $dependencies, $annotations);
		$this->createPersistor($table);
		$this->createMapper($table);
		$this->createFacade($table);
	}

	public function createAll()
	{

		$qt = $this->db->query('SELECT * FROM [information_schema].[TABLES] where [TABLE_SCHEMA] = %s', $this->db->config['database']);
		foreach($qt as $rt){
			$qc = $this->db->query('select * from [information_schema].[columns] where [table_name] = %s and [table_schema] = %s order by [ORDINAL_POSITION] desc', $rt->TABLE_NAME, $this->db->config['database']);

			$properties = [];
			foreach($qc as $qr){
				$properties[] = (Object)['name' => $qr->COLUMN_NAME];
			}
			$properties = array_reverse($properties);


			$dependencies = $this->getDependencies($rt->TABLE_COMMENT);		
			$annotations = $this->getAnnotations($rt->TABLE_COMMENT);		

			$this->createData($rt->TABLE_NAME, $properties, $dependencies, $annotations);
			$this->createPersistor($rt->TABLE_NAME);
			$this->createMapper($rt->TABLE_NAME);
			$this->createFacade($rt->TABLE_NAME);
		}
		    
	}
}