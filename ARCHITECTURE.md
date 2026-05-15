# Architecture

This document describes the architecture that is actually implemented in this repository today.

## System Design Philosophy

The plugin is intentionally small and WordPress-native:

- recurring publication intent is stored separately from actual scheduled post state
- WordPress core remains the source of truth for when a post will publish
- editor integrations are thin clients over shared PHP scheduling logic
- there is no standalone service, worker, or frontend app

The central architectural choice is this split:

- recurring slot definitions live in a custom table
- actual queued posts live in core `wp_posts` as normal scheduled posts

That keeps publication compatible with WordPress' existing scheduled-post behavior instead of introducing a parallel publish pipeline.

## Main Components

### Bootstrap and orchestration

All server-side logic lives in `queue-posts-for-publication.php`.

Key traits:

- singleton plugin class: `Queue_Posts_For_Publication`
- hooks registered in `init_hooks()`
- activation creates the custom table
- one file owns admin pages, REST routes, AJAX handlers, asset loading, and scheduling logic

### Admin pages

Implemented admin pages:

- `queue-posts-slots`: add and delete recurring slot definitions
- `queue-posts-list`: inspect all `future` posts in calendar or list form

There is a `render_settings_page()` method, but it is only a placeholder and is not part of the active product surface.

### Editor integrations

Two separate UIs schedule posts through the same underlying PHP logic:

- `js/admin.js` for the classic editor, via `admin-ajax.php`
- `js/block-editor.js` for the block editor, via REST

Both flows expose the same user actions:

- queue for next available slot
- pick a slot from a short list of upcoming available slots

### Queue overview UI

`render_queue_list_page()` reads all future posts and renders:

- a month-by-month calendar
- a grouped list view

`js/calendar-view.js` handles the view toggle. On narrow screens it defaults to list view.

## Key Invariants And Rules

These rules define how the current implementation behaves:

1. Slot definitions are recurring weekly patterns, not dated schedule rows.
2. Slot definitions are expected to be unique by weekly day and local time.
3. Actual queue state is represented by `wp_posts.post_status = 'future'`.
4. Slot availability is computed by exact local datetime collision against existing future posts.
5. Deleting a slot definition does not move or unschedule posts that are already set to publish in the future.
6. Slot management is admin-only (`manage_options`), but queueing is editor-level (`edit_posts`).
7. The UI and APIs expose at most 10 available slots at a time.
8. The plugin does not publish posts itself. It hands off to normal WordPress scheduled publishing once a post is marked `future`.

## Persistence Model

### Custom table

Activation creates `{$wpdb->prefix}qpfp_publication_slots`:

| Column | Type | Purpose |
| --- | --- | --- |
| `id` | `bigint(20)` | stable identifier for a recurring slot definition |
| `day_of_week` | `tinyint(1)` | weekly recurrence day, `1` through `7` |
| `time_of_day` | `time` | local site time for that recurrence |
| `created_at` | `datetime` | audit-lite creation timestamp |

Why this table exists:

- recurring weekly slots do not fit naturally in `wp_posts`
- the plugin needs a reusable schedule template independent of any one post

### Core WordPress tables

The plugin deliberately reuses WordPress core persistence for queued posts:

- `wp_posts.post_status = 'future'`
- `wp_posts.post_date`
- `wp_posts.post_date_gmt`

Why this exists:

- WordPress already knows how to publish scheduled posts
- posts remain normal WordPress posts
- no second "queue item" model has to be synchronized

### Registered options

The plugin registers:

- `qpfp_publication_slots`
- `qpfp_timezone`

In the current codebase these are not the active data source for slot scheduling. Slot rows come from the custom table, and there is no working settings screen that drives these options.

## Data Flows

### 1. Activation

Flow:

- plugin activation calls `activate()`
- `activate()` calls `create_tables()`
- `dbDelta()` creates or updates `qpfp_publication_slots`

### 2. Slot management

Flow:

- admin opens `admin.php?page=queue-posts-slots`
- POST with nonce `qpfp_add_slot` validates the submitted slot and inserts a row into `qpfp_publication_slots`
- POST with nonce `qpfp_delete_slot` deletes a row by `id`

Validation:

- `day_of_week` must be `1..7`
- `time_of_day` must match `HH:MM`
- the `(day_of_week, time_of_day)` combination must not already exist
- accepted `HH:MM` input is normalized to `HH:MM:00` before storage

### 3. Available-slot calculation

The shared method is `get_available_slots($limit = 0)`.

Algorithm:

1. Read all recurring slot definitions from `qpfp_publication_slots`.
2. Read all future posts from `wp_posts` through `get_posts()`.
3. Build a map of taken local datetimes from those future posts.
4. Expand each recurring slot into its next 10 weekly occurrences.
5. Sort all possible occurrences chronologically.
6. Drop occurrences whose exact `Y-m-d H:i:s` datetime is already taken.
7. Return the first N free occurrences.

Important consequence:

- availability is based on datetime occupancy, not on "one post per slot definition"

### 4. Queueing from the editor

Classic editor flow:

- `js/admin.js` calls `qpfp_get_slots` or `qpfp_queue_post`
- PHP handlers are `handle_get_slots_ajax()` and `handle_queue_post_ajax()`

Block editor flow:

- `js/block-editor.js` calls `GET /wp-json/wp/v2/qpfp/slots`
- `POST /wp-json/wp/v2/qpfp/queue`
- PHP handlers are `get_slots_rest()` and `queue_post_rest()`

Scheduling step:

- choose the next slot or a selected slot
- compute local site datetime
- convert to GMT with `get_gmt_from_date()`
- call `wp_insert_post()` with `post_status = future`

### 5. Future-post overview

Flow:

- `render_queue_list_page()` queries all future posts ordered ascending
- groups them by date
- renders every month from the earliest to latest scheduled post
- lets the browser toggle between calendar and list layouts

## State Transitions

The plugin does not define a custom queue state machine. It relies on WordPress post states.

Effective transitions implemented here:

- editable non-`publish`, non-`future` post -> `future` when queued
- `future` -> `publish` later through normal WordPress scheduled publishing

Other notable transitions:

- recurring slot added -> affects future availability calculations only
- recurring slot deleted -> removes future use of that slot pattern only

The plugin does not implement:

- a "queued but not scheduled" state
- automatic rescheduling after slot deletion
- conflict reshuffling

## Authorization Model

There are two different permission boundaries:

### Managing configuration

Admin menu pages require:

- `manage_options`

This means slot management and queue-calendar access are admin-only in the current UI.

### Queueing posts

REST and AJAX queue actions require:

- `current_user_can('edit_posts')`

This means users who can edit posts may be able to queue posts from the editor even if they cannot access the slot-management screens.

### Request protection

- admin forms use `check_admin_referer()`
- AJAX uses `check_ajax_referer('qpfp-queue-nonce', '_ajax_nonce')`
- block editor REST calls use the standard WordPress REST nonce
- there are no public `nopriv` endpoints

## API Architecture

The plugin has two API layers because it supports both editor experiences.

### REST

Routes:

- `GET /wp-json/wp/v2/qpfp/slots`
- `POST /wp-json/wp/v2/qpfp/queue`

Characteristics:

- used by the block editor
- returns JSON objects formatted for UI consumption
- permission callback is `current_user_can('edit_posts')`

### AJAX

Actions:

- `qpfp_get_slots`
- `qpfp_queue_post`

Characteristics:

- used by the classic editor
- mirrors the REST behavior closely
- also protected by nonce and `edit_posts`

### Duplication trade-off

The queueing logic is effectively duplicated between REST and AJAX handlers rather than abstracted into one shared scheduling method. That keeps each endpoint straightforward, but increases maintenance cost and drift risk.

## Performance Decisions

The current implementation favors simplicity over scale.

### Good enough for small editorial queues

- slots table is tiny
- queueing only exposes 10 upcoming slots
- no expensive joins or custom reporting tables

### Scaling limits in current code

- `get_available_slots()` loads all future posts every time slots are requested
- each recurring slot is expanded into 10 candidate weeks before filtering
- `render_queue_list_page()` loads all future posts, then renders every month from first scheduled post to last scheduled post
- there is no pagination, caching, or memoization

This architecture should be fine for modest numbers of future posts and recurring slots, but it will get slower as the queue horizon and post count grow.

## Failure Handling And Edge Cases

### Implemented handling

- add-slot requests validate day and time format
- add-slot requests reject duplicate weekly day/time combinations
- slot insert/delete failures surface through `settings_errors()`
- AJAX queue handlers return explicit JSON errors
- empty slot lists return an empty success payload

### Important current edge cases

1. Slot identity is a recurring-slot ID, not an occurrence ID.

The UI labels individual upcoming datetimes, but the submitted value is only `slot_id`. If the same weekly slot appears more than once in the next 10 available choices, later occurrences are not uniquely addressable. The backend will match the first available occurrence with that slot ID.

2. Conflict-handling text exists, but conflict reassignment does not.

The block-editor localization includes a `slotConflict` message, but there is no implemented flow that reschedules an already-booked post to free a slot.

3. Legacy or unused code is present.

Present but not wired into runtime behavior:

- `schedule_cron_jobs()`
- `unschedule_cron_jobs()`
- hook name `qpfp_check_publication_slots`
- `cleanup_corrupted_locks()`
- `render_settings_page()`

## Security Considerations

Current security posture is reasonable for a small admin plugin:

- direct file access is blocked with a `WPINC` check
- capability checks gate admin screens and queue actions
- nonces protect admin POST and AJAX requests
- admin output is escaped with `esc_html()`, `esc_attr()`, `esc_url()`, and `esc_js()`
- inserts and deletes go through `$wpdb->insert()` and `$wpdb->delete()`
- table creation uses `dbDelta()`

Notable caveats:

- there is no fine-grained authorization by post ownership or post type beyond `edit_posts`
- queue handlers remove all filters from `wp_insert_post_data` and `wp_insert_post` before scheduling, then restore specific callbacks in a narrow way; that is a broad request-scope side effect and may interact poorly with other plugins

## Scalability Constraints

Architectural constraints visible in the current repository:

- most logic lives in one PHP file
- REST and AJAX paths duplicate behavior
- no service layer or repository layer
- no tests or fixtures to lock behavior down
- no asynchronous work beyond normal WordPress scheduled publishing

This is easy to deploy, but harder to extend safely as features grow.

## Testing And Maintenance Notes

There are no automated tests in this repository.

Practical regression areas for manual testing:

- activation creates the custom table
- slot add/delete with valid and invalid input
- classic editor queue-next flow
- classic editor pick-slot flow
- block editor queue-next flow
- block editor pick-slot flow
- queue list calendar rendering across multiple months
- mobile list fallback
- timezone-sensitive scheduling around the current day

Maintenance concerns:

- duplicated scheduling logic in AJAX and REST
- implicit coupling to WordPress editor screens and script handles
- all major behavior concentrated in a single plugin file

## WordPress-Specific Architecture Choices

The implementation leans on common WordPress patterns:

- hooks and callbacks instead of a custom framework
- `register_activation_hook()` for schema setup
- `dbDelta()` for table creation
- admin pages via `add_menu_page()` and `add_submenu_page()`
- classic editor integration via `admin_enqueue_scripts` and `admin-ajax.php`
- block editor integration via `enqueue_block_editor_assets` and REST
- localization through `load_plugin_textdomain()` and `wp_set_script_translations()`
- native scheduled publishing instead of a custom cron worker

Just as important are the choices it does **not** make:

- no custom post type
- no custom queue item post type
- no separate queue processor
- no external dependencies beyond WordPress core assets

## What Exists Now Vs Not Yet Wired

Exists and drives production behavior in this repo:

- custom slot table
- slot CRUD in wp-admin
- classic editor queue UI
- block editor queue UI
- scheduled-post calendar/list
- REST and AJAX scheduling endpoints

Present in code but not functionally part of the product today:

- settings page placeholder
- registered timezone option
- cron helper methods
- corrupted-lock cleanup helper
- conflict-resolution copy without conflict-resolution logic
