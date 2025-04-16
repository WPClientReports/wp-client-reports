=== WP Client Reports ===
Contributors: wpclientreports, causelabs, mikegillihan
Tags: reports, client reports, maintenance reports, analytics, client dashboard
Requires at least: 5.3.0
Tested up to: 6.8
Stable tag: 1.0.24
Requires PHP: 5.6.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

The best maintenance reporting tool for WordPress professionals. Display update statistics directly in the WordPress admin or send reports via email.

== Description ==

WP Client Reports is a powerful plugin designed for WordPress freelancers, agencies, and website managers who provide care and maintenance services for their clients and stakeholders.

== Streamline Your Maintenance Reporting ==

This essential tool tracks WordPress core, plugin, and theme updates on the fly and provides detailed, professional reports directly inside the WordPress admin dashboard.

You and your clients can quickly switch between different time periods to view update statistics, giving you and them a clear, on-demand understanding of maintenance activities.

== Email Maintenance Reports to Key Stakeholders ==

Keeping your clients and other site stakeholders updated with a detailed, professional-looking email report is a simple point-and-click! The are no PDFs here, just a nicely designed email.

> <strong>WP Client Reports Pro</strong><br />
> This plugin is the lite version of WP Client Reports Pro, which includes custom branding, automated report scheduling, maintenance notes, and many other integrations.<br />
> [Click here to purchase the best WordPress reporting plugin now!](https://wpclientreports.com/?utm_source=wporg&utm_medium=link&utm_campaign=wpclientreports&utm_content=readme)

== — Pro Version — ==

[WP Client Reports Pro](https://wpclientreports.com?utm_source=wporg&utm_medium=link&utm_campaign=wpclientreports&utm_content=readme) takes your client reporting to the next level. With this upgrade, you can brand your maintenance reports with your logo and company color, schedule automatic report delivery, and integrate additional statistics from a variety of services and plugins.

[Click here to purchase WP Client Reports Pro now!](https://wpclientreports.com/pricing/?utm_source=wporg&utm_medium=link&utm_campaign=wpclientreports&utm_content=readme)

= Pro Version Features =

- **Custom Branding**: Personalize your reports with your company's branding, adding a professional touch to your communications.
- **Scheduled Reports**: Set up automatic delivery of reports on a weekly or monthly basis, ensuring consistent and timely updates for your clients.
- **Extended Integrations**: Enhance your reports with additional statistics from:
  - Site Maintenance Notes
  - Google Analytics
  - Form Plugins (Gravity Forms, Ninja Forms, WP Forms, Formidable Forms, Contact Form 7)
  - Uptime Monitoring (Uptime Robot, Pingdom)
  - Backup Solutions (UpdraftPlus, BackWPup, BackupBuddy, WPEngine Backups)
  - Email Marketing (Mailchimp)
  - Search Optimization (SearchWP)
  - E-commerce and Donations (WooCommerce, Easy Digital Downloads, GiveWP, Stripe)

== The Perfect Solution for WordPress Professionals ==

- **Elevate Your Brand**: Stand out with reports that reflect your company's identity.
- **Streamline Your Workflow**: Automate report delivery, saving you time and effort.
- **Provide Comprehensive Insights**: Offer clients a complete overview of their website's performance and your maintenance activities.

Have an idea that should be added? Submit a feature request at [wpclientreports.com](https://wpclientreports.com/feature-requests/?utm_source=wporg&utm_medium=link&utm_campaign=wpclientreports&utm_content=readme).

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/wp-client-reports` directory, or install the zipped plugin through the WordPress plugins screen directly.
2. Activate the WP Client Reports plugin through the 'Plugins' screen in WordPress.
3. Use the Settings->WP Client Reports screen to configure the default settings.
4. Use the Dashboard->Reports screen to view update statistics.

== Frequently Asked Questions ==

= Where Are My Past Updates? =

WordPress, by default, does not track when updates have happened. WP Client Reports adds the functionality to do that, but it cannot report updates that happened before the plugin was installed.

== Screenshots ==

1. The WP Client Reports main report screen
2. Easily switch dates to view states for any time period
3. Send an html email with friendly statistics to your client
3. Adds a handy dashboard widget
4. Manage email settings and which sections are enabled

== Changelog ==

= 1.0.24 =
* Replace all links to switchwp.com with wpclientreports.com
* Update link to GitHub repo
* Fix typos in admin notifications.
* Tested up to WordPress v6.8
* Update readme

= 1.0.23 =
* Improve code formatting
* Update included moment.min.js to 2.30.1
* Add nonce verification when sending email report
* Update readme

= 1.0.22 =
* Tested up to 6.4
* Update readme

= 1.0.21 =
* Update readme

= 1.0.20 =
* Fixed css issue with datepicker

= 1.0.19 =
* Fixed timezone issues
* Fixed display issue near bottom of report page

= 1.0.18 =
* Added advanced filter to optionally hide plugin update details

= 1.0.17 =
* Added Last 90 Days option in date selector
* Increased security

= 1.0.16 =
* Fix issues with datepicker styling
* Update moment.js

= 1.0.15 =
* Add optional "Reply To" field for email settings
* Fix issue with report intro removing paragraphs and line breaks

= 1.0.14 =
* Ensure that jquery ui calendar next/prev month buttons are visible regardless of conflicts with other plugins

= 1.0.13 =
* Make report title a required field
* Update moment.js

= 1.0.12 =
* Fix an issue with formatting of dates in reports where placeholders [YEAR], [MONTH], and [DAY] are used
* Bumped WP version requirement to 5.3.0 related to use of wp_timezone() function

= 1.0.11 =
* Fix issues with meta box headers in newer versions of WordPress

= 1.0.10 =
* New Feature: Ability to use [YEAR], [MONTH], and [DATE] shortcodes in email title and description.
* New Feature: Loading spinners while reports are loading
* New Feature: New fields for email: Send From Email, Send From Name, and Footer
* Fix issues with timezones using UTC offsets
* Fix issues with HTML in email titles and descriptions
* Fix some untranslatable strings
* Fix some issues with settings screen when Easy Digital Downloads is installed

= 1.0.9 =
* Change the way reports are sent to allow for sending via schedules in Pro version

= 1.0.8 =
* Fix some issues with calendar when jquery-ui styles are enqued by another plugin

= 1.0.7 =
* Fix issues with description formatting
* Fix issue when selecting single date in chooser

= 1.0.6 =
* Fix saving issue with default report title

= 1.0.5 =
* Check for updates after each plugin/theme update

= 1.0.4 =
* Allow title of report to be customized
* Fix issue with sending to multiple email addresses

= 1.0.3 =
* Restructure parts of the plugin for more consistency
* Add content stats section

= 1.0.2 =
* Rethink data loading for report page and email

= 1.0.1 =
* Security enhancements
* Readying plugin for pro version

= 1.0.0 =
* Initial Version

== Upgrade Notice ==

= 1.0.0 =
Initial Version
