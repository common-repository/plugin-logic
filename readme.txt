=== Plugin Logic ===
Contributors: simon_h
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=SMX67F2CZLDZL
Tags: deactivate plugins by url, activate plugins by url, deactivate plugins by rules, disable plugins by page, disable plugins by rules 
Requires at least: 3.8
Tested up to: 6.6.1
Stable tag: 1.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Url based plugin deactivation or activation.

== Description ==

A possibility to increase the speed of your Wordpress page is to deactivate Plugins on pages, 
where they are not needed. This Plugin allows you to do this on a easy way. 
So you can reduce the amount of JavaScript and CSS files which are loaded and SQL queries run at page load.

== Installation ==

1. Install the Plugin
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Create your own rules on the Plugin Logic options page

== Frequently Asked Questions ==

**Q. How do I format my rules?**

Use "http://" or "https://" at the beginning to define urls. 
Words without these keywords at the beginning are interpreted as normal words. They will be searched in the requested url.
To separate your rules use the comma sign.
To use regex it is necessery to wrap a rule with quotations like this: "wordpress\/\?p=[1-7]$"

== Screenshots ==

1. The settings page.
2. Options for the behavior on dashbord.

== Changelog ==

= 1.1.0 =
* Validate regex rule syntax before saving to file
* Translation for error messages

= 1.0.9 =
* Regex support for rules (See FAQ)
* Switched to WP_Filesystem for file handling
* Switched to WP_Error for error handling
* Added admin_notices to display error messages
* Switched from serialized data to JSON format
* Added a process to update the database table
* Redesigned most code after current WordPress Coding Standards
* Seperated some code into functions for better testing with PHPUnit

= 1.0.8 =
* Removed multisite support due to security issue
* Security hardening 

= 1.0.7 =
* Added Primary Key for database table

= 1.0.6 =
* Delete rules of uninstalled plugins 
* Some code enhancements

= 1.0.4 =
* Multisite support 
* Several code changes
* Screenshot for the multisite settings page

= 1.0.3 =
* Fixed important variable name for database table

= 1.0.2 =
* First translation added (german)
* Transform rule input to lowercase
* Several code enhancements

= 1.0.1 =
* Fixed uninstall
* Standard WP table charset and collate added 

= 1.0.0 =
* Initial release
