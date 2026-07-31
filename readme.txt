=== Pheasantly - Post Queue & Recurring Publishing Schedule ===
Contributors: karol-k
Tags: editorial calendar, content calendar, schedule posts, post scheduler, queue
Requires at least: 5.0
Tested up to: 7.0.2
Stable tag: 0.43
Requires PHP: 7.4
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Set a weekly publishing schedule, then queue drafts to fill the next open slot automatically. A simple editorial calendar of scheduled posts.

== Description ==

Pheasantly turns WordPress scheduling into a queue - recurring weekly.

Instead of opening the date picker and working out when your next post should go live, you define your publishing rhythm once - say Tuesday 9:00 AM and Friday 2:00 PM - and then queue drafts into it. 

Each post takes the next free slot. If you've ever used a social media scheduling queue, this is the same idea applied to your blog.

https://www.youtube.com/watch?v=9w5P-ffQmqg

= Who this is for =

* Bloggers who write in batches and want posts trickling out on a consistent schedule
* Editors managing a content calendar across several contributors
* Anyone whose publishing schedule is a fixed weekly rhythm rather than a series of one-off dates

= How it's different from scheduling posts natively =

WordPress can already schedule a post for a specific date and time. What it can't do is remember your schedule. Every post means picking a date, checking what's already scheduled, and doing the arithmetic yourself.

Pheasantly stores your weekly slots as reusable patterns and fills them in order. Queue five drafts on a Sunday afternoon and they'll go out over the next two and a half weeks without you touching a calendar.

Underneath, these stay ordinary WordPress scheduled posts. Pheasantly just picks the date, core handles publication. Deactivate the plugin and everything you've already queued still publishes exactly as scheduled.

= Features =

* wp-admin screen for managing recurring publication slots for your content workflow
* Validation that prevents duplicate weekly slots with the same day and time
* Queue action in the classic editor
* Queue action in the block editor
* Scheduled-post overview screen with calendar and list views, useful as a lightweight editorial calendar or content calendar
* Queue a post into the next available slot
* Pick one of the next available upcoming slots when you need to schedule blog posts in advance
* Occupancy avoidance: datetimes already used by other scheduled posts are not offered
* A WordPress post queue for editors who want to schedule posts on a recurring publishing queue without calculating dates manually

= Getting started =

1. Go to **Queue Posts -> Publication Slots**.
2. Configure recurring slots by adding a day of week and time of day. Each row is a recurring pattern, not a one-off date.
3. Open an unpublished, unscheduled post.
4. Find **Queue for publication** in the post status or publish area (works both in classic and block editor).
5. Choose **Queue for next slot** or **Pick a slot**. This will queue for the next available slot or your selected specific upcoming slot.
6. Review scheduled posts at **Queue Posts -> Queued Posts** in calendar or list view. The post scheduling calendar shows every future post on the site, not only posts queued through this plugin.

= Usage scenarios =

📅 **How to create a weekly publishing schedule**

Go to **Queue Posts -> Publication Slots**. Select a weekday and time, then click **Add Slot**. Repeat this for every regular publishing time.

Each row is a repeating weekly pattern rather than a one-time event. For example, Wednesday at 9:00 AM makes future Wednesdays at 9:00 AM available to the queue.

You can add multiple times on the same day, such as Tuesday at 9:00 AM and Tuesday at 3:00 PM. You cannot add the exact same weekday and time twice.

➕ **How to queue a post into the next open slot**

Open an unpublished, unscheduled post in the block editor or classic editor. Open the **Queue for publication** options and select **Queue for next slot**.

Pheasantly finds the earliest configured time that is in the future and is not already occupied. The post then becomes a normal WordPress scheduled post.

🎯 **How to choose a specific upcoming slot**

Open the queue options and choose **Pick a slot**. Select a date and time, then confirm your choice.

The picker shows up to 10 upcoming free dates. Occupied dates are omitted. If the date you want is absent, another post may already be scheduled for that exact time, or the date may fall beyond the choices currently shown.

📚 **How to queue several drafts**

Queue ready drafts in the order in which you want them published. Use **Queue for next slot** on each draft.

After the first draft takes the earliest free date, the next draft takes the next free date, and so on. Review the final order under **Queue Posts -> Queued Posts**.

👀 **How to review upcoming publications**

Go to **Queue Posts -> Queued Posts**. Use the calendar to see your schedule across months, or use **Toggle View** to switch to the grouped list. Smaller screens use the list view by default.

Post titles link to their editor screens. The overview includes every future post on the site, including posts scheduled manually or by another plugin.

✏️ **How to change or remove a queued post**

A queued post is an ordinary WordPress scheduled post. Open it and use the standard WordPress scheduling controls to change its publication date and time.

To remove it from the schedule, change it back to a non-scheduled status such as Draft and save it. You can queue it again later.

🔁 **How to change the recurring schedule**

Go to **Queue Posts -> Publication Slots** and delete a recurring time you no longer need. Add a replacement slot if required.

Deleting a recurring slot only affects future queue choices. It does not move or cancel posts that are already scheduled.

== Installation ==

1. Upload archive or install from the official directory
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Queue Posts -> Publication Slots** in wp-admin to configure your publication slots.
4. Queue any unscheduled post draft for publication from the post editor.

== Frequently Asked Questions ==

= What is a publication slot? =

A publication slot is a reusable weekday-and-time pattern, such as Tuesday at 10:00 AM. It repeats weekly. When you queue a post, Pheasantly turns the pattern into a real future date.

= How do I add a new publication slot? =

Go to **Queue Posts -> Publication Slots**. Select the day of the week and time for the new slot. Click **Add Slot**.

= Can I edit a queued post? =

Yes, you can edit a queued post at any time before it is published. The scheduled datetime stays in place unless you change it through WordPress' normal scheduling tools.

= Who can manage slots and queue posts? =

Users with the `manage_options` capability (Administrators by default) can manage publication slots and view the scheduled-post overview. Queueing a post also requires `edit_posts` (Editors) and permission to edit that specific post.

= Can I choose a datetime already used by another scheduled post? =

No. Occupied datetimes are not offered as available slots, and the plugin does not move or reschedule other posts.

= What happens if I delete a publication slot? =

If you delete a publication slot, that slot will no longer be used for future publications. Posts already scheduled for future publication keep their existing schedule.

= What is the difference between "Queue for next slot" and "Pick a slot"? =

**Queue for next slot** immediately schedules the post at the earliest available configured time. **Pick a slot** lets you choose from up to 10 upcoming available dates.

= Which timezone does Pheasantly use? =

Pheasantly uses the timezone configured under **Settings -> General**. Check this setting before creating your schedule, especially if team members work in different timezones.

= Does Pheasantly publish posts itself? =

No. Pheasantly selects a date and turns the post into a normal WordPress scheduled post. WordPress handles publication at the scheduled time.

= Is a queued post different from a scheduled post? =

No. "Queued" describes how its date was selected. After queueing, WordPress stores and handles it as a regular scheduled post.

= Can I manually reschedule a queued post? =

Yes. Open the post and change its date and time using the standard WordPress scheduling controls. The manually selected time does not have to match a Pheasantly slot.

= Can I remove a post from the queue? =

Yes. Open the scheduled post, change it back to Draft or another non-scheduled status, and save it. The date it occupied will become available again.

= What happens when a slot is occupied? =

Pheasantly skips that occurrence and offers the next free one. It does not displace or automatically reschedule the post already there.

= Does changing a slot move posts that were already queued? =

No. Slot changes affect later queue choices only. Existing scheduled posts keep their dates until you edit them.

= Does the Queued Posts screen show only posts queued with Pheasantly? =

No. It shows all future posts on the site. It does not identify which tool originally scheduled each post.

= Can I use both the block editor and classic editor? =

Yes. Both editors let you queue for the next slot or pick a specific upcoming slot.

= Why does the picker show only 10 dates? =

The picker intentionally shows up to 10 upcoming available dates. Use **Queue for next slot** when you simply want the earliest one.

= Can I add more than one slot on the same day? =

Yes. For example, Tuesday at 9:00 AM and Tuesday at 3:00 PM are separate valid slots. Only exact duplicates are rejected.

= What happens if I deactivate the plugin? =

Already queued posts remain normal WordPress scheduled posts, so deactivating Pheasantly does not cancel them. Pheasantly's queue controls and admin screens are unavailable while it is inactive.

== Troubleshooting ==

= The queue control is missing =

Confirm that the plugin is active and that you can edit the post.

The control is intentionally hidden for posts that are already published or scheduled. Use WordPress' normal scheduling controls for a scheduled post, or change it back to Draft before queueing it again.

Also confirm that at least one slot exists under **Queue Posts -> Publication Slots**. With no slots configured, the editor displays a setup message instead of the queue actions.

= "Define publication slots before queueing posts" appears =

No recurring slots have been configured. Ask an Administrator to add at least one slot under **Queue Posts -> Publication Slots**. Editors may be able to queue posts without having permission to manage slots.

= "No slots available" appears =

Confirm that slots have been configured. If they have, upcoming occurrences may already be occupied.

Open **Queue Posts -> Queued Posts** or the standard Posts screen and look for scheduled posts at the configured times. Reschedule an existing post, add another recurring slot, or try again later.

= A slot I expected is not in the picker =

Check that the expected time is still in the future according to the site timezone, that the recurring slot still exists, and that another post is not scheduled for the exact same date and time.

The picker displays only the next 10 available dates, so a later occurrence may not appear yet.

= "Selected slot not available" appears =

Availability changed after you opened the picker, usually because another post took that time. Reopen **Pick a slot** to refresh the list and choose another date.

= The post was queued at an unexpected time =

Check the site timezone, date format, and time format under **Settings -> General**. The WordPress site timezone may differ from your computer's timezone.

Correct the post using the normal WordPress scheduling controls. Update the site timezone or recurring slots before queueing more posts.

= A deleted slot still has a post scheduled on it =

This is expected. Deleting a slot prevents later use of that weekly pattern; it does not cancel or move existing scheduled posts. Edit the affected post if you want to change it.

= An Editor can queue posts but cannot manage slots or view the overview =

This is expected with the default permissions. Administrators normally manage slots and view the overview, while users who can edit a post may queue that post.

= A scheduled post did not publish on time =

After queueing, WordPress is responsible for publication. Scheduled publishing normally depends on WP-Cron, which is triggered by site traffic unless your host provides a server cron.

Check the post's date, the site timezone, and whether other scheduled WordPress tasks are also late. If they are, ask your hosting provider or site administrator to investigate the site's cron configuration.

= The Queued Posts screen contains posts I did not queue =

This is expected. It lists all future posts, including posts scheduled manually or by other plugins.

= I cannot add a slot =

Select both a weekday and a valid time. If WordPress says the slot already exists, check **Current Slots** for the same weekday and time. Exact duplicates are not allowed.

= Queueing failed unexpectedly =

Reload the editor and try again. The post may have been published, scheduled elsewhere, moved to Trash, or changed by another editor while the page was open.

If the problem continues, confirm that WordPress normally lets you edit and schedule that post. A site administrator can then check for plugin conflicts and review the WordPress debug log.

= Resources and tips =

* Plugin roadmap on [GitHub](https://github.com/carlosinho/queue-posts-for-publication)
* Guides and WordPress tutorials on [WP Workshop](https://www.youtube.com/@wpworkshophq) (YouTube)
* Reach the author at [Karol.cc](https://karol.cc/)

== Screenshots ==

1. Publication Slots – screen for configuring publication slots
2. Queued Posts – calendar view
3. Schedule post for next slot (block editor)
4. Pick a slot from the list (classic editor)

== Changelog ==

= 0.43 =
* UI improvements.

= 0.42 =
* UI improvements.

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

= 0.43 =
Tweaks UI mildly.

= 0.42 =
Tweaks UI to be more smooth.

= 0.40 =
Initial WordPress.org release.
