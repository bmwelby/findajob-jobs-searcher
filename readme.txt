=== Find a Job – Jobs Searcher ===
Contributors: benwelby
Tags: jobs, recruitment, dwp, search, api
Requires at least: 6.0
Tested up to: 6.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Search Find a job API, show results, and persist each job as a standalone page with an “Apply” link.

== Description ==

This plugin allows your WordPress site to search the DWP Find a Job API. It displays search results and automatically creates a standalone page for each job listing with an "Apply" link.

**Features:**

*   Search interface shortcode `[findajob_search]`.
*   Connects to the official DWP Find a Job API.
*   Caches results for performance.
*   Automatically creates custom post types for job listings.
*   Includes a "Similar Jobs" widget.

== Installation ==

1.  Upload the plugin files to the `/wp-content/plugins/findajob-jobs-searcher` directory, or install the plugin through the WordPress plugins screen directly.
2.  Activate the plugin through the 'Plugins' screen in WordPress.
3.  Go to the plugin settings page to configure your API ID and Key (obtained from the DWP Find a Job service).
4.  Add the `[findajob_search]` shortcode to any page to display the search form.

== Frequently Asked Questions ==

= Do I need an API Key? =

Yes, you need to register with the DWP Find a Job service to obtain an API ID and API Key.

= How do I display the search form? =

Simply add the shortcode `[findajob_search]` to any page or post.

== Screenshots ==

1.  The job search interface.
2.  A single job listing page.

== Changelog ==

= 1.0.0 =
*   Initial release.
