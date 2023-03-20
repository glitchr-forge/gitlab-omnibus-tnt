<?php

namespace Omnibus\TNT;

use Omnibus\Core\GatewayFactory;

class TNTGatewayFactory extends GatewayFactory
{
    protected function populateConfig(ArrayObject $config)
    {
        $config->defaults([
            'omnibus.factory_name' => 'tnt',
            'omnibus.factory_title' => 'TNT'
        ]);
    }
}