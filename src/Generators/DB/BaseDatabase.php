<?php

namespace AcidORM\Generators\DB;

use Nette,
	AcidORM\Generators;

class BaseDatabase
{
	use \Nette\SmartObject;

	protected function createData($name, $properties, $dependencies, $annotations = [])
	{
		$userDefinedProperties = '';
		$userDefinedMethods = '';

		$filepath = sprintf('%s/model/Data/%s.php', $this->appDir, $name);
		if(file_exists($filepath)){
			$fp = fopen($filepath, 'r');
			$content = fread($fp, filesize($filepath));
			fclose($fp);

			$userDefinedPropertiesStart = false;
			$userDefinedMethodsStart = false;

			foreach(preg_split('/\n/', $content) as $line){
				if(preg_match('/\/\/\sUser\sdefined\sproperties/', $line)){
					$userDefinedPropertiesStart = !$userDefinedPropertiesStart;
					continue;
				}

				if(preg_match('/\/\/\sUser\sdefined\smethods/', $line)){
					$userDefinedMethodsStart = !$userDefinedMethodsStart;
					continue;
				}

				if($userDefinedPropertiesStart) $userDefinedProperties .= $line . "\n";
				if($userDefinedMethodsStart) $userDefinedMethods .= $line . "\n";
			}
		}

		$data  = "<?php\n\nnamespace Model\Data;\n\nuse Nette,\n\tAcidORM,\n\tModel;\n\n";

		if(sizeof($annotations) > 0){
			$data .= "/**\n";
			foreach ($annotations as $annotation) {
				$data .= sprintf(" * %s\n", $annotation);	
			}
			$data .= " */\n\n";
		}


		$data .= sprintf("class %s extends AcidORM\BaseObject\n{\n\n", $name);

		$data .= "\t// AcidORM generated properties\n\n";

		foreach($properties as $property){
			if(isset($property->annotations) && sizeof($property->annotations) > 0){
				$data .= sprintf("\t/**\n");
				foreach($property->annotations as $annotation){
					$data .= sprintf("\t* %s\n", $annotation);
				}
				$data .= sprintf("\t*/\n");
			}
			$data .= sprintf("\tprivate \$%s;\n", $property->name);
		}

		$data .= "\n";

		foreach($dependencies as $dependency){
			$data .= sprintf("\t/** %s */\n", $dependency->annotation);
			$data .= sprintf("\tprivate \$%s;\n", $dependency->name);
		}

		$data .= "\n\t// AcidORM generated properties";

		$data .= "\n\n\t// User defined properties\n";

		$data .= $userDefinedProperties;

		$data .= "\t// User defined properties";

		$data .= "\n\n\t// AcidORM generated methods\n\n";
		
		foreach($properties as $property){
			$data .= sprintf("\tpublic function get%s()\n\t{\n\t\treturn \$this->%s;\n\t}\n\n", Nette\Utils\Strings::firstUpper($property->name), $property->name);
			$data .= sprintf("\tpublic function set%s(\$%s)\n\t{\n\t\t\$this->%s = \$%s;\n\t}\n\n", Nette\Utils\Strings::firstUpper($property->name), $property->name, $property->name, $property->name);
		}

		foreach($dependencies as $dependency){
			$data .= sprintf("\tpublic function get%s()\n\t{\n\t\treturn \$this->%s;\n\t}\n\n", Nette\Utils\Strings::firstUpper($dependency->name), $dependency->name);
			$data .= sprintf("\tpublic function set%s(\$%s)\n\t{\n\t\t\$this->%s = \$%s;\n\t}\n\n", Nette\Utils\Strings::firstUpper($dependency->name), $dependency->name, $dependency->name, $dependency->name);
		}		

		$data .= "\t// AcidORM generated methods\n\n";

		$data .= "\t// User defined methods\n";

		$data .= $userDefinedMethods;

		$data .= "\t// User defined methods\n\n";

		$data .= "}";

		$this->saveFile($data, sprintf('Data/%s.php', Nette\Utils\Strings::firstUpper($name)));

	}

	protected function createPersistor($name)
	{
		$filepath = sprintf('Persistors/%sPersistor.php', Nette\Utils\Strings::firstUpper($name));
		if(!file_exists($this->appDir . '/model/' . $filepath)){
			$data  = sprintf("<?php\n\nnamespace Model\Persistors;\n\nuse Nette,\n\tAcidORM;\n\nclass %sPersistor extends AcidORM\BasePersistor\n{\n\n}", $name);
			$this->saveFile($data, $filepath);
		}
	}

	protected function createMapper($name)
	{
		$filepath = sprintf('Mappers/%sMapper.php', Nette\Utils\Strings::firstUpper($name));
		if(!file_exists($this->appDir . '/model/' . $filepath)){
			$data  = sprintf("<?php\n\nnamespace Model\Mappers;\n\nuse Nette,\n\tAcidORM;\n\nclass %sMapper extends AcidORM\BaseMapper\n{\n\n}", $name);
			$this->saveFile($data, $filepath);
		}
	}

	protected function createFacade($name)
	{
		$filepath = sprintf('Facades/%sFacade.php', Nette\Utils\Strings::firstUpper($name));
		if(!file_exists($this->appDir . '/model/' . $filepath)){
			$data  = sprintf("<?php\n\nnamespace Model\Facades;\n\nuse Nette,\n\tAcidORM;\n\nclass %sFacade extends AcidORM\BaseFacade\n{\n\n}", $name);
			$this->saveFile($data, $filepath);			
		}
	}	

	protected function saveFile($data, $filepath)
	{
		$fp = fopen($this->appDir . '/model/' . $filepath, 'w');
		fwrite($fp, $data);
		fclose($fp);
		chmod($this->appDir . '/model/' . $filepath, 0777);
	}

	protected function getDependencies($text)
	{
		$dependencies = [];
		foreach(preg_split('/\n/', $text) as $line){
			if(preg_match('/^([^:]+):((@oneToOne|@oneToMany|@manyToMany)[^$]+)$/', $line, $regs)){
				$dependencies[] = (Object)[
					'name' => $regs[1],
					'annotation' => $regs[2]
				];
			}
		}
		return $dependencies;
	}

	protected function getAnnotations($text)
	{
		$annotations = [];
		foreach(preg_split('/\n/', $text) as $line){
			if(preg_match('/^((@plural)[^$]+)$/', $line, $regs)){
				$annotations[] = $regs[1];
			}
		}
		return $annotations;
	}

	protected function getPropertyAnnotations($text)
	{
		$annotations = [];
		foreach(preg_split('/\n/', $text) as $line){
			if(preg_match('/^(@label[^$]+)$/', $line, $regs)){
				$annotations[] = $regs[1];
			} else if(preg_match('/^(@enum[^$]+)$/', $line, $regs)){
				$annotations[] = $regs[1];
			}
		}
		return $annotations;
	}

}
