<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Tester\Assert;
use Tester\TestCase;
use Model\Data\User;
use Model\Mappers\UserMapper;
use Model\Persistors\UserPersistor;
use AcidORM\Managers\MapperManager;

final class BasePersistorTest extends TestCase
{
	private UserPersistor $persistor;
	private \Dibi\Connection $db;
	private MapperManager $mapperManager;

	protected function setUp(): void
	{
		$this->db             = new \Dibi\Connection(['driver' => 'dummy']);
		$this->mapperManager  = new MapperManager();
		$this->persistor      = new UserPersistor($this->db, $this->mapperManager);
	}

	// --- constructor / properties ---

	public function testTableReturnsEntityName(): void
	{
		Assert::same('User', $this->persistor->table);
	}

	public function testObjectReturnsUserInstance(): void
	{
		Assert::type(User::class, $this->persistor->object);
	}

	public function testMapperReturnsUserMapper(): void
	{
		Assert::type(UserMapper::class, $this->persistor->mapper);
	}

	public function testDbReturnsSameConnection(): void
	{
		Assert::same($this->db, $this->persistor->db);
	}

	public function testMapperManagerReturnsSameManager(): void
	{
		Assert::same($this->mapperManager, $this->persistor->mapperManager);
	}

	// --- dotazy s DummyDriver (nevracejí žádná data) ---

	public function testGetByIdReturnsNullWhenNoRows(): void
	{
		Assert::null($this->persistor->getById(1));
	}

	public function testGetByPropertyReturnsNullWhenNoRows(): void
	{
		Assert::null($this->persistor->getByProperty('name', 'Alice'));
	}

	// --- insertUpdate (DummyDriver: žádný skutečný INSERT) ---

	public function testInsertUpdateWithNullIdDoesNotThrow(): void
	{
		$user = new User();
		$user->name = 'Test';
		$this->persistor->insertUpdate($user);
		Assert::true(true);
	}

	public function testInsertUpdateWithNullIdKeepsIdNull(): void
	{
		$user = new User();
		$user->name = 'Test';
		$this->persistor->insertUpdate($user);
		Assert::null($user->id);
	}

	public function testInsertUpdateWithExistingIdDoesNotThrow(): void
	{
		$user = new User();
		$user->id   = 99;
		$user->name = 'Existing';
		$this->persistor->insertUpdate($user);
		Assert::same(99, $user->id);
	}

	// --- delete ---

	public function testDeleteDoesNotThrow(): void
	{
		$this->persistor->delete(1);
		Assert::true(true);
	}

	// --- db property ---

	public function testDbStoresNewConnection(): void
	{
		$newDb = new \Dibi\Connection(['driver' => 'dummy']);
		$this->persistor->db = $newDb;
		Assert::same($newDb, $this->persistor->db);
	}

	// --- getDependencies ---

	public function testGetDependenciesForUserReturnsEmptyArray(): void
	{
		Assert::equal([], $this->persistor->getDependencies());
	}

	// --- createQuery ---

	public function testCreateQueryReturnsDibiFluent(): void
	{
		$q = $this->persistor->createQuery();
		Assert::type(\Dibi\Fluent::class, $q);
	}
}

(new BasePersistorTest())->run();
