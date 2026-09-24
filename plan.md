# Plan — `sarissaops/sylius-enviatodo-plugin` (build inside `SyliusEnviatodoPlugin/`)

> Status 2026-09-23, rev 5 — COMPLETE. Steps 0–7 built, gated green, and
> live-verified against `sylius-dev-env` on `:8001` (later moved to `:8000`;
> re-verify on publish). Commits `2e3f0ff` → `3868055` in
> `SyliusEnviatodoPlugin/` (local only, unpushed, unreleased). Final gates:
> phpunit 108 tests / 284 assertions green; Behat 5 scenarios / 81 steps green
> (`@enviatodo_live` suite defined, excluded by default); PHPStan level max
> clean; ECS clean. Live proof: 16 enviatodo routes, calculator in
> `sylius.shipping_calculators`, validation group appended, tracking hook
> enabled, CSRF guards return 403, OpenAPI lists both plugin ops, shop/admin
> unregressed, all 4 migrations applied on MariaDB with snake_case columns
> matching 1:1. Parent: `../PROJECT_MASTER_PLAN.md` (§§1–7).
> Locked: style = **ECS only** (`sylius-labs/coding-standard`, never standalone
> php-cs-fixer); vendor = **`sarissaops`**; git = **init here, retire the old
> empty `sylius-enviatodo-plugin/`**. Methodology: BDD (Behat) outside, TDD
> (PHPUnit) inside, PHPStan level max, ECS. Scope: **vanilla Sylius 2.x only** —
> no Sylius 1.x paths, no third-party B2B-suite integrations (deferred until a
> suite is actually detected; §10). Fulfilment covers **three paths**: scheduled
> carrier pickup, merchant **branch drop-off** (Mexico-common, no pickup booked —
> local handover state, no API call), and poll-based delivery status. Grounded in
> `../enviatodo/docs/Enviatodo Api V2.json` (collection v2.1.0, 11 folders /
> 28 requests, parsed request-by-request; header census `x-api-key` 28/28,
> `x-enviatodo-app` 28/28, `x-enviatodo-client` exactly 1) and 16 read-only
> explore subagents over every reference repo (second wave: TwigHooks mechanics,
> Standard wiring, mailgun test reality, Behat harness, Furgonetka inventory).

## 1. Goal, scope, non-goals

Ship a Sylius 2.x-only plugin (`type: sylius-plugin`) wiring the Enviatodo
Shipping API (V2) into a store's real operations: live `rates_client` quotes at
checkout through a Sylius shipping calculator; label purchase after payment by
holding the quote `uuid`; label download/bulk export; poll-based tracking and
status sync; async-cancel with pending-refund state; scheduled dock pickups;
origin/package master data; balance guardrail; per-clerk audit. B2B-shaped ops
(multi-branch origins, declared values, SAT-shaped product codes, insurance,
bulk batches, handover contacts) are served through **vanilla data only**
(`Address.company`, `CustomerGroup`, our own settings/logs) — no B2B-suite
classes are referenced anywhere.

Non-goals: Sylius 1.x (`sylius_ui` events, APIP-2 `DataPersister/Provider`,
`MAJOR_VERSION` branches — never); iframe admin; raw HTTP clients in the plugin
(all traffic via `sarissaops/enviatodo-bundle` → `enviatodo-php`); admin order
creation (Sylius has none: `AdminBundle/.../routing/order.yml` is `index`-only,
`OrderType` edits addresses only — B2B orders still originate in shop checkout,
staff do fulfilment); bulk grid actions in core (neither order nor shipment grid
defines `bulk:` — we add our own); company fields on `Customer` (does not exist
upstream — company lives on `Address`).

## 2. Starting state (pre-Step 0, preserved for history)

Everything in this section described the scaffold before the build; §9 records
what was actually built, green means verified.

- Renamed skeleton. `src/SarissaOpsSyliusEnviatodoPlugin.php:11` (`Bundle` +
  `SyliusPluginTrait`, `getPath() = dirname(__DIR__)`); renamed DI extension
  (`extends AbstractResourceExtension implements PrependExtensionInterface`,
  `use PrependDoctrineMigrationsTrait`; `load()` only loads `services.xml`);
  empty `Configuration` (root `sarissa_ops_sylius_enviatodo`); generic
  `DoctrineMigrations` namespace (collides — rename to plugin-specific).
- `vendor/` installed: `sylius/sylius v2.2.9`, `sylius/test-application
  v2.2.0-ALPHA.1`, `phpunit 10.5.64`, `phpstan 1.12.34`,
  `sylius-labs/coding-standard v4.5.1`. Zero `sarissaops/*`, zero
  `friendsofphp/php-cs-fixer` (ECS covers fixers internally).
- `composer.json`: name still `sarissa-ops/sylius-enviatodo-plugin`; require =
  `php ^8.2` + `sylius/sylius ^2.0` only (`enviatodo-bundle` absent); phpstan
  `^1.12` (→ `^2.2`); `test-appication` keyword typo.
- Missing root configs: `phpunit.xml.dist`, `behat.yml.dist`, `phpstan.neon`,
  `ecs.php`, `Makefile` (binaries vendored but unconfigured).
- Demo residue: `GreetingController`, greeting shop routes, `twig_hooks/shop.yaml`
  greetings, `templates/*/greeting/*`, `WelcomeContext` + pages + `suites.yml`
  greeting suite, `features/*greeting*` (keep `running_a_sylius_feature`
  smoke). `tests/{Unit,Integration,Functional}/`, `src/Migrations/`,
  `config/services/` empty. `config/routes/admin.yaml` placeholder-only.
- TestApplication wiring present: `bundles.php` registers the plugin,
  `config.yaml` imports plugin config + `services_test.php`, `routes.yaml`
  mounts plugin routes, `.env`/`.env.test` hold MySQL `DATABASE_URL` +
  `BEHAT_BASE_URL=http://127.0.0.1:8080/`.
- `sarissaops/enviatodo-bundle` **v0.1.0 live on Packagist** (API-verified).
  Seam: autowired `SarissaOps\Enviatodo\Enviatodo` (`enviatodo` + alias).
  Keys: `token` (required, `%env(ENVIATODO_TOKEN)%`), `endpoint` (sandbox
  default), `api_key: enviatodo`, `app_name: custom`, `timeout: null`,
  `debug: false` (traffic inspection only).
- Not a git repo. Old `../sylius-enviatodo-plugin/` (1 commit, remote
  `sarissaops/sylius-enviatodo-plugin`) retires after init here.

## 3. API contract (from the collection file, load-bearing only)

Base `https://apiqav2.enviatodo.mx/index.php/` + `Api/…` (follow redirects;
prod base confirmed at integration time). Every request: `x-api-key: enviatodo`
+ `x-enviatodo-app: custom` + `Authorization: Bearer <JWT>` (doc typos
`Bearer:` / `Bearer Token:` are wrong). One per-call exception:
`x-enviatodo-client: 1` on `get_address_by_type_id` only — never globalize.

| # | Request | Consuming operation |
|---|---|---|
| 1–3 | `POST rates_client` ×3: `quotes.provider_service_id` (un servicio, e.g. 11) / `quotes.provider_id` (una paquetería, e.g. 9) / neither (todas) | Checkout calculator + live-rate AJAX; cheapest-rate display |
| 4 | `GET get_zip_code/{zip}` (path segment) | Zip feasibility gate in `EnviatodoMethodAvailable` |
| 5 | `POST create_order {order.type=create_order, data.uuid, detail.provider_id, provider_service_id, insurance}` | Post-payment label purchase; uuid from chosen quote row |
| 6–7 | `POST download_guides {guides}` → `file_id`; `POST download_guide_binaries {guides}` (also declares 429/500) | Admin print + bulk export; per-file `invalid_files[]` on 200; chunk ~10 |
| 8 | `POST cancel_order {tracking_ids,date{cancelled_at,refunded_at},reason{value,text},order_type=rates}` | Cancel; status 43 = refund +7 days → UI shows *pending* |
| 9–11 | `GET get_orders`; `POST get_orders_filter {date_range,provider,status,user_id}` (+ `recipient` always — Swagger-required, Postman-absent); `GET user_transactions` | Tracking grid, status sync, per-clerk audit |
| 12 | `GET get_client_balance` | Balance widget + pre-`create_order` guardrail |
| 13–17 | `add_address` (18 keys) / `get_address` / `get_address_by_id` / `get_address_by_type_id` (`1`=origin, `2`=destination) / `delete_address_by_id` (**GET**) | Origin branch book (`default_addr` HQ flag); destination presets |
| 18–21 | `add_package/` (**keep slash**, 13 keys) / `get_packages` / `get_package_by_id` / `delete_package` (**GET**) | Parcel templates; per-product parcel data |
| 22–23 | `GET get_parcel_service` / `GET provider_services` | `ProviderMap` UI (service→method codes) + weight/dims rules |
| 24–25 | `GET get_catalog/pts` / `pkt` (string `"code":"200"` here — normalize) | SAT-shaped `product_type`/`package_type_id` dropdowns |
| 26–28 | `POST get_orders_for_pickup_by_client_id {providers[]}` (not `provider_id` — that returns `data:null`) / `POST add_pickup` (9 keys: `who_delivers,contact_phone,data[],pickup_date,pickup_place,pickup_place_data,origin,provider_id,description_reference`; `contact_email` SDK-required though sample-absent) / `POST cancel_pickup {id_recollection,cancel_description,user_id}` | Pickup queue → scheduled dock run (single shared `origin_address` validated client-side) → cancel with audit |

Envelope `{success,message,data,error,code}`: HTTP 200 may carry `error: true`
(bad package keys, bogus uuid) and `success: false` may arrive with
`error: false` (bad cancel) — **the load-bearing check is `success: false`**.
`data: null` and `error: null` are success shapes. Loose typing everywhere
(`amount_pkg "150"/500`, `weight 1/"1.00"`, `default_pkg "false"/false`,
`reason.value "7"/7`, `file_id` string-out vs int-in, 3 date formats, status 71
trailing space) — coerce, nullable getters. Quote package MUST send
`bill_weight`+`real_weight`+`volumetric_weight`+`default_pkg` or 200+`error:true`
(`"Propiedades inválidas…"`). Carriers: `1/FEDEX(1,2) 4/PAQUETEXPRESS(5)
5/DHL(6,16) 8/SENDEX(9) 9/ESTAFETA(11,12) 11/BIGSMART(18,19,20) 12/AMPM(21)
14/AFIMEX(23) 15/IMILE(24) 16/UBER(25,26)`. Statuses: order
`41/42/43/44/59/68/69`, pickup `60/61/62/71`. 32 non-ISO state codes
(`JC`=Jalisco, `DF`=CDMX…). `product_type` 8-digit + `unit_type` 3-char match
SAT `c_ClaveProdServ`/`c_ClaveUnidad` shape (`01010101` = generic) — format
inference, validate against `pts`/`pkt` catalogs. No webhooks, no Postman
single-order GET (Swagger-only `get_order/{trx_id}`) → tracking sync is
poll-based and `EnviatodoShipment` persists `trx_id`+tracking+`file_id` at
creation. Secrets: `ENVIATODO_TOKEN` env only; checked-in sandbox JWT likely
expired — request fresh.

## 4. Order lifecycle (the spine)

1. **Checkout.** Destination → `get_zip_code` gates methods → `rates_client(all)`
   → calculator prices each Enviatodo-backed method → pick → **hold `uuid`**
   (session/order context; never re-quote). B2C needs only
   `full_name/email/telephone`; B2B fills `company`+branch origin.
2. **Payment.** `OrderPaidListener` → balance check → `create_order` → persist
   `trx_id`, `guides[].tracking_id`, `file_id`, `summary` amounts.
3. **Fulfilment — three paths, one tracking spine.** Print (`download_guides`),
   bulk export (`_binaries` + `invalid_files[]` retry), then per shipment exactly
   one handover path: (a) **scheduled carrier pickup** — queue → `add_pickup`
   (shared `origin_address` validated client-side) → cancel with `user_id`
   (`60/61/62/71`); (b) **branch drop-off** (Mexico-common) — staff leaves the
   parcel at a carrier branch, NO `add_pickup` call fires; the plugin records a
   local handover state (`dropped_at_branch` + timestamp + branch reference) as
   the first visible milestone, and the carrier scan chain advances tracking
   from there; (c) **delivery status** — poll-based sync via `get_orders*`
   (`41→42→44` happy, `59` in-pickup, `68/69` return/incident) surfaced in shop
   account + admin. Cancel labels stay *pending* (+7d refund).

## 5. Architecture + exact seams

`src/Component/` framework-free (`Shipping/Calculator/EnviatodoCalculator` +
`RateResult`, `ProviderMap`, `StatusMap`; zero Sylius types — ProductBundle's
mixed `src/` is the anti-pattern). Wiring only elsewhere. HTTP only via the
`Enviatodo` facade (Furgonetka's per-request raw client is the anti-pattern).
Seams (verified FQCNs): `Sylius\Component\Shipping\Calculator\CalculatorInterface::calculate(ShipmentInterface,array): int
+ getType()` registered by `#[AsShippingCalculator]` → `sylius.shipping_calculator`
(`RegisterCalculatorsPass` → `sylius.registry.shipping_calculator`);
`Core\Model\Shipment` (+`order/tracking`, `sylius_shipment` graph
`cart→ready→shipped/cancelled`); checkout `SelectShippingType` (`shipments:
CollectionType(ShipmentType)`, groups `['sylius']`, complete adds
`sylius_checkout_complete`); `SyliusPluginTrait` + `PrependDoctrineMigrationsTrait`
+ `AbstractResourceExtension`; order-show hooks
`sylius_admin.order.show.content.sections.shipments(.header/.item/.item.general/.item.actions)`
(tracking lives at `.item.general`, actions incl. `ship` at `.item.actions`);
sparse `sylius_admin.shipment.show.content.*`; hook syntax `{% hook 'a.b#c' %}`,
lowercase/dots. TwigHooks corrections (verified `Stack/src/TwigHooks`): block
types are ONLY `template | component | disabled` (no `string`);
autoprefixing is opt-in; sections use `#`; higher priority renders first
(default 0); blocks consume `hookable_metadata.context.*` (e.g.
`{% set order = hookable_metadata.context.order %}`); register via
`config.yaml` imports of `twig_hooks/**/*.yaml` OR
`prependExtensionConfig('sylius_twig_hooks', …)`; app-level pattern =
disable core hook (`enabled: false`) + replacement block at negative priority
(Mollie/Stripe/Adyen in Standard); verify names with
`bin/console sylius:debug:twig-hooks`. Company = `Address.company` +
`CustomerGroup` (nothing on `Customer`). Config: `services.xml` (+`services/*.xml`, explicit prefixed
private ids, no autowiring — bundle rule) + `sylius_resources.yaml` +
`routes/{admin,shop}.yaml` + `twig_hooks/**/*.yaml` + `doctrine/*.orm.xml`
(mapped-superclass) + `validation/*.xml` + `serialization/{Admin,Shop}/*.xml` +
`api_resources/{admin,shop}/*.xml` (APIP-4 `Provider/Processor` only; single
version so standard dirs are safe). Entities: settings key-value (`name` PK +
LONGTEXT) with hidden-secret read-filter; shipment extension fields; logs
(`level` enum + JSON context, write-time −14d prune, export/reset actions);
dual MySQL (`AbstractMigration`) + PostgreSQL (`AbstractPostgreSQLMigration`)
migrations (ProductBundle `Version20250603055430/62000` template; Furgonetka
MySQL-only is the anti-pattern). Company-gating: own settings enum +
`MENU_SECTIONS page=>companyOnly` + Twig fn + grid option (Furgonetka pattern,
detector swapped to Enviatodo data — no suite coupling). Native Sylius forms
calling `enviatodo-php` directly (no iframe indirection).

## 6. References (where each answer lives)

Skeleton/harness `../PluginSkeleton/` (`CLAUDE/CLEANUP/RENAME` guides);
checkout/admin patterns `../sylius-furgonetka-plugin/` (map, validation pass,
menu, grid action, CSRF AJAX, logs — minus v1 dual layout, OAuth dance,
iframe); resources/Messenger/API `../ProductBundlePlugin/` (bus +
`#[AsMessageHandler]`, Command/Dto/DataTransformer `messenger=input`, XML
splits, decoration, `api+unit` + Behat triad); hooks `../Stack/`; DI
`../symfony-docs/` (explicit config beats prepend; passes work on Definitions
with early-return); packaging `../composer/`+`../flex/` (§7); full-store E2E
`../Sylius-Standard/` (pristine — `make init` first; MySQL 8.4; path-repo +
`bundles.php` + `config/packages/sylius_enviatodo.yaml`; entity example
`src/Entity/Shipping/Shipment.php`).

## 7. Packaging checklist (`sarissaops/sylius-enviatodo-plugin`)

`type: sylius-plugin`; no `version` (tags); PSR-4 trailing `\\`;
`require: php ^8.2, sylius/sylius ^2.1` (installed 2.2.9),
`sarissaops/enviatodo-bundle ^0.1`; `require-dev: behat, friends-of-behat/*,
sylius/test-application ^2.0` (never `require`), `phpunit ^10.5, phpstan ^2.2,
lchrusciel/api-test-case, sylius-labs/coding-standard` (no cs-fixer — decided);
`extra: branch-alias dev-main→0.1.x-dev, symfony.require ^7.4`;
`sort-packages`; lock git-ignored; `.gitattributes` export-ignore
(`/.github/ /tests dev-dist QA configs README CHANGELOG`); `composer validate`
clean. Flex auto-registers from the `*Plugin`+`Bundle`-base heuristic — ship no
`config/bundles.php`; document manual `=> ['all'=>true]` + `ENVIATODO_TOKEN`.

## 8. Gates (this dir)

```
composer validate
vendor/bin/phpunit                                   # unit/functional/integration (+api)
vendor/bin/behat --strict --tags="~@javascript&&~@mink:chromedriver"   # JS: APP_ENV=test server :8080 + Chrome :9222
vendor/bin/phpstan analyse -c phpstan.neon -l max src/                 # zero baseline
vendor/bin/ecs check                                 # scope src + tests/{Api,Behat,Unit}
composer run database-reset                          # restore script in Step 0
# instance health (Standard/TestApplication):
bin/console lint:container && bin/console debug:router | grep -i envia \
  && bin/console sylius:debug:twig-hooks | grep -i envia && bin/console doctrine:schema:validate
```

Static → phpunit → Behat. ECS and cs-fixer never coexist (decided: ECS).

## 9. Build steps (all complete; per-step commits + deviations)

- **Step 0 — Repair ✓ (`2e3f0ff`).** composer.json (§2/§7) → restore 4 QA configs from
  skeleton → delete greetings (keep smoke) → real `Configuration`
  (`token/endpoint/defaults/allowlist/pickup toggle`) + `load()` → plugin
  migration namespace → `composer update` → gate green → `git init` + remote,
  retire old dir. Deviation: dropped `sylius/sylius-rector` (pins phpstan ^1
  via rector/rector, conflicts with the level-max toolchain).
- **Step 1 — Quote (API #1–4) ✓ (`244f512`).** Feature `selecting_enviatodo_shipping_method`
  (reuse `sylius.behat.context.*` + 1 plugin context) → `CalculatorTest`
  (mocked `QuoteApi`, 4 weight keys) → `EnviatodoCalculator`+`RateResult` →
  explicit-tag adapter (deviation: `sylius.shipping_calculator` tag, **not**
  `#[AsShippingCalculator]`) + container test → zip validator +
  CSRF AJAX quote + `ProviderMap` (tolerant decode, shared gate). Test style
  mirrors mailgun reality: Component tests = inline rate-row JSON (no fixture
  files); adapter tests = real class + mocked transport asserting
  URI/method/body with `null`=wildcard. Exit: purchasable rates, uuid held.
- **Step 2 — Buy label (API #5, #12) ✓ (`b7c12b1`).** `OrderPaidListener` → balance guard →
  `create_order` → persist tracking/`file_id`/summary → dual
  migrations → repos. Addition: two-phase draft→complete (draft holds uuid at
  checkout, completion spends it post-payment); `EnviatodoOrderManagerInterface`
  + `EnviatodoShipmentRepositoryInterface` extracted (final classes unmockable);
  scoped `property.unusedType` ignore for Doctrine ids (documented, no baseline);
  no `trx_id` persisted (create response carries summary + guides only).
  Exit: paid order → shippable label.
- **Step 3 — Labels/tracking/cancel/handover (API #6–11 + local state) ✓ (`b306e6a`).**
  Order-show shipment panels (hook `.item.general` for tracking, `.item.actions`
  for label/ship buttons); download + bulk-binary (+`invalid_files[]` retry,
  ~10 chunks, custom grid `bulk` action — core grids define none); tracking sync
  via `StatusMap`; cancel → *pending*. Handover state on the shipment extension
  (`label_created → [dropped_at_branch(+branch ref) | in_pickup] → in_transit →
  delivered`) with the drop-off branch recorded Furgonetka-point-style
  (`branchCode/branchName/carrier`) but as a *merchant* action needing no API
  call. Company-gated extras via own enum. Partially deferred (no BDD scenario
  required them): company menu gating, order grid action, `OrderNormalizer`.
  Exit: ops without Enviatodo
  dashboard.
- **Step 4 — Pickups (API #26–28) ✓ (`debefee`).** Queue (`providers[]`), create form
  (shared-origin check, handover contacts incl. `contact_email`), cancel with
  `user_id`; auto-schedule toggle. Addition: shared `CsrfProtection` guard
  extracted on 4th duplication (Rule of Three). Exit: one call ends the warehouse day.
- **Step 5 — Master data (API #13–25) ✓ (`90f1229`).** Address CRUD (per-call header on
  by-type; GET deletes), package CRUD (slash kept; 4 weights; declared value +
  insurance fields), catalog-fed SAT dropdowns, carrier-mapping UI, checkout
  defaults. Addition: controllers are **plain callables** (live 500 proved
  `AbstractController` without autoconfiguration never gets `setContainer()` —
  matches Mollie convention). Exit: merchant self-serves.
- **Step 6 — Headless + i18n ✓ (`3aef392`).** APIP-4 XML admin/shop (custom processors, not
  `messenger=input`: synchronous JSON needs no bus surface — KISS deviation),
  DTO groups/validation via PHP attributes on our own classes (XML reserved
  for third-party-class mappings), provider/processor unit tests
  (`api-test-case` deferred with Step 7 DB infra), `es`-primary translations,
  UI wiring gated behind Twig presence. Known-remaining: §14 P0/P1. Exit: headless parity.
- **Step 7 — E2E ✓ (`3868055`).** Behat file set (skeleton shape): `behat.yml.dist`
  (`%env(BEHAT_BASE_URL)%` + `SuiteTagsExtension`, imports core `suites.yml` +
  `tests/Behat/Resources/suites.yml`); `suites.yml` (core `doctrine_orm` +
  transforms + setups + shop/admin UI contexts + 2 plugin contexts, tags
  `@enviatodo_shipping`/`@enviatodo_config`); `services.xml` (`public=true`
  defaults; pages as `sylius.behat.symfony_page` / admin CRUD children);
  `Setup/EnviatodoContext` (seed methods + shared storage), 2 UI contexts
  (delegate to pages + `Assert::*`), 2 page interface+impl pairs
  (`getRouteName` + `[data-test-*]` elements); 2 features with `Background`
  (single channel / admin login) and `@ui` vs `@ui @javascript` split
  (`DriverHelper::isNotJavascript` guards in pages). Non-JS Behat
  (TestApplication) → JS (Chrome + test server) → `Sylius-Standard/`
  full-store pass: path-repo require → `bundles.php` (`['all'=>true]`) →
  `config/packages/sylius_enviatodo.yaml` (import + hook swaps only) →
  `config/routes/sylius_enviatodo.yaml` (single resource re-export) →
  entity traits on clean `Shipment`/`ShippingMethod` (Order: keep touch
  minimal — 4 Mollie traits already) → assets entrypoint imports →
  `make init` (MySQL 8.4) → sandbox `ENVIATODO_TOKEN` → health commands (§8).
  As built: programmatic order placement in admin scenarios (avoids shop/admin
  step-definition collision); example-factory method creation (PHPStan caught
  `addChannel()` misuse); TestApplication frontend build prerequisite
  (`yarn install` + `encore dev` + `assets:install`); test DB reuses granted
  empty `sylius` DB (no `CREATE` privilege — documented in `.env.test.local`);
  `@enviatodo_live` excluded by default; shop UI flows need Chrome
  (`@javascript`, non-JS LiveComponent add-to-cart silently no-ops).

## 10. Explicitly deferred (vanilla-only rule)

Company/branch hierarchies, buyer orgs, quote negotiation, credit limits, or
any detection/adapters for third-party B2B suites: no `class_exists` shims,
no optional integrations, no `suggest` wiring for unrun suites (YAGNI).
Extension stays possible via already-interface-typed seams (calculator iface,
pass group constants, narrow map methods, structured log context) — a future
adapter is a new class + wiring, never an edit. Revisit only when a suite is
actually present in a target app.

## 11. Constraints

`Component/` framework-free; no autowiring in shipped config;
`test-application` never in `require`; single `twig_hooks`/Bootstrap-5 path;
`success`-flag checks; null-`data`/`error` = success; coerce loose scalars;
`recipient` always sent; slash kept; GET deletes; token env-only. Sandbox token
PROVEN LIVE 2026-09-22 (balance 9717.82 MXN; quote returned 4 rows
ESTAFETA/DHL with MXN totals and uuid). Prod base URL still open at
integration time.

## 12. Appendix — Furgonetka mirror/skip list (verified file inventory, Sylius 2.x only)

Mirror (rename `Furgonetka→Enviatodo`, `PickupPoint→Rate/Branch/Quote`):
`Resources/config/services.yaml` (explicit wiring; normalizer priority 100 +
`api_platform.filter` defs); `validation.yaml` (Order class-constraint, groups
`[enviatodo_select_shipping, sylius_checkout_complete]`); `serialization/Order.yaml`;
`api_mapping/v2/{Order,Settings}.yaml` (APIP-4; conventional `api_resources/`
safe — single version, no double-load); `app/v2/views.yaml` (twig_hooks +
grid action); `shop_routing.yaml` (`POST /enviatodo/ajax/…`, `_sylius.section:
shop`) + `admin_routing.yaml` (trimmed); `Twig/*ShopExtension` (saved
rate/branch, config-for-method forwards) + `*AdminExtension`
(is_integrated/is_configured); `Serializer/OrderNormalizer` (guard const +
group gate → `enviatodoOrderData{quoteUuid,rate,branch,tracking}`);
`Compiler/SelectShippingValidationGroupPass` (dedicated group constant);
extension `load()+prepend()` (views + api_mapping/v2 only) + `build()`
single pass; `Form/Extension/SelectShippingTypeExtension` (`POST_SUBMIT`
stale-cleanup); shop AJAX controller (CSRF id, payload structure,
`validatePoint`, `resolveCart` via `CartContextInterface`, upsert
`{order,shippingMethodCode,code/uuid,name,type}`); `RateSelected`
constraint+validator (method gate + `findOneByOrder` check); shop views
(`select_shipping/{choice,address_data,notice}`, `complete/summary`,
`_javascripts/_stylesheets`) + `enviatodo-shop.js` (`X-CSRF-Token` +
`data-*` contract); admin views (order sidebar/detail, grid action, modal,
assets); `ApiPlatform/Resource/EnviatodoSettingResource` +
`State/{Provider,Processor}`; rate-choice repository
(`findOneByOrder/save/remove`). Logging: `Logs{level,message,context-json}` +
write-time −14d prune + export/reset (context carries
`uuid→order→guide/pickup`); settings key-value with hidden-secret filter;
`MENU_SECTIONS page=>companyOnly` + Twig fn + grid option for company gating.

Skip: `services_v1.yaml`, `api_mapping/v1/*`, `app/v1/views.yaml`,
`views/{admin,shop}/v1/**`, `public/{js/v1,css/v1}`, APIP-2
`DataPersister/Provider`, `sylius_ui` events, `MAJOR_VERSION` switches,
MySQL-only migrations, iframe/welcome/error/advanced screens, OAuth
controllers (static Bearer needs only a validate/reconnect check on admin
  entry), `liip/imagine` hard dep.

## 13. Live lessons (paid for in debugging time)

- L1 plain controllers: `AbstractController` services without autoconfiguration
  never get `setContainer()` → live 500 “has no container set”. Fix: plain
  final classes + `controller.service_arguments` only (Mollie convention).
- L2 `hasExtension()` inside `Extension::load()` is unreliable (`load()` runs on
  a temp container copy) → set the `ui_enabled` flag in `prepend()` instead.
- L3 `config/services/**` glob imports everything beneath it — UI-only file had
  to move to top-level `config/twig.xml` or the gate is bypassed.
- L4 LiveComponent add-to-cart is JS-only (core's own scenario is
  `@mink:chromedriver`); non-JS UI checkout silently no-ops. Chrome needs
  `--remote-allow-origins=*` (CDP rejects otherwise) plus warm start (first WS
  connect races).
- L5 Suite step collision: shop addressing + admin login both define the
  password step → place orders programmatically in admin-suite scenarios.
- L6 Reference-clone drift: workspace `Sylius/` is v2.3.0-dev (`.php` configs)
  vs vendored v2.2.9 (XML) — verify service ids/annotations against `vendor/`,
  treat the clone as indicative only.
- L7 Env facts: MariaDB 11.8.8 (not MySQL 8.4); test DB is `sylius`, dev DB
  `sylius_dev`; MySQL `root` unreachable → reuse granted DBs; dev server moved
  `:8001` → `:8000`.
- L8 Template context fallback: our tracking template reads
  `hookable_metadata.context.order`, but all ~98 core admin order fragments read
  `.resource` — `order` arrives only via metadata-name leakage. A themed/custom
  order controller rendering with just `resource` silences our panel with no
  log. Fix (open): `{% set enviatodo_order = hookable_metadata.context.resource
  ?? hookable_metadata.context.order ?? null %}` + a test rendering with only
  `resource` in context.
- L9 Theme override path: `<theme>/templates/bundles/SarissaOpsSyliusEnviatodoPlugin/admin/order/tracking.html.twig`
  (literal `Plugin`, no `Bundle` suffix — `NamespacedTemplateLocator.php:59-66`);
  document it in README. Hook names are theme-stable; emission depends on the
  theme keeping `{% hook %}` tags (inherent to the hooks design, not our bug).

## 14. Known-remaining API gaps (headless audit, unverified code — fix next)

- P0 scope the shop lookup: `ShipmentLiveRates.xml` `shipmentId` lacks
  `fromProperty="order"`; `LiveRatesProcessor` uses bare `findOneBy(token)` —
  cross-order/cross-channel replay works today. Fix: scoped cart-only query +
  channel + state checks (cf. core `Order.xml:210-213`, `ItemProvider.php:39-60`).
- P0 error mapping: SDK/`InvalidArgument` failures bubble as 500 HTML, not
  ApiProblem JSON; bulk silent-empty-200 on unknown ids. Fix: 502/422 mapping
  mirroring `QuoteController`, `<openapi><responses>` per op.
- P1 explicit `<validationContext>`, `Positive`/`Collection` DTO tightening,
  `status="200"` on the shop op (implicit 201 today), nested-schema render test.
