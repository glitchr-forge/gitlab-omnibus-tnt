# omnibus/tnt

TNT for [glitchr/omnibus](https://github.com/glitchr-studio/omnibus): prices (ExpressConnect
Pricing), consignments with their labels (ExpressConnect Shipping) and tracking (ExpressConnect
Tracking) - the XML services on express.tnt.com, still answered for existing TNT accounts. New
accounts get FedEx's APIs since TNT joined FedEx: see omnibus/fedex.

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

License: LGPL-3.0-or-later.
