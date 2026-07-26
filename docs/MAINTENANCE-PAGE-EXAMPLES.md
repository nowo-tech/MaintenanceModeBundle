# Maintenance page examples

Ready-to-use public 503 templates with **calm, anxiety-light copy** in **en / es / it / fr / pt / de / nl**.

Inspiration for the “idea_*” set: common maintenance-page patterns (short & sweet, compassionate, playful, brand-familiar, countdown, live updates) — adapted for Symfony without linking the public page to panel login. See also [this showcase of maintenance-page ideas](https://es.ephesossoftware.com/articles/showcase/6-maintenance-page-ideas-you-can-use-on-your-wordpress-site.html).

## Table of contents

- [Framework gallery](#framework-gallery)
- [Idea gallery](#idea-gallery-6-patterns)
- [Activate an example](#activate-an-example)
- [Demo gallery](#demo-gallery)
- [Translation keys](#translation-keys)
- [Operator access](#operator-access-important)

## Framework gallery

CDN frameworks (latest at ship time):

| Template | Framework | Style |
| --- | --- | --- |
| `maintenance/examples/bootstrap_calm.html.twig` | Bootstrap **5.3.8** | Soft teal calm card |
| `maintenance/examples/bootstrap_sunset.html.twig` | Bootstrap **5.3.8** | Warm sunset / peach |
| `maintenance/examples/foundation_garden.html.twig` | Foundation Sites **6.9.0** | Sage garden |
| `maintenance/examples/foundation_night.html.twig` | Foundation Sites **6.9.0** | Calm night / slate |
| `maintenance/examples/tailwind_breeze.html.twig` | Tailwind CSS **4** (`@tailwindcss/browser`) | Mint breeze |
| `maintenance/examples/tailwind_aurora.html.twig` | Tailwind CSS **4** | Aurora dusk |

## Idea gallery (6 patterns)

| Template | Framework | Pattern |
| --- | --- | --- |
| `maintenance/examples/idea_short.html.twig` | Bootstrap **5.3.8** | Short & sweet — minimal message |
| `maintenance/examples/idea_compassion.html.twig` | Bootstrap **5.3.8** | Compassionate apology + status note (no admin links) |
| `maintenance/examples/idea_playful.html.twig` | Tailwind CSS **4** | Light humor / friendly pit stop |
| `maintenance/examples/idea_brand.html.twig` | Vanilla CSS | Familiar brand mark + restrained palette |
| `maintenance/examples/idea_countdown.html.twig` | Tailwind CSS **4** | Countdown from `state.scheduledDisableAt` |
| `maintenance/examples/idea_updates.html.twig` | Foundation Sites **6.9.0** | Progress / status log |

Default page (no CDN): `maintenance/page.html.twig` — also uses the calm messaging keys.

## Activate an example

```yaml
# config/packages/nowo_maintenance_mode.yaml
nowo_maintenance_mode:
    templates:
        page: '@NowoMaintenanceModeBundle/maintenance/examples/idea_countdown.html.twig'
```

Or override in the app:

```twig
{# templates/bundles/NowoMaintenanceModeBundle/maintenance/page.html.twig #}
{% include '@NowoMaintenanceModeBundle/maintenance/examples/idea_compassion.html.twig' %}
```

When maintenance is enabled with a custom message (`MaintenanceManager::enable(...)` or the panel), that message replaces the template’s default translated body while heading / reassure / thanks stay localized.

The countdown example reads `state.scheduledDisableAt` (passed by the subscriber). If no end time is scheduled, it shows the translated “Soon” fallback.

## Demo gallery

In `demo/symfony8` (path `/examples` is excluded from 503):

```bash
make -C demo/symfony8 up
# http://localhost:8055/examples
# http://localhost:8055/examples/idea-short?_locale=es
# http://localhost:8055/examples/idea-countdown?_locale=en
```

## Translation keys

Shared default page: `maintenance.page.*` (`title`, `badge`, `heading`, `message`, `reassure`, `thanks`).

Per example: `maintenance.examples.<name>.*` with the same fields plus `eyebrow` (and extras such as `punchline`, `status_note`, `remaining`, `log_*` where needed).

Domain: `NowoMaintenanceModeBundle`.

## Operator access (important)

These public templates must **not** link to panel login. Keep `/_maintenance` (auto-excluded) or other ops URLs in `exclusions.*`, and toggle login with `security.password_protection` in YAML. Demo guide: `/examples/bypass`.
