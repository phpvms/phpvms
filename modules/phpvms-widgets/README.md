# phpvms widgets (`phpvms/widgets`)

First-party pilot tiles for Blade themes such as airside, registered with the
core tile registry (`App\Features\Tiles\Tiles`). See the "Tiles" section of
`docs/addons.md` for the API.

| Tile id       | Livewire component              | Dashboard zone |
| ------------- | ------------------------------- | -------------- |
| `kpi-hours`   | `phpvmswidgets::total-hours`    | grid           |
| `kpi-flights` | `phpvmswidgets::flights`        | grid           |
| `kpi-balance` | `phpvmswidgets::balance`        | grid           |
| `rank`        | `phpvmswidgets::rank-progress`  | grid           |
| `last-flight` | `phpvmswidgets::last-flight`    | sidebar        |
| `weather`     | `phpvmswidgets::weather`        | sidebar        |

All six are offered on the dashboard and are in its default layout.

- Classes: `Livewire/`. Views: `resources/views/livewire/` (`phpvmswidgets::`).
  Strings: `resources/lang/en/widgets.php`.
- The weather tile loads lazily. It shows the pilot's current airport, else the
  home airport, unless the theme passes `icao`. `Support/WeatherSummary.php`
  turns the METAR into the conditions, temperature and wind rows.

The addon installs disabled. Enable it on the admin Add-ons page; while it is
disabled its provider never boots and none of the tiles exist.
