## ADDED Requirements

### Requirement: Dashboard payload exposes the pilot summary values

The system SHALL expose pilot status, transfer time, absolute rank progress, pilot score, on-time percentage, and average landing rate in the typed dashboard payload. The dashboard summary SHALL use shared authenticated-user props for pilot identity instead of adding a second identity projection.

#### Scenario: Normal pilot summary payload

- **WHEN** an authenticated pilot with stored career data requests the dashboard
- **THEN** the payload contains status, flights, flight time, transfer time, balance, rank progress, pilot score, on-time percentage, and average landing rate

#### Scenario: Shared identity is used

- **WHEN** the fixed dashboard summary renders pilot identity
- **THEN** it reads name, avatar, ident, callsign, and airline from the shared authenticated-user props

#### Scenario: Generated types stay synchronized

- **WHEN** the PHP dashboard DTO fields change
- **THEN** the generated TypeScript declarations match the server payload

### Requirement: Dashboard values use authoritative sources

The system SHALL source flights, flight time, transfer time, status, current airport, and rank inputs from `User`; balance from the current `journal`; rank targets from `Rank`; and pilot score and average landing rate from the pilot's accepted PIREPs. Missing derived values SHALL be null.

#### Scenario: Accepted PIREPs provide derived metrics

- **WHEN** accepted PIREPs contain non-null scores and non-zero landing rates
- **THEN** pilot score and average landing rate are rounded averages of those accepted values

#### Scenario: Unusable PIREP values are excluded

- **WHEN** a PIREP is not accepted, has a null score, or has a null or zero landing rate
- **THEN** that unusable value does not contribute to the corresponding aggregate

#### Scenario: No measurable values

- **WHEN** no accepted PIREP provides a measurable score or landing rate
- **THEN** the corresponding DTO field is null and the UI renders `—`

#### Scenario: Balance remains live

- **WHEN** the dashboard payload is built
- **THEN** the Balance value equals the pilot's current journal balance

### Requirement: Rank progress uses absolute hours

The system SHALL expose current rank, next rank, current rank hours, target rank hours, hours remaining, and a clamped percentage. Current rank hours SHALL include transfer time only when `pilots.count_transfer_hours` is enabled.

#### Scenario: Pilot has a next rank

- **WHEN** the pilot has a rank above them
- **THEN** the payload contains the current and next rank names, absolute current and target hours, non-negative remaining hours, and a 0–100 percentage

#### Scenario: Transfer time counts toward rank

- **WHEN** `pilots.count_transfer_hours` is enabled
- **THEN** current rank hours equal flight time plus transfer time

#### Scenario: Pilot is at the highest rank

- **WHEN** no higher rank exists
- **THEN** the next rank, target hours, and hours remaining are null and rank progress is complete

### Requirement: Pilot summary stays fixed above the widget board

The system SHALL render the expanded `DashboardPilotHeader` before the dashboard toolbar and customizable widget board. Dashboard layout edits SHALL NOT move, remove, or duplicate the summary.

#### Scenario: Dashboard opens normally

- **WHEN** the dashboard renders
- **THEN** the pilot summary appears once above the dashboard toolbar and widget board

#### Scenario: Widget customization is enabled

- **WHEN** the pilot adds, removes, or reorders dashboard widgets
- **THEN** the fixed pilot summary stays in place and the widget board behavior is unchanged

### Requirement: Pilot summary matches the locked profile section

The summary SHALL follow `section#profile` of `mockups/b-workspace.html` for its information order and presentation. It SHALL show pilot identity and status, rank progress, current and target hours, flights, flight hours, transfer hours, balance, pilot score, on-time percentage, and average landing rate. It SHALL NOT copy unrelated mockup sections.

#### Scenario: Wide desktop presentation

- **WHEN** the summary has wide desktop space
- **THEN** identity and rank progress share the top row and the seven career metrics render in seven columns

#### Scenario: Tablet presentation

- **WHEN** the summary has tablet width
- **THEN** identity and rank progress remain readable and the career metrics render in four columns

#### Scenario: 390px presentation

- **WHEN** the viewport is 390px wide
- **THEN** identity and rank progress stack, the metrics render in two columns, and the page has no horizontal overflow

#### Scenario: Semantic status and rank progress

- **WHEN** status and rank progress render
- **THEN** both have readable text and accessible state or progress semantics without relying on color alone

### Requirement: On-time percentage uses scheduled arrival

The system SHALL snapshot a scheduled arrival timestamp when a PIREP is created from a scheduled flight. It SHALL calculate pilot on-time percentage from accepted scheduled PIREPs. An operation SHALL be on time when actual block-on occurs less than 15 minutes after scheduled arrival. The UI SHALL distinguish unavailable from zero percent.

#### Scenario: Scheduled arrival is snapshotted

- **WHEN** a PIREP is created from a scheduled flight
- **THEN** the PIREP stores an immutable UTC scheduled-arrival timestamp derived from the service date, structured schedule, and airport timezones

#### Scenario: Flight schedule changes later

- **WHEN** the source flight's schedule is edited after the PIREP is created
- **THEN** the PIREP's scheduled-arrival timestamp and historical on-time result do not change

#### Scenario: Arrival is on time

- **WHEN** an accepted scheduled PIREP blocks on earlier than scheduled arrival plus 15 minutes
- **THEN** it counts as an on-time operation

#### Scenario: Arrival is late

- **WHEN** an accepted scheduled PIREP blocks on at or after scheduled arrival plus 15 minutes
- **THEN** it counts as a late operation

#### Scenario: Accepted operation is diverted

- **WHEN** an accepted scheduled PIREP is marked diverted
- **THEN** it counts as a late operation

#### Scenario: Unscheduled operation is excluded

- **WHEN** a manual or legacy PIREP has no scheduled-arrival snapshot
- **THEN** it is excluded from both the on-time count and denominator

#### Scenario: On-time is unavailable

- **WHEN** `onTimePercentage` is null
- **THEN** the summary renders `—` and does not render `0%`

#### Scenario: On-time is measured

- **WHEN** `onTimePercentage` contains a measured value
- **THEN** the summary renders the value as a percentage from 0 to 100

### Requirement: Pilot awards use one shared compact presentation

The system SHALL use one shared awards component anywhere pilot awards are displayed. It SHALL follow `section#awards` of `mockups/a-operations-console.html` with an icon tile, title, qualifier, earned state, count, and a responsive two/three/six-column grid.

#### Scenario: Awards with images

- **WHEN** awards contain image URLs
- **THEN** each card displays its image, title, and qualifier in the compact grid

#### Scenario: Award image is missing or fails

- **WHEN** an award has no usable image
- **THEN** its card displays a neutral award icon without losing the title or qualifier

#### Scenario: Long award text

- **WHEN** an award has a long title or qualifier
- **THEN** the text wraps inside the card and is not truncated

#### Scenario: No awards

- **WHEN** the pilot has no awards
- **THEN** the shared presentation shows a zero count and a small empty state without an empty grid

#### Scenario: Responsive award columns

- **WHEN** the awards presentation moves from mobile to tablet to wide desktop
- **THEN** it renders two, three, and six columns respectively with no horizontal overflow at 390px

### Requirement: Runtime themes and addons remain compatible

The summary and awards SHALL use documented `--pv-*` variables and stable `.pv-*` hooks. Generic controls SHALL use Nuxt UI directly. The change SHALL preserve the dashboard widget board, addon slots, and the single shared Vue runtime.

#### Scenario: Theme mode changes

- **WHEN** the pilot selects light, dark, or auto mode
- **THEN** summary and award content remain readable and use the active runtime theme without a page reload

#### Scenario: Addon widgets render

- **WHEN** host and addon dashboard widgets render together
- **THEN** the fixed summary does not change addon order, page-prop resolution, or Vue runtime identity
