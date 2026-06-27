<?php

namespace AcidORM\Generators\DB;

use Nette,
	AcidORM\Generators;

/**
 * @property \Dibi\Connection $db
 * @property string $appDir
 */
class Postgre extends BaseDatabase implements Generators\IDatabaseFirst
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
		$qc = $this->db->query('select * from [information_schema].[columns] where [table_name] = %s order by [ordinal_position]', $table);
		$oid = $this->db->query('SELECT c.oid FROM pg_catalog.pg_class c WHERE c.relname = %s', $table)->fetch()->oid;
		$properties = [];
		foreach($qc as $qr){
			$qa = $this->db->query('select pg_catalog.col_description(%i, %i) as comment', $oid, $qr->ordinal_position);
			$property = new \StdClass;
			$property->name = $qr->column_name;
			$property->annotations = $this->getPropertyAnnotations($qa->fetch()->comment);
			$properties[] = $property;
		}

		$qfk = $this->db->query('
			SELECT obj_description(%sql::regclass) as note
			FROM pg_class
			WHERE relkind = %s limit 1
		', '\'"' . $table . '"\'', 'r');

		$note = $qfk->fetch()->note;
		$dependencies = $this->getDependencies($note);	
		$annotations = $this->getAnnotations($note);		

		$this->createData($table, $properties, $dependencies, $annotations);
		$this->createPersistor($table);
		$this->createMapper($table);
		$this->createFacade($table);
	}

	public function createAll()
	{
		$qt = $this->db->query('SELECT * FROM [pg_catalog].[pg_tables] where [schemaname] = %s', 'public');
		foreach($qt as $rt){
			$this->createFromTable($rt->tablename);
		}
		    
	}

}