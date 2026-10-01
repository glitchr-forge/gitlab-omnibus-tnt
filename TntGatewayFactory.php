<?php

namespace Omnibus\Tnt;

use Omnibus\Config;
use Omnibus\Exception\InvalidConfigException;
use Omnibus\GatewayFactory;
use Omnibus\Tnt\Action\RatingAction;
use Omnibus\Tnt\Action\ShippingAction;
use Omnibus\Tnt\Action\TrackingAction;
use Symfony\Component\HttpClient\HttpClient;

/**
 *   options:
 *     username: '%env(TNT_USERNAME)%'        # the ExpressConnect login
 *     password: '%env(TNT_PASSWORD)%'
 *     account_number: '%env(TNT_ACCOUNT)%'
 *     sender: { ... }                        # optional: the account's collection address when it is not the shipment's sender
 *     rates: [...]                           # optional: configured prices instead of ExpressConnect Pricing
 *
 * No pickup points, no cancellation: ExpressConnect offers neither; TNT Depots are
 * addressed by phone. ExpressConnect has no sandbox: a test account is one TNT marks as such.
 */
final class TntGatewayFactory extends GatewayFactory
{
    protected function populateConfig(Config $config): void
    {
        $config->defaults([
            'omnibus.factory_name' => 'tnt',
            'omnibus.factory_title' => 'TNT',
            'omnibus.required_options' => ['username', 'password', 'account_number'],
            'omnibus.api' => function (Config $c) {
                $http = $this->http ?? (class_exists(HttpClient::class) ? HttpClient::create() : throw new InvalidConfigException('The "tnt" gateway needs symfony/http-client.'));

                return new Api($http, (string) $c['username'], (string) $c['password'], (string) $c['account_number']);
            },
            'omnibus.action.rating' => static fn (Config $c) => $c->get('rates') ? null : new RatingAction(),
            'omnibus.action.shipping' => new ShippingAction(),
            'omnibus.action.tracking' => new TrackingAction(),
        ]);
    }
}
