# omnibus/tnt

TNT for [glitchr/omnibus](https://github.com/glitchr-studio/omnibus): prices (ExpressConnect
Pricing), consignments with their labels (ExpressConnect Shipping) and tracking (ExpressConnect
Tracking) - the XML services on express.tnt.com, still answered for existing TNT accounts. New
accounts get FedEx's APIs since TNT joined FedEx: see omnibus/fedex.

```php
$gateway = (new TntGatewayFactory($http))->create($options);   // $http: the application's HTTP client - none given, the factory makes its own; the options below
```

No framework needed: the package requires `glitchr/omnibus` and `symfony/http-client`. In a
Symfony application, the same through the bundle's configuration:

```yaml
omnibus:
    gateways:
        tnt:
            factory: tnt
            options:
                username: '%env(TNT_USERNAME)%'
                password: '%env(TNT_PASSWORD)%'
                account_number: '%env(TNT_ACCOUNT)%'
                rates: [...]        # optional: configured prices instead of ExpressConnect Pricing
```

The service is TNT's product code (15N Express, 09N/10N/12N timed Express, 48N Economy Express;
the D variants for documents). Shipment options: `documents`, `description`, `instructions`,
`option` (a TNT option code), `sender_vat`. The label comes back as TNT's XML, rendered with TNT's
stylesheet. No pickup points, no cancellation: ExpressConnect offers neither.

Credentials: an ExpressConnect login (username, password) and your TNT account number, from your
TNT account manager; there is no sandbox, TNT flags a test account.

Built from TNT's published ExpressConnect documentation and tested on recorded answers; not yet
run against the service.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by TNT (Federal Express Corporation). It reaches their service on behalf of whoever runs
it, and it does not redistribute their data beyond the short excerpts recorded as test fixtures. Its
MIT licence covers this package's code only and grants no right to the service or to its data.

Whoever installs and runs this package is responsible for complying with TNT's terms ([tnt.com terms
and conditions](https://www.tnt.com/express/en_gb/site/terms-conditions.html)), for obtaining
whatever they require (an account, a key, a licence or subscription, an attribution or a citation, a
rate limit to respect), and for any use made of what it returns. The authors publish it free of
charge, make no commercial use of it, and accept no liability for how it is used (see
[LICENSE](LICENSE)).

License: MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
