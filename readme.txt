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

1.  Download the plugin repository as a ZIP file.
2.  In your WordPress admin dashboard, go to **Plugins > Add New > Upload Plugin**.
3.  Choose the downloaded ZIP file and click **Install Now**.
4.  Activate the plugin.
5.  Go to the plugin settings page to configure your API ID and Key (obtained from the DWP Find a Job service).
6.  Add the `[findajob_search]` shortcode to any page to display the search form.

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
