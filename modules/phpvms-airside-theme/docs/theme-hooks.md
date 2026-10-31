# airside hooks

Every hook airside exposes for styling. See [customizing.md](customizing.md) for
how hooks work and how to override them.

Hook classes start with `vms-`. Add an entry in the same change that adds a hook.
Each entry lists:

- **View:** the file the hook is in.
- **Element:** what the class sits on.
- **Parts:** child hooks (`vms-<name>-<part>`).
- **State:** the attributes and values to select on, e.g. `[aria-selected="true"]`.
- **Variables:** CSS variables, with their unit and default.
- **Stability:** `stable` (a public contract: renaming it is a breaking change)
  or `draft` (may still change).

## Components

### `vms-auth-card`

The card every auth page sits in.

- **View:** `resources/views/components/auth/card.blade.php`
- **Element:** the root `<section>`.
- **Parts:** none yet.
- **State:** none.
- **Variables:** none.
- **Stability:** draft. The class is in the markup; the rule in `airside.css` is
  not written yet.
