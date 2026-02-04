# Find a Job – Jobs Searcher

Search Find a job API, show results, and persist each job as a standalone page with an “Apply” link.

## Description

This plugin allows your WordPress site to search the DWP Find a Job API. It displays search results and automatically creates a standalone page for each job listing with an "Apply" link.

**Features:**

*   Search interface shortcode `[findajob_search]`.
*   **Customizable Search Forms:** Use the built-in Shortcode Generator to tailor the search widget (e.g., restrict to "Social Care" jobs, hide fields, or redirect results to a specific page).
*   Connects to the official DWP Find a Job API.
*   Caches results for performance.
*   Automatically creates custom post types for job listings.
*   Includes a "Similar Jobs" widget.

## Installation

1.  Download the plugin repository as a ZIP file.
2.  In your WordPress admin dashboard, go to **Plugins > Add New > Upload Plugin**.
3.  Choose the downloaded ZIP file and click **Install Now**.
4.  Activate the plugin.
5.  Go to **Settings > Jobs Searcher** to configure your API ID and Key.
6.  Use the **Shortcode Generator** at the bottom of the settings page to create your search widget, or simply add `[findajob_search]` to any page.

## Shortcode Options

You can customize the `[findajob_search]` shortcode with the following attributes:

*   `cat`: The default category ID (e.g., `177` for Social Care).
*   `fields`: A comma-separated list of visible fields (e.g., `q,w,d`). Available fields: `q` (Keywords), `w` (Location), `d` (Radius), `cat` (Category), `cti` (Hours), `cty` (Contract), `sf` (Salary).
*   `url`: The URL to submit the search to (leave empty to show results on the current page).

**Example:**
`[findajob_search cat="177" fields="w,d" url="/job-results"]`
*Creates a search form restricted to "Social Care" jobs, showing only Location and Radius fields, submitting to `/job-results`.*

## Frequently Asked Questions

### Do I need an API Key?

Yes, you need to register with the DWP Find a Job service to obtain an API ID and API Key.

### How do I display the search form?

Simply add the shortcode `[findajob_search]` to any page or post. Check the settings page for a generator tool.

## Screenshots

1.  The job search interface.
2.  A single job listing page.
3.  The Admin Shortcode Generator.

## Changelog

### 1.1.0
*   Added Shortcode Generator in Admin Settings.
*   Added `cat`, `fields`, and `url` attributes to the shortcode.
*   Added Category dropdown to the search form.

### 1.0.0
*   Initial release.
