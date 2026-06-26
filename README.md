# AcidORM

## config.neon
```php

	services:		

		acidORM:
			class: AcidORM\AcidORM
			setup:
				- setDb(@dibi.connection)
				- setCacheProvider(@cacheProvider)
				- setParameters(['appDir' = %appDir%])
				- startup()	
```