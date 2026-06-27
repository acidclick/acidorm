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

	// --- getDb / setDb ---

	public function testGetDbReturnsNullInitially(): void
	{
		Assert::null($this->engine->getDb());
	}

	public function testSetDbStoresConnection(): void
	{
		$this->engine->setDb($this->db);
		Assert::same($this->db, $this->engine->getDb());
	}

	// --- getCacheProvider / setCacheProvider ---

	public function testGetCacheProviderReturnsNullInitially(): void
	{
		Assert::null($this->engine->getCacheProvider());
	}

	public function testSetCacheProviderStoresCache(): void
	{
		$this->engine->setCacheProvider($this->cache);
		Assert::same($this->cache, $this->engine->getCacheProvider());
	}

	// --- getParameters / setParameters ---

	public function testGetParametersReturnsEmptyArrayInitially(): void
	{
		Assert::equal([], $this->engine->getParameters());
	}

	public function testSetParametersStoresAll(): void
	{
		$params = ['databaseDriver' => 'mysqli', 'appDir' => '/app'];
		$this->engine->setParameters($params);
		Assert::same($params, $this->engine->getParameters());
	}

	// --- startup() ---

	public function testStartupCreatesMapperManager(): void
	{
		$this->startupEngine();
		Assert::type(MapperManager::class, $this->engine->getMapperManager());
	}

	public function testStartupCreatesPersistorManager(): void
	{
		$this->startupEngine();
		Assert::type(PersistorManager::class, $this->engine->getPersistorManager());
	}

	public function testStartupCreatesFacadeManager(): void
	{
		$this->startupEngine();
		Assert::type(FacadeManager::class, $this->engine->getFacadeManager());
	}

	public function testStartupCreatesGridManager(): void
	{
		$this->startupEngine();
		Assert::type(GridManager::class, $this->engine->getGridManager());
	}

	// --- vazby mezi managery ---

	public function testPersistorManagerReceivesDb(): void
	{
		$this->startupEngine();
		Assert::same($this->db, $this->engine->getPersistorManager()->getDb());
	}

	public function testPersistorManagerReceivesCache(): void
	{
		$this->startupEngine();
		Assert::same($this->cache, $this->engine->getPersistorManager()->getCache());
	}

	public function testPersistorManagerReceivesMapperManager(): void
	{
		$this->startupEngine();
		Assert::same(
			$this->engine->getMapperManager(),
			$this->engine->getPersistorManager()->getMapperManager()
		);
	}

	public function testFacadeManagerReceivesCache(): void
	{
		$this->startupEngine();
		Assert::same($this->cache, $this->engine->getFacadeManager()->getCache());
	}

	public function testFacadeManagerReceivesParameters(): void
	{
		$params = ['key' => 'value'];
		$this->engine->setParameters($params);
		$this->startupEngine();
		Assert::same($params, $this->engine->getFacadeManager()->getParameters());
	}

	public function testFacadeManagerReceivesPersistorManager(): void
	{
		$this->startupEngine();
		Assert::same(
			$this->engine->getPersistorManager(),
			$this->engine->getFacadeManager()->getPersistorManager()
		);
	}

	public function testFacadeManagerReceivesMapperManager(): void
	{
		$this->startupEngine();
		Assert::same(
			$this->engine->getMapperManager(),
			$this->engine->getFacadeManager()->getMapperManager()
		);
	}

	// --- getFacadeManager před startup ---

	public function testGetFacadeManagerReturnsNullBeforeStartup(): void
	{
		Assert::null($this->engine->getFacadeManager());
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

	// --- createDirStructure ---

	public function testCreateDirStructureCreatesAllDirectories(): void
	{
		$tmpDir = sys_get_temp_dir() . '/acidorm_test_' . uniqid();
		mkdir($tmpDir);

		$this->engine->setParameters(['appDir' => $tmpDir]);
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

		$this->engine->setParameters(['appDir' => $tmpDir]);
		$this->engine->createDirStructure();
		$this->engine->createDirStructure(); // druhé volání nesmí hodit chybu

		Assert::true(is_dir($tmpDir . '/model/Data'));

		$this->removeDir($tmpDir);
	}

	// --- helpers ---

	private function startupEngine(): void
	{
		$this->engine->setDb($this->db);
		$this->engine->setCacheProvider($this->cache);
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
