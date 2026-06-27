<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Tester\Assert;
use Tester\TestCase;
use AcidORM\Engine;
use AcidORM\Managers\FacadeManager;
use AcidORM\Managers\GridManager;
use AcidORM\Managers\MapperManager;
use AcidORM\Managers\PersistorManager;
use Nette\Caching\Cache;
use Nette\Caching\Storages\MemoryStorage;

final class EngineTest extends TestCase
{
	private Engine $engine;
	private \Dibi\Connection $db;
	private Cache $cache;

	protected function setUp(): void
	{
		$this->engine = new Engine();
		$this->db = new \Dibi\Connection(['driver' => 'dummy']);
		$this->cache = new Cache(new MemoryStorage());
	}

	// --- db ---

	public function testDbNullInitially(): void
	{
		Assert::null($this->engine->db);
	}

	public function testDbStoresConnection(): void
	{
		$this->engine->db = $this->db;
		Assert::same($this->db, $this->engine->db);
	}

	// --- cacheProvider ---

	public function testCacheProviderNullInitially(): void
	{
		Assert::null($this->engine->cacheProvider);
	}

	public function testCacheProviderStoresCache(): void
	{
		$this->engine->cacheProvider = $this->cache;
		Assert::same($this->cache, $this->engine->cacheProvider);
	}

	// --- parameters ---

	public function testParametersEmptyArrayInitially(): void
	{
		Assert::equal([], $this->engine->parameters);
	}

	public function testParametersStoresAll(): void
	{
		$params = ['databaseDriver' => 'mysqli', 'appDir' => '/app'];
		$this->engine->parameters = $params;
		Assert::same($params, $this->engine->parameters);
	}

	// --- startup() ---

	public function testStartupCreatesMapperManager(): void
	{
		$this->startupEngine();
		Assert::type(MapperManager::class, $this->engine->mapperManager);
	}

	public function testStartupCreatesPersistorManager(): void
	{
		$this->startupEngine();
		Assert::type(PersistorManager::class, $this->engine->persistorManager);
	}

	public function testStartupCreatesFacadeManager(): void
	{
		$this->startupEngine();
		Assert::type(FacadeManager::class, $this->engine->facadeManager);
	}

	public function testStartupCreatesGridManager(): void
	{
		$this->startupEngine();
		Assert::type(GridManager::class, $this->engine->gridManager);
	}

	// --- vazby mezi managery ---

	public function testPersistorManagerReceivesDb(): void
	{
		$this->startupEngine();
		Assert::same($this->db, $this->engine->persistorManager->db);
	}

	public function testPersistorManagerReceivesCache(): void
	{
		$this->startupEngine();
		Assert::same($this->cache, $this->engine->persistorManager->cache);
	}

	public function testPersistorManagerReceivesMapperManager(): void
	{
		$this->startupEngine();
		Assert::same(
			$this->engine->mapperManager,
			$this->engine->persistorManager->mapperManager
		);
	}

	public function testFacadeManagerReceivesCache(): void
	{
		$this->startupEngine();
		Assert::same($this->cache, $this->engine->facadeManager->cache);
	}

	public function testFacadeManagerReceivesParameters(): void
	{
		$params = ['key' => 'value'];
		$this->engine->parameters = $params;
		$this->startupEngine();
		Assert::same($params, $this->engine->facadeManager->parameters);
	}

	public function testFacadeManagerReceivesPersistorManager(): void
	{
		$this->startupEngine();
		Assert::same(
			$this->engine->persistorManager,
			$this->engine->facadeManager->persistorManager
		);
	}

	public function testFacadeManagerReceivesMapperManager(): void
	{
		$this->startupEngine();
		Assert::same(
			$this->engine->mapperManager,
			$this->engine->facadeManager->mapperManager
		);
	}

	// --- facadeManager před startup ---

	public function testFacadeManagerNullBeforeStartup(): void
	{
		Assert::null($this->engine->facadeManager);
	}

	// --- magic __get ---

	public function testMagicGetThrowsForUnknownProperty(): void
	{
		$this->startupEngine();
		Assert::exception(
			function () { $x = $this->engine->unknownProperty; },
			\Exception::class
		);
	}

	// --- getFacadeManager (backward compat) ---

	public function testGetFacadeManagerReturnsNullBeforeStartup(): void
	{
		Assert::null($this->engine->getFacadeManager());
	}

	public function testGetPersistorManagerAfterStartup(): void
	{
		$this->startupEngine();
		Assert::type(PersistorManager::class, $this->engine->getPersistorManager());
	}

	public function testGetMapperManagerAfterStartup(): void
	{
		$this->startupEngine();
		Assert::type(MapperManager::class, $this->engine->getMapperManager());
	}

	// --- createDirStructure ---

	public function testCreateDirStructureCreatesAllDirectories(): void
	{
		$tmpDir = sys_get_temp_dir() . '/acidorm_test_' . uniqid();
		mkdir($tmpDir);

		$this->engine->parameters = ['appDir' => $tmpDir];
		$this->engine->createDirStructure();

		$expected = ['Data', 'Enums', 'Interfaces', 'Mappers', 'Persistors', 'Facades', 'Grids', 'Forms'];
		foreach ($expected as $dir) {
			Assert::true(is_dir($tmpDir . '/model/' . $dir), "Chybí adresář model/$dir");
		}

		$this->removeDir($tmpDir);
	}

	public function testCreateDirStructureIsIdempotent(): void
	{
		$tmpDir = sys_get_temp_dir() . '/acidorm_test_' . uniqid();
		mkdir($tmpDir);

		$this->engine->parameters = ['appDir' => $tmpDir];
		$this->engine->createDirStructure();
		$this->engine->createDirStructure();

		Assert::true(is_dir($tmpDir . '/model/Data'));

		$this->removeDir($tmpDir);
	}

	// --- helpers ---

	private function startupEngine(): void
	{
		$this->engine->db = $this->db;
		$this->engine->cacheProvider = $this->cache;
		$this->engine->startup();
	}

	private function removeDir(string $dir): void
	{
		foreach (glob($dir . '/*') ?: [] as $entry) {
			is_dir($entry) ? $this->removeDir($entry) : unlink($entry);
		}
		rmdir($dir);
	}
}

(new EngineTest())->run();
