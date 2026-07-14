=== Pheasantly Queued Publication ===
Contributors: karol-k
Tags: posts, scheduling, queue, publication, calendar
Requires at least: 5.0
Tested up to: 7.0
Stable tag: 0.40
Requires PHP: 7.4
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Define a recurring weekly publishing cadence and schedule posts into the next open slot without calculating dates manually.

== Description ==

Pheasantly Queued Publication lets editors define a recurring weekly publishing cadence and schedule posts into the next open slot without calculating dates manually.

Keep using WordPress' native scheduled posts, but choose publish times from a reusable queue of weekly slots such as "Monday 13:00" or "Friday 09:30".

The plugin does not create its own queue of post records. It stores slot definitions in one custom table and stores actual scheduled publication state in WordPress core posts with `post_status = future`. WordPress core handles publication in the normal scheduled-post way.

= Features =

* wp-admin screen for managing recurring publication slots
* Validation that prevents duplicate weekly slots with the same day and time
* Queue action in the classic editor
* Queue action in the block editor
* Scheduled-post overview screen with calendar and list views
* Queue a post into the next available slot
* Pick one of the next available upcoming slots
* Occupancy avoidance: datetimes already used by other scheduled posts are not offered

= Usage =

Configure recurring slots at **Queue Posts -> Publication Slots** by adding a day of week and time of day. Each row is a recurring pattern, not a one-off date.

On unscheduled, unpublished posts, use the queue control in the classic or block editor to queue for the next available slot or pick a specific upcoming slot.

Review queued posts at **Queue Posts -> Queued Posts** in calendar or list view.

== Installation ==

1. Upload archive or install from the official directory
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Queue Posts -> Publication Slots** in wp-admin to configure your publication slots.
4. Queue any unscheduled post draft for publication from the post editor.

== Frequently Asked Questions ==

= How do I add a new publication slot? =

Go to **Queue Posts -> Publication Slots**. Select the day of the week and time for the new slot. Click **Add Slot**.

= Can I edit a queued post? =

Yes, you can edit a queued post at any time before it is published. The scheduled datetime stays in place unless you change it through WordPress' normal scheduling tools.

= What happens if I delete a publication slot? =

If you delete a publication slot, that slot will no longer be used for future publications. Posts already scheduled for future publication keep their existing schedule.

== Screenshots ==

1. Publication Slots - screen for configuring publication slots
2. Queued Posts - calendar view
3. The main post scheduling section when editing a post

== Changelog ==

= 0.40 =
* Initial WordPress.org release.
* Queueing to the next slot now fails clearly when no publication slots have been configured yet.
* Duplicate weekly publication slots are rejected when adding a slot.
* Classic and block editors show a setup message when no slots exist yet, instead of broken queue controls.
* Classic editor shows WordPress' native scheduled-post success notice after queueing.
* Publication Slots admin screen to add and delete recurring weekly slots.
* Queued Posts admin screen with calendar and list views of all scheduled posts.
* Queue controls in the classic editor publish box and the block editor post status panel.
* Queue a post for the next available slot or pick from the next upcoming free slots.
* Datetimes already used by another scheduled post are not offered when queueing.
* Scheduling uses native WordPress future posts.

== Upgrade Notice ==

= 0.40 =
Initial WordPress.org release.
