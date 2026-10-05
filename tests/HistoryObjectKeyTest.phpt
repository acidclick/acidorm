<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use AcidORM\Engine;
use Model\Data\Note;
use Tester\Assert;

// IHistoryObject dostane při prvním uložení jedinečný key; jedinečnost se ověřuje v tabulce entity
// (a ne v tabulce History2, která nemusí existovat).
$db = new Dibi\Connection(['driver' => 'sqlite', 'database' => ':memory:']);
$db->query('CREATE TABLE [Note] ([id] INTEGER PRIMARY KEY AUTOINCREMENT, [text] TEXT, [key] TEXT)');
$engine = new Engine;
$engine->setDb($db);
$engine->setCacheProvider(new Nette\Caching\Cache(new Nette\Caching\Storages\MemoryStorage));
$engine->setParameters(['appDir' => __DIR__, 'databaseDriver' => 'sqlite']);
$engine->startup();
$facade = $engine->getFacade('Note');

$a = new Note; $a->text = 'a';
$facade->insertUpdateNote($a);
$b = new Note; $b->text = 'b';
$facade->insertUpdateNote($b);

Assert::type('string', $a->key);
Assert::notSame($a->key, $b->key);
Assert::same($a->key, $db->fetchSingle('SELECT [key] FROM [Note] WHERE [id] = %i', $a->id));

// existující klíč se při další úpravě nemění
$key = $a->key;
$a->text = 'a2';
$facade->insertUpdateNote($a);
Assert::same($key, $db->fetchSingle('SELECT [key] FROM [Note] WHERE [id] = %i', $a->id));
