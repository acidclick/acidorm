<?php

namespace acidorm\Traits;

trait HistoryObject {

	public $key;

	public static function generateUniqueId()
	{
		return uniqid();
	}

	public static function getTableName()
	{
		return 'History2';
	}
}