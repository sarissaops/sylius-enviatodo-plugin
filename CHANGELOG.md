# Changelog

All notable changes to `sarissaops/sylius-enviatodo-plugin` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/);
versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] — Initial release

First functional release: Enviatodo shipping for Sylius 2.x (PHP `^8.2`,
Sylius `^2.1`, Symfony `^7.4`), on top of `sarissaops/enviatodo-bundle ^0.1`.

### Added

- Live checkout quotes: `EnviatodoCalculator` (framework-free, in
  `src/Component/Shipping`) + Sylius adapter tagged `sylius.shipping_calculator
  calculator="enviatodo"`; eligibility checker with zip-code gate; CSRF-guarded
  shop quote endpoint (`POST /enviatodo/ajax/quote`); `enviatodo_select_shipping`
  validation group via compiler pass.
- Label purchase: two-phase draft (quote `uuid` held at checkout) → complete on
  `OrderPaidListener`; balance guardrail before `create_order`; single
  `EnviatodoShipment` entity per shipment.
- Labels & fulfilment: single/bulk label download with per-file `invalid_files[]`
  retry; poll-based tracking sync (`SyncEnviatodoStatusesCommand`); async cancel
  with pending-refund state; scheduled dock pickups (queue/book/cancel);
  merchant branch drop-off as a local handover state (no API call).
- Master data: origin address book, parcel templates, carrier catalog,
  SAT-shaped dropdowns, checkout defaults (13 admin + 1 shop MVC routes).
- Headless API (synchronous API Platform 4 `Provider`/`Processor`, no Messenger):
  shop live-rates, admin bulk-labels, admin shipment-cancel.
- Admin order-tracking Twig fragment on
  `sylius_admin.order.show.content.sections#right` (opt-in; headless installs
  skip Twig entirely).
- Doctrine migrations in MySQL + PostgreSQL pairs.

### Non-goals (explicitly deferred, see `plan.md` §10)

Third-party B2B-suite integrations, company/branch hierarchies, buyer orgs,
quote negotiation, credit limits, `OrderNormalizer`, company menu gating, order
grid bulk actions, settings/logs entities, checkout form extension, shop JS
bundle. Extension is possible through the existing interface-typed seams; it is
not built until a target suite is actually present.

### Notes

- Test matrix at release: PHPUnit `unit` 177 tests / 387 assertions green,
  `functional` 9 / 65 green, PHPStan level max clean, ECS clean, Behat non-JS
  5 scenarios / 81 steps green (`@enviatodo_live` excluded — it hits the real
  sandbox). E2E/Panther specs require a running webserver + Chrome and are not
  part of the default gate.
- `sylius:debug:twig-hooks` is unavailable in the vendored Sylius 2.2.9, and
  `sylius:fixtures:load`-based database reset requires DB privileges this
  machine lacks — hook wiring is instead covered by the admin Behat scenario
  and container wiring tests.
