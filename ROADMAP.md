# Development

## Status

WordPress plugin **v0.11**: recurring weekly publication slots in a custom table; editors queue drafts into the next or chosen upcoming slot via classic or block editor; scheduling is native `post_status = future` with REST and AJAX endpoints; admins manage slots and review all future posts in a calendar/list screen.

## Roadmap

### v0.10 — Working plugin - main logic

- [x] Custom table `{$wpdb->prefix}qpfp_publication_slots` created on activation (`dbDelta`): `day_of_week`, `time_of_day`, `created_at`
- [x] **Publication Slots** admin screen: add and delete recurring weekly slots (day + local time); validation for day 1–7 and `HH:MM`
- [x] **Queued Posts** admin screen: all `future` posts, month calendar + grouped list, toggle via `calendar-view.js`; list default on narrow viewports
- [x] Classic editor: queue UI in publish box (`admin.js`), loads on `post.php` / `post-new.php`; AJAX `qpfp_get_slots`, `qpfp_queue_post` with nonce
- [x] Block editor: queue control in post status panel (`block-editor.js`); REST `GET /wp-json/wp/v2/qpfp/slots`, `POST /wp-json/wp/v2/qpfp/queue`
- [x] Queue actions: assign to **next available** slot or pick from **up to 10** upcoming free slots (`get_available_slots()` — datetime collision against existing future posts)
- [x] Scheduling: `wp_insert_post()` with `future`, local `post_date` / GMT via `get_gmt_from_date()`
- [x] Capabilities: slot/overview menus `manage_options`; queue actions `edit_posts`
- [x] Translation: `load_plugin_textdomain()`, `languages/queue-posts-for-publication.pot`
- [x] Admin styling: `css/admin.css`

### v0.12 — Tightening

- [x] No slots defined fix. 
    - If no publication slots are defined, assigning a post to the **next available slot** must not publish or schedule it immediately (or must fail clearly).
    - Implemented: queueing to the **next available slot** now fails clearly when no publication slots are configured, instead of falling through to an invalid/immediate schedule path.
- [x] Prevent duplicate slots. 
    - Implemented: admin slot creation now rejects duplicate day/time rows so the duplicate-slot bug cannot be introduced going forward.
- [ ] Inconsistencies in readme.md about conflict resolution.
    - The readme says that there's no conflict resolution or automatic reshuffling when a slot is already occupied.
    - Do we need that? What it means? Is it a situation likely to happen?
- [ ] Inconsistencies in readme.md - why does the readme say that a real settings screen doesn't exit?
    - The plugin has its section in the wp-admin where the user can set publication slots.
- [ ] Inconsistencies in architecture.md when it comes to WordPress filters. 
    - From the architecture doc: Queue handlers remove all filters from `wp_insert_post_data` and `wp_insert_post` before scheduling, then restore specific callbacks in a narrow way; that is a broad request-scope side effect and may interact poorly with other plugins
    - Why is that a real problem?
- [ ] Inconsistencies in architecture.md when it comes to implemented features.
    - This is about the section from the architecture doc: "Present in code but not functionally part of the product today:"
    - I'm not sure all of those are a bug; analyze if they're maybe part of the core idea of the plugin and how it's meant to work.

### v0.20 — TBD

### Backlog / Future

- Items not committed here; see README *Current Scope* / *Troubleshooting* and ARCHITECTURE for documented gaps (e.g. no tests, no real settings UI, no conflict reshuffling, occurrence-level slot picking).

## Known Issues / Tech Debt

- REST and AJAX duplicate queueing logic; drift risk (ARCHITECTURE).
- No automated tests in the repository.
- `render_settings_page()` and registered options `qpfp_publication_slots` / `qpfp_timezone` are not the active product path; slots live in the custom table only.
- Picker identifies choices by recurring **slot id**, not occurrence timestamp — later occurrences of the same weekly slot are not uniquely selectable (README *Troubleshooting*).
- `get_available_slots()` loads all future posts each time; no pagination/caching on the queue overview.
- Block editor includes `slotConflict` copy without a full conflict-reassignment flow.
- Dead or stub code paths: `schedule_cron_jobs`, `unschedule_cron_jobs`, `qpfp_check_publication_slots`, `cleanup_corrupted_locks`, settings page.
- `wp_insert_post_data` / `wp_insert_post` filter removal during queueing may interact with other plugins.

## Decisions Pending

- ...
