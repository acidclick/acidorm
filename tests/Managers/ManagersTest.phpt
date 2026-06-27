<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use Tester\Assert;
use Tester\TestCase;
use Model\Mappers\UserMapper;
use Model\Persistors\UserPersistor;
use AcidORM\Managers\MapperManager;
use AcidORM\Managers\PersistorManager;
use Nette\Caching\Cache;
use Nette\Caching\Storages\MemoryStorage;

final class ManagersTest extends TestCase
{
	private MapperManager $mapperManager;
	private PersistorManager $persistorManager;
	private \Dibi\Connection $db;
	private Cache $cache;

	protected function setUp(): void
	{
		$this->db              = new \Dibi\Connection(['driver' => 'dummy']);
		$this->cache           = new Cache(new MemoryStorage());
		$this->mapperManager   = new MapperManager();
		$this->persistorManager = new PersistorManager();
		$this->persistorManager->mapperManager = $this->mapperManager;
		$this->persistorManager->db = $this->db;
	}

	// --- MapperManager ---

	public function testGetMapperReturnsUserMapper(): void
	{
		Assert::type(UserMapper::class, $this->mapperManager->getMapper('User'));
	}

	public function testGetMapperReturnsSameInstanceOnSecondCall(): void
	{
		$first  = $this->mapperManager->getMapper('User');
		$second = $this->mapperManager->getMapper('User');
		Assert::same($first, $second);
	}

	public function testGetMapperViaMagicProperty(): void
	{
		$mapper = $this->mapperManager->userMapper;
		Assert::type(UserMapper::class, $mapper);
	}

	// --- PersistorManager: properties ---

	public function testDbReturnsSameConnection(): void
	{
		Assert::same($this->db, $this->persistorManager->db);
	}

	public function testDbStoresConnection(): void
	{
		$newDb = new \Dibi\Connection(['driver' => 'dummy']);
		$this->persistorManager->db = $newDb;
		Assert::same($newDb, $this->persistorManager->db);
	}

	public function testMapperManagerReturnsSameManager(): void
	{
		Assert::same($this->mapperManager, $this->persistorManager->mapperManager);
	}

	public function testCacheNullByDefault(): void
	{
		$fresh = new PersistorManager();
		Assert::null($fresh->cache);
	}

	public function testCacheStoresCache(): void
	{
		$this->persistorManager->cache = $this->cache;
		Assert::same($this->cache, $this->persistorManager->cache);
	}

	// --- PersistorManager: getPersistor ---

	public function testGetPersistorReturnsUserPersistor(): void
	{
		Assert::type(UserPersistor::class, $this->persistorManager->getPersistor('User'));
	}

	public function testGetPersistorReturnsSameInstanceOnSecondCall(): void
	{
		$first  = $this->persistorManager->getPersistor('User');
		$second = $this->persistorManager->getPersistor('User');
		Assert::same($first, $second);
	}

	public function testGetPersistorWithoutCacheDoesNotThrow(): void
	{
		$manager = new PersistorManager();
		$manager->db = $this->db;
		$manager->mapperManager = $this->mapperManager;
		$persistor = $manager->getPersistor('User');
		Assert::type(UserPersistor::class, $persistor);
		Assert::null($persistor->cache);
	}

	public function testGetPersistorPropagatesCache(): void
	{
		$this->persistorManager->cache = $this->cache;
		$persistor = $this->persistorManager->getPersistor('User');
		Assert::same($this->cache, $persistor->cache);
	}

	public function testGetPersistorViaMagicProperty(): void
	{
		$persistor = $this->persistorManager->userPersistor;
		Assert::type(UserPersistor::class, $persistor);
	}
}

(new ManagersTest())->run();
