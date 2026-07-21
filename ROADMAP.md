# Development

## Status

WordPress plugin: recurring weekly publication slots in a custom table; editors queue drafts into the next or chosen upcoming slot via classic or block editor; scheduling is native `post_status = future` with REST and AJAX endpoints; admins manage slots and review all future posts in a calendar/list screen.

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
- [x] Translation: i18n-wrapped strings, `languages/queue-posts-for-publication.pot` (WordPress.org loads language packs automatically)
- [x] Admin styling: `css/admin.css`

### v0.20 — Smoothing rough edges
- [x] No slots defined fix. 
    - If no publication slots are defined, assigning a post to the **next available slot** must not publish or schedule it immediately (or must fail clearly).
    - Implemented: queueing to the **next available slot** now fails clearly when no publication slots are configured, instead of falling through to an invalid/immediate schedule path.
- [x] Prevent duplicate slots. 
    - Implemented: admin slot creation now rejects duplicate day/time rows so the duplicate-slot bug cannot be introduced going forward.
- [x] Inconsistencies in readme.md - the readme says that a real settings screen doesn't exit.
    - The plugin has its section in the wp-admin where the user can set publication slots.
    - Resolved: removed unused Settings API scaffolding and clarified that `Queue Posts -> Publication Slots` is the active configuration screen.
- [x] Inconsistencies in architecture.md when it comes to WordPress filters.
    - Resolved: queueing no longer strips global `wp_insert_post_data` filters or `wp_insert_post` actions before calling `wp_insert_post()`.
    - Scheduling now uses normal WordPress post-insert hook behavior, avoiding request-scope hook side effects with core, themes, and other plugins.
- [x] UI improvements when no slots available.
    - The interface on the post editing screen, both classic editor and block editor, isn't to optimized in the case when no slots have been defined yet. In that case, the section shouldn't display an option to queue posts and then say that no slots have been defined, but instead just have a quick message for the user to define publication slots first.
    - Implemented: classic and block editor queue controls now show a setup message, with a management link for editors/admins, when no publication slots are configured.
- [x] Success notification in the classic editor.
    - In the classic editor, there's no indication that the post has been successfully queued/scheduled. The UI should use those native green notifications above the post title box.
    - Implemented: classic editor queueing now redirects back to the post edit screen with WordPress' native scheduled-post success notice.

### v0.30 — Tightening
- [x] Potential refactor.
    - Shared queue path for REST and AJAX: `resolve_queue_slot()`, `schedule_post_on_available_slot()`.
    - Shared slot labels for REST, AJAX, and classic dropdown: `format_available_slots_for_ui()`, `get_weekday_labels()`.
    - Occupancy map via `get_taken_slot_datetimes()` (one `get_posts()` for all `future` posts; keys from `post_date`).
    - Server code under `includes/` (`Queue_Posts_For_Publication` + traits); bootstrap in `queue-posts-for-publication.php`; `QPFP_PLUGIN_FILE` for activation and textdomain.

### v0.40 — WordPress.org release (Queue Posts for Publication → Easy Publication Queue → Pheasantly Queued Publication)
- [x] WordPress.org submission.
- [x] Rename for Plugin Directory review: display name **Pheasantly Queued Publication**, slug `pheasantly-queued-publication` (was **Queue Posts for Publication** / `queue-posts-for-publication`). Change this name in all related places - slugs, text domain, etc.
- [x] Deploy to WordPress.org SVN.

### v0.50
- [ ] Add automatic reshuffling.
    - Make it possible for posts to take over slots of other posts that have already been scheduled, which means reshuffling the other scheduled posts further - by one slot each.
    - I.e. make this example scenario possible: “I want this Monday 1pm slot even though another post is already scheduled there - move that other post to the next free slot.”
    - There should be a function slotConflict() that is already kind of a placeholder for this.

### Backlog / Future

- `get_available_slots()` early-exit or caching when `future` post volume is large
- Queued Posts overview pagination or month windowing

## Known Issues / Tech Debt

- No automated tests in the repository.
- `get_available_slots()` loads all future posts on each slots request; each slot definition still expands 10 weekly candidates before filtering.
- No pagination on the Queued Posts calendar/list screen.
- Block editor includes `slotConflict` copy without a full conflict-reassignment flow.
- Editor JavaScript remains two clients (`admin.js`, `block-editor.js`); PHP queue rules are shared.

## Decisions Pending

- TBA
