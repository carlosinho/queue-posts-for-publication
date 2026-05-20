# Queue Posts for Publication

`Queue Posts for Publication` is a WordPress plugin that lets editors define a recurring weekly publishing cadence and then schedule posts into the next open slot without calculating dates manually.

It exists to solve a very specific workflow: keep using WordPress' native scheduled posts, but choose publish times from a reusable queue of weekly slots such as "Monday 13:00" or "Friday 09:30".

## What It Does Now

The current implementation provides:

- A wp-admin screen for managing recurring publication slots
- Validation that prevents creating duplicate weekly slots with the same day and time
- A queue action in the classic editor
- A queue action in the block editor
- A scheduled-post overview screen with calendar and list views
- Internal REST and AJAX endpoints used by the editor UI

The plugin does **not** create its own queue of post records. It stores slot definitions in one custom table and stores actual scheduled publication state in WordPress core `wp_posts` rows with `post_status = future`.

## Main User Flows

### 1. Configure recurring slots

Open `Queue Posts -> Publication Slots` in wp-admin and add one or more weekly slots:

- day of week
- time of day

Each row is a recurring pattern, not a one-off date.

The plugin prevents duplicate slot definitions for the same weekly day and time.

### 2. Queue a post from the editor

On unscheduled, unpublished posts:

- Classic editor: a "Queue for publication" section is injected into the publish box
- Block editor: a queue control is added through the post status panel

From there you can:

- queue the post into the next available slot
- pick one of the next available upcoming slots exposed by the plugin

When queued, the plugin updates the post to:

- `post_status = future`
- `post_date = selected local site time`
- `post_date_gmt = GMT equivalent`

Only datetimes with no other `future` post at that local time are offered or accepted. The plugin does not move or reschedule other posts to free a slot.

After that, WordPress core handles publication in the normal scheduled-post way.

### 3. Review queued posts

Open `Queue Posts -> Queued Posts` to see all future posts:

- calendar view on larger screens
- list view toggle
- mobile defaults to list view

## Tech Stack

- WordPress plugin, loaded from `queue-posts-for-publication.php`
- PHP for all server-side logic
- WordPress admin pages and hooks
- `admin-ajax.php` for the classic editor flow
- WordPress REST API for the block editor flow
- jQuery for classic-editor and calendar interactions
- WordPress block editor packages provided by core script handles
- One custom database table: `{$wpdb->prefix}qpfp_publication_slots`
- Translation support via `load_plugin_textdomain()` and `languages/queue-posts-for-publication.pot`

There is no `composer.json`, `package.json`, build step, Docker setup, CI pipeline, or deployment automation in this repository.

## Setup

### Install

Standard install process for WordPress plugins.

### What activation creates

On activation, the plugin creates:

- `{$wpdb->prefix}qpfp_publication_slots`

Schema:

- `id bigint(20) auto_increment`
- `day_of_week tinyint(1) not null`
- `time_of_day time not null`
- `created_at datetime not null default current_timestamp`

### Environment variables

The plugin does not define or require any plugin-specific environment variables.

It relies on the host WordPress install for:

- database credentials from WordPress configuration
- site timezone/date/time settings
- WordPress auth cookies and nonces

### Site settings it uses

The implementation reads these WordPress settings:

- `date_format`
- `time_format`
- `start_of_week`

Publication slot configuration is managed through `Queue Posts -> Publication Slots` and stored in the custom slots table. The plugin does not register plugin-specific options.

## API And Integration Points

These are internal plugin endpoints used by the editor UIs.

### REST API

- `GET /wp-json/wp/v2/qpfp/slots`
- `POST /wp-json/wp/v2/qpfp/queue`

`POST /wp-json/wp/v2/qpfp/queue` accepts:

- `post_id` (required)
- `slot_timestamp` (optional, concrete available occurrence timestamp)
- `slot_id` (optional, legacy recurring slot fallback)

Permissions:

- requires `current_user_can('edit_posts')`
- uses the WordPress REST nonce localized into the block editor script

### AJAX

- `action=qpfp_get_slots`
- `action=qpfp_queue_post`

Permissions:

- requires `current_user_can('edit_posts')`
- requires AJAX nonce `qpfp-queue-nonce`

### Admin screens

- `admin.php?page=queue-posts-slots`
- `admin.php?page=queue-posts-list`

These menu pages require `manage_options`.

## Project Structure

```text
queue-posts-for-publication/
├── queue-posts-for-publication.php   # Plugin bootstrap, hooks, admin pages, REST, AJAX, slot logic
├── css/
│   └── admin.css                     # Admin/editor/calendar styling
├── js/
│   ├── admin.js                      # Classic editor queue UI
│   ├── block-editor.js               # Block editor queue UI
│   └── calendar-view.js              # Calendar/list toggle on queued-posts screen
├── languages/
│   └── queue-posts-for-publication.pot
├── README.md
└── readme.txt
```

## Troubleshooting

- No queue button in the editor: the plugin hides queue controls for posts already `publish` or `future`.
- No queue button in the classic editor: the script only loads on `post.php` and `post-new.php`.
- Cannot add a slot that looks valid: duplicate weekly day/time combinations are rejected.
- A slot was deleted but queued posts stayed scheduled: this is expected. Deleting a recurring slot only affects future slot selection; existing `future` posts are not moved.
- An editor can queue posts but cannot manage slots: this is expected. queueing requires `edit_posts`, but slot management pages require `manage_options`.
- Looking for configuration: publication slots are managed at `Queue Posts -> Publication Slots`.

## Current Scope

What exists now:

- recurring weekly slot definitions
- duplicate-slot prevention for recurring slot definitions
- occupancy avoidance when queueing (occupied datetimes are not offered and cannot be selected through the plugin)
- editor-side queue controls
- scheduled-post overview
- REST and AJAX scheduling endpoints
- native WordPress scheduled publishing

What does not exist in this repository:

- a public frontend
- a custom publish worker
- automated tests
- override or reshuffling: taking an occupied datetime by moving another scheduled post, or automatically reshuffling the queue when slots fill up
