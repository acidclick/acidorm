<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use AcidORM\BaseObject;
use AcidORM\Engine;
use AcidORM\Interfaces\IEntityListener;
use Model\Data\Tag;
use Tester\Assert;

class RecordingListener implements IEntityListener
{
	public array $events = [];
	public bool $fail = false;

	public function supports(BaseObject $object): bool
	{
		return $object instanceof Tag;
	}

	public function afterSave(?BaseObject $old, BaseObject $new, ?int $userId): void
	{
		$this->events[] = ['save', $old?->name, $new->name, $userId];
		if ($this->fail) throw new RuntimeException('listener failed');
	}

	public function afterDelete(BaseObject $old, ?int $userId): void
	{
		$this->events[] = ['delete', $old->name, null, $userId];
	}
}

$db = new Dibi\Connection(['driver' => 'sqlite', 'database' => ':memory:']);
$db->query('CREATE TABLE [Tag] ([id] INTEGER PRIMARY KEY AUTOINCREMENT, [name] TEXT)');
$engine = new Engine;
$engine->setDb($db);
$engine->setCacheProvider(new Nette\Caching\Cache(new Nette\Caching\Storages\MemoryStorage));
$engine->setParameters(['appDir' => __DIR__, 'databaseDriver' => 'sqlite']);
$engine->startup();
$listener = new RecordingListener;
$engine->addEntityListener($listener);
$facade = $engine->getFacade('Tag');

// nová entita: old = null
$tag = new Tag;
$tag->name = 'první';
$facade->insertUpdateTag($tag, 7);
Assert::same([['save', null, 'první', 7]], $listener->events);

// úprava: old = stav z databáze před uložením
$tag->name = 'druhá';
$facade->insertUpdateTag($tag, 7);
Assert::same(['save', 'první', 'druhá', 7], $listener->events[1]);

// výjimka v posluchači vrátí i uložení entity (jedna transakce)
$listener->fail = true;
$tag->name = 'třetí';
Assert::exception(fn() => $facade->insertUpdateTag($tag, 7), RuntimeException::class);
Assert::same('druhá', $db->fetchSingle('SELECT [name] FROM [Tag] WHERE [id] = %i', $tag->id));
$listener->fail = false;

// smazání bez uživatele: posluchač dostane poslední stav a userId null
$t2 = new Tag; $t2->name = "bez uživatele"; $facade->insertUpdateTag($t2, 7);
$facade->deleteTag($t2->id);
Assert::same(["delete", "bez uživatele", null, null], end($listener->events));

// smazání: posluchač dostane poslední stav
$facade->deleteTag($tag->id, 8);
Assert::same(['delete', 'druhá', null, 8], end($listener->events));
Assert::same(0, (int) $db->fetchSingle('SELECT COUNT(*) FROM [Tag]'));

// userId 0 = aktuální uživatel z provideru, null zůstává null (systém)
$engine->setDefaultUserIdProvider(fn() => 42);
$t3 = new Tag; $t3->name = 'provider';
$facade->insertUpdateTag($t3, 0);
Assert::same(['save', null, 'provider', 42], end($listener->events));
$t3->name = 'systém';
$facade->insertUpdateTag($t3);
Assert::same(['save', 'provider', 'systém', null], end($listener->events));
