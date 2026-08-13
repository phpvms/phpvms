# resources/js/packages

Shared JS/TS packages consumed by more than one app under `resources/js/apps/`.

Empty for now — each app (`fe`, `admin`, `seven`) is currently self-contained.
As shared code emerges (tokens, utilities, a component kit), extract it into a
`packages/<name>/` here and import it from the apps.
