# Observability: OpenTelemetry tracing

Production runs official OpenTelemetry **zero-code auto-instrumentation** for PHP/Laravel.
No application code was changed to add this — it's an extension plus a handful of
Composer packages that hook into the PHP runtime and Laravel's service container
automatically.

## What was added

- **`production.Dockerfile`** — the `opentelemetry` PECL extension, installed via the
  same `install-php-extensions` layer already used for `pdo_pgsql`, `zip`, and
  `bcmath`.
- **Composer packages** (`composer.json` / `composer.lock`):
  - [`open-telemetry/sdk`](https://packagist.org/packages/open-telemetry/sdk) — the OTel SDK.
  - [`open-telemetry/exporter-otlp`](https://packagist.org/packages/open-telemetry/exporter-otlp) — OTLP exporter (HTTP/protobuf).
  - [`open-telemetry/opentelemetry-auto-laravel`](https://packagist.org/packages/open-telemetry/opentelemetry-auto-laravel) — auto-instrumentation hooks for Laravel (HTTP requests, queries, queue jobs, cache, etc.).
- **`kubernetes/app.yaml`** — `OTEL_*` env vars on the `laravel` container of the
  `trip-planner` Deployment (see below).
- **`.env.example`** — the same variables, commented out, for local reference.

## Environment variables (production)

| Variable | Value | Purpose |
|---|---|---|
| `OTEL_PHP_AUTOLOAD_ENABLED` | `true` | Turns auto-instrumentation on. **This is the escape hatch** — see below. |
| `OTEL_SERVICE_NAME` | `trip-planner` | `service.name` resource attribute; how the app shows up in Grafana/Tempo. |
| `OTEL_TRACES_EXPORTER` | `otlp` | Export traces via OTLP. |
| `OTEL_LOGS_EXPORTER` | `none` | Logs already reach Loki via stdout → kubelet → Alloy's `loki.source.kubernetes` pipeline; the OTel logs exporter is disabled to avoid double-shipping. |
| `OTEL_EXPORTER_OTLP_ENDPOINT` | `http://alloy.observability.svc.cluster.local:4318` | In-cluster Alloy collector, OTLP/HTTP. |
| `OTEL_EXPORTER_OTLP_PROTOCOL` | `http/protobuf` | Matches the collector's HTTP/protobuf listener. |
| `OTEL_PROPAGATORS` | `baggage,tracecontext` | W3C Trace Context + Baggage propagation. |
| `OTEL_RESOURCE_ATTRIBUTES` | `deployment.environment.name=production` | Tags every span with its environment. |
| `OTEL_TRACES_SAMPLER` | `parentbased_always_on` | Sample everything, honoring any upstream sampling decision. |

## Viewing traces

Traces land in **Tempo** and are viewable through **Grafana**:
<https://grafana.k3s.szanto-zoltan.com>

Search Tempo for `service.name="trip-planner"` after a few requests hit
<https://trip-planner.szanto-zoltan.com>. HTTP requests, DB queries, and queue
jobs show up as their own spans; cache operations are recorded as events on
whichever span is active when they happen, rather than as standalone spans —
all without any application code changes.

## Disabling it (escape hatch)

Auto-instrumentation is controlled entirely by env vars — there is no code path to
revert. To turn it off with zero code changes:

- Set `OTEL_PHP_AUTOLOAD_ENABLED=false` on the `laravel` container (fastest — no
  redeploy of the image needed, only the Deployment env), **or**
- Revert the `OTEL_*` block in `kubernetes/app.yaml` and re-apply, **or**
- Revert this change entirely via `git revert`.

The `opentelemetry` PHP extension and Composer packages can stay installed even
with autoloading disabled — they're inert until `OTEL_PHP_AUTOLOAD_ENABLED=true`.

## Local development

Local dev (`php artisan serve`, Sail, etc.) is unaffected by default. `.env.example`
lists the `OTEL_*` vars for reference, but uncommenting them there does **not**
enable instrumentation: the extension's autoloader hooks in from
`vendor/autoload.php`, which runs before Laravel boots and loads `.env`, so the
SDK never sees values set only in that file. To opt in locally, install the
`opentelemetry` PECL extension (e.g. `pecl install opentelemetry`) and export the
vars as real process/shell env vars instead, e.g.:

```sh
OTEL_PHP_AUTOLOAD_ENABLED=true OTEL_SERVICE_NAME=trip-planner php artisan serve
```
