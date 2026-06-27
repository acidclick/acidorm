<?php

namespace AcidORM\Utils;

class AnnotationParser
{
	private static array $cache = [];

	public static function hasAnnotation(\ReflectionClass|\ReflectionProperty $reflector, string $name): bool
	{
		return isset(self::getAll($reflector)[$name]);
	}

	public static function getAnnotation(\ReflectionClass|\ReflectionProperty $reflector, string $name): mixed
	{
		$all = self::getAll($reflector);
		return isset($all[$name]) ? end($all[$name]) : null;
	}

	private static function getAll(\ReflectionClass|\ReflectionProperty $reflector): array
	{
		$key = self::cacheKey($reflector);
		if (!array_key_exists($key, self::$cache)) {
			self::$cache[$key] = self::parse($reflector->getDocComment() ?: '');
		}
		return self::$cache[$key];
	}

	private static function cacheKey(\ReflectionClass|\ReflectionProperty $reflector): string
	{
		if ($reflector instanceof \ReflectionClass) {
			return 'c:' . $reflector->getName();
		}
		return 'p:' . $reflector->getDeclaringClass()->getName() . '::$' . $reflector->getName();
	}

	private static function parse(string $doc): array
	{
		$res = [];
		if ($doc === '') return $res;

		$doc = preg_replace('#^\s*\*\s?#ms', '', trim($doc, '/*'));

		foreach (preg_split('/\r\n?|\n/', $doc) as $line) {
			$line = trim($line, " \t*");
			if (!preg_match('/^@([_a-zA-Z][_a-zA-Z0-9\-\\\\]*)(.*)$/s', $line, $m)) {
				continue;
			}

			$name  = $m[1];
			$value = ltrim($m[2]);

			if ($value !== '' && $value[0] === '(') {
				$inner  = rtrim(substr($value, 1), " \t)");
				$parsed = self::parseParams($inner);
				if (count($parsed) === 1 && array_key_exists(0, $parsed)) {
					$res[$name][] = $parsed[0];
				} else {
					$res[$name][] = new AnnotationValue($parsed);
				}
			} elseif ($value === '') {
				$res[$name][] = true;
			} else {
				$res[$name][] = self::coerce($value);
			}
		}

		return $res;
	}

	private static function parseParams(string $raw): array
	{
		$data = [];
		foreach (preg_split('/\s*,\s*/', trim($raw)) as $pair) {
			$pair = trim($pair);
			if ($pair === '') continue;
			if (preg_match('/^([_a-zA-Z]\w*)\s*=\s*(.+)$/s', $pair, $m)) {
				$data[$m[1]] = self::coerce(trim($m[2]));
			} else {
				$data[] = self::coerce($pair);
			}
		}
		return $data;
	}

	private static function coerce(string $value): mixed
	{
		if ($value === '') return true;
		if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'")) {
			return stripslashes(substr($value, 1, -1));
		}
		if (is_numeric($value)) return $value + 0;
		return match (strtolower($value)) {
			'true'  => true,
			'false' => false,
			'null'  => null,
			default => $value,
		};
	}
}
