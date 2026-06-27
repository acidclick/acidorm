<?php

namespace AcidORM\Generators;

interface IDatabaseFirst
{
	function createFromTable($table);

	function createAll();
}