<?php

namespace acidorm\Generators;

interface IDatabaseFirst
{
	function createFromTable($table);

	function createAll();
}