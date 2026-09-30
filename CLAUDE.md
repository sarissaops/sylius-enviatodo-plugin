# SarissaOps Sylius Enviatodo Plugin — agent notes

> This file replaces the plugin-skeleton boilerplate. The build record is
> `plan.md` (§5 architecture, §8 gates, §13 live lessons); end-user docs are
> `README.md` / `CHANGELOG.md`.

## Gates (run inside this dir, in order)

```bash
composer validate
vendor/bin/phpunit --testsuite=unit
vendor/bin/phpstan analyse -c phpstan.neon -l max src/
vendor/bin/ecs check
vendor/bin/behat --strict --tags="~@javascript&&~@mink:chromedriver&&~@enviatodo_live"
```

No `Makefile`, no `php-cs-fixer` — ECS is the only style tool. `sylius/test-application`
is require-dev, never `require`. Never commit `tests/TestApplication/.env.test.local`
or any `.env` / token.

## Architecture rules

- All Enviatodo traffic via the autowired `SarissaOps\Enviatodo\Enviatodo` facade.
  Never construct raw HTTP clients.
- `src/Component/Shipping/` is framework-free (no Sylius/Symfony imports).
- Config is explicit XML, no autowiring. UI wiring lives in top-level
  `config/twig.xml`, gated by `ui_enabled` set in `Extension::prepend()` — never
  put UI services under `config/services/**` (the glob bypasses the gate).
- Controllers are plain `final` callables, not `AbstractController`.
- Migrations ship in MySQL (`AbstractMigration`) + PostgreSQL
  (`AbstractPostgreSQLMigration`) pairs.
- `src/DependencyInjection/Configuration.php` is excluded from PHPStan (crashes it).
