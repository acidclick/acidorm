<?php

namespace AcidORM;

/** @deprecated Use AcidORM\Engine instead. */
class AcidORM extends Engine
{
    public function __construct()
    {
        trigger_error(
            'AcidORM\AcidORM is deprecated, use AcidORM\Engine instead.',
            \E_USER_DEPRECATED,
        );
        parent::__construct();
    }
}
