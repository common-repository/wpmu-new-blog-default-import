=== WPMU DEFAULT CONTENT IMPORT ===
Contributors: Md. Mahabubur Rahman
Donate link: http://solvease.com/donate/
Tags: Wpmu default import, wordpress mu import, default content in wpmu, deafult conetnt import
Requires at least: 2.7.0
Tested up to: 2.9.2
Stable tag: 1.0

This Plugin is used to import default contents when new blog will be created in wpmu.

== Description ==
This Plugin is used to import default contents when new blog will be created in wpmu.


== Installation ==

1. in wpmu_default folder open the file wpmu_default.php and go to line number 457
and specify the xml file url.
$_file_url = ABSPATH . "wp-content/plugins/wpmu_default/your_file.xml";
put the file in wpmu_default folder.

2. Upload `wpmu_default` foldet to the `/wp-content/plugins/` directory
3. go to the directory wp-includes and open the file capabilities.php
   in line number two add the below codes
   require_once('pluggable.php');
4. Go to admin Pnale and activate the plugin

ALL SET AND YOUR ARE DONE!!

== Screenshots ==


== Changelog ==


== Upgrade Notice ==


