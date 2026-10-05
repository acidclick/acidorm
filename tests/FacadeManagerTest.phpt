<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Tester\Assert;
use Tester\TestCase;
use AcidORM\DB\Result;
use AcidORM\Managers\FacadeManager;
use AcidORM\Managers\MapperManager;
use AcidORM\Managers\PersistorManager;
use Model\Facades\UserFacade;
use Model\Persistors\UserPersistor;
use Nette\Caching\Cache;
use Nette\Caching\Storages\MemoryStorage;

final class FacadeManagerTest extends TestCase
{
	private FacadeManager $facadeManager;

	protected function setUp(): void
	{
		$mapperManager = new MapperManager();
		$persistorManager = new PersistorManager();
		$persistorManager->setMapperManager($mapperManager);
		$persistorManager->setDb(new \Dibi\Connection(['driver' => 'dummy']));

		$this->facadeManager = new FacadeManager();
		$this->facadeManager->setPersistorManager($persistorManager);
		$this->facadeManager->setMapperManager($mapperManager);
		$this->facadeManager->setCache(new Cache(new MemoryStorage()));
	}

	public function testGetFacadeInjectsDependencies(): void
	{
		$facade = $this->facadeManager->getFacade('User');
		Assert::type(UserFacade::class, $facade);
		Assert::type(UserPersistor::class, $facade->persistor);
		Assert::type(Cache::class, $facade->cache);
	}

	public function testGetFacadeViaMagicProperty(): void
	{
		Assert::same($this->facadeManager->getFacade('User'), $this->facadeManager->userFacade);
	}

	public function testResultAcceptsDibiRow(): void
	{
		$result = new Result(new \Dibi\Row(['user_id' => 5, 'user_name' => 'Jan', 'other_id' => 1]));
		Assert::same(['id' => 5, 'name' => 'Jan'], $result->getAliasData('user'));
	}
}

(new FacadeManagerTest())->run();
