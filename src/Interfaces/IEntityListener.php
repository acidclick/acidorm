<?php

namespace AcidORM\Interfaces;

use AcidORM\BaseObject;

/**
 * Posluchač ukládání a mazání entit přes fasády (insertUpdate*, delete*).
 * Typicky záznam historie změn, audit nebo invalidace cache v aplikaci.
 *
 * Pro entity, které nějaký posluchač podporuje, fasáda načte stav před a po uložení
 * a uložení i volání posluchačů proběhne v jedné databázové transakci.
 */
interface IEntityListener
{
	public function supports(BaseObject $object): bool;

	/**
	 * @param BaseObject|null $old stav před uložením (null u nové entity)
	 * @param BaseObject $new stav po uložení načtený z databáze
	 * @param int|null $userId uživatel, který změnu provedl
	 */
	public function afterSave(?BaseObject $old, BaseObject $new, ?int $userId): void;

	/**
	 * @param BaseObject $old stav před smazáním
	 */
	public function afterDelete(BaseObject $old, ?int $userId): void;
}
