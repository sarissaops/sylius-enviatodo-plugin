<p align="center">
    <a href="https://sylius.com" target="_blank">
        <picture>
          <source media="(prefers-color-scheme: dark)" srcset="https://media.sylius.com/sylius-logo-800-dark.png">
          <source media="(prefers-color-scheme: light)" srcset="https://media.sylius.com/sylius-logo-800.png">
          <img alt="Sylius Logo." src="https://media.sylius.com/sylius-logo-800.png">
        </picture>
    </a>
</p>

<h1 align="center">Sylius Enviatodo Plugin</h1>

<p align="center">Enviatodo shipping for Sylius 2.x: live quotes at checkout, label purchase after payment, tracking, pickups and branch drop-off.</p>

## Requirements

- PHP `^8.2`
- Sylius `^2.1` (Symfony `^7.4`)
- `sarissaops/enviatodo-bundle ^0.1` (pulled in automatically)
- An Enviatodo API token (JWT), passed via environment — never committed

## Installation

1. Require the plugin:

    ```bash
    composer require sarissaops/sylius-enviatodo-plugin
    ```

2. Register both bundles in `config/bundles.php` (Flex usually does this for you):

    ```php
    SarissaOps\EnviatodoBundle\EnviatodoBundle::class => ['all' => true],
    SarissaOps\SyliusEnviatodoPlugin\SarissaOpsSyliusEnviatodoPlugin::class => ['all' => true],
    ```

3. Expose the token (the only secret; the only supported channel is the environment):

    ```bash
    ENVIATODO_TOKEN=your-jwt-here bin/console about
    ```

4. Add `config/packages/sylius_enviatodo.yaml`:

    ```yaml
    imports:
        - { resource: "@SarissaOpsSyliusEnviatodoPlugin/config/config.yaml" }
        # Optional: admin order-tracking panel (skip for headless — every JSON
        # controller and API Platform operation works without Twig).
        - { resource: "@SarissaOpsSyliusEnviatodoPlugin/config/twig_hooks/admin.yaml" }

    sarissa_ops_sylius_enviatodo:
        provider_map:
            # shipping-method code => Enviatodo carrier + service
            enviatodo_estafeta: { provider_id: '9', provider_service_id: '11' }
    ```

    Optional keys (all default to off/empty): `origin_address_id`, `default_package_id`
    (integers), `insurance: false`, `pickup_auto_schedule: false`.

5. Import the routes in `config/routes/sylius_enviatodo.yaml`:

    ```yaml
    sarissa_ops_sylius_enviatodo_admin:
        resource: "@SarissaOpsSyliusEnviatodoPlugin/config/routes/admin.yaml"

    sarissa_ops_sylius_enviatodo_shop:
        resource: "@SarissaOpsSyliusEnviatodoPlugin/config/routes/shop.yaml"
    ```

6. Run the plugin migrations, then load data as usual:

    ```bash
    vendor/bin/console doctrine:migrations:migrate -n
    vendor/bin/console sylius:fixtures:load -n   # dev/test only
    ```

7. In the Sylius admin, create a shipping method per carrier entry in your
   `provider_map` with calculator **"Enviatodo (live rates)"**. Unmapped codes are
   hidden automatically; unquotable destinations are gated by zip-code lookup.

## What you get

- **Checkout quotes** — live `rates_client` rates per Enviatodo-backed method; the
  chosen quote `uuid` is held (draft) and spent once, after payment.
- **Label purchase** — `OrderPaidListener` checks balance, calls `create_order`,
  persists tracking/guide/amounts. Print single labels or bulk-export binaries
  (per-file `invalid_files[]` retry).
- **Tracking & status sync** — admin order panel + `SyncEnviatodoStatusesCommand`;
  cancel flows to a *pending* (+7 days refund) state.
- **Fulfilment, three paths** — scheduled carrier pickup (`add_pickup`/cancel),
  merchant branch drop-off (local handover state, no API call), poll-based delivery
  status (`41→42→44` happy path, `59` in-pickup, `68/69` return/incident).
- **Master data** — origin address book, parcel templates, carrier catalog, SAT-shaped
  dropdowns, checkout defaults.
- **Headless API** — `POST /api/v2/shop/orders/{token}/shipments/{id}/live-rates`,
  `POST /api/v2/admin/shipments/bulk-labels`,
  `POST /api/v2/admin/shipments/{id}/cancel` (synchronous, no Messenger).

All Enviatodo traffic goes through the autowired `SarissaOps\Enviatodo\Enviatodo`
facade from `sarissaops/enviatodo-bundle`. See `enviatodo/docs/Enviatodo Api V2.json`
(upstream collection) for the raw API contract.

## Theming and headless usage

The admin order-tracking panel ships as the Twig fragment
`@SarissaOpsSyliusEnviatodoPlugin/admin/order/tracking.html.twig`, attached via
the `sylius_admin.order.show.content.sections#right` hook (import
`config/twig_hooks/admin.yaml` to enable it). To override it in a theme, copy it
to `<theme>/templates/bundles/SarissaOpsSyliusEnviatodoPlugin/admin/order/tracking.html.twig`
— note the literal `Plugin` suffix, no `Bundle` appended. For headless
deployments, skip the `twig_hooks` import entirely: all JSON controllers and
API Platform operations work without Twig installed.

## Development

```bash
composer validate
vendor/bin/phpunit --testsuite=unit
vendor/bin/phpstan analyse -c phpstan.neon -l max src/
vendor/bin/ecs check
vendor/bin/behat --strict --tags="~@javascript&&~@mink:chromedriver&&~@enviatodo_live"
```

See `plan.md` (§5 architecture, §8 gates, §13 live lessons) for the build record.
