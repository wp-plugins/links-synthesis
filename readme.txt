=== Links synthesis ===

Author: sedLex
Contributors: sedLex
Author URI: http://www.sedlex.fr/
Plugin URI: http://wordpress.org/plugins/links-synthesis/
Tags: tag
Requires at least: 3.0
Tested up to: 3.9
Stable tag: trunk

This plugin enables a synthesis of all links and the creation of thumbnail for links in an article and retrieves data from them. 

== Description ==

This plugin enables a synthesis of all links and the creation of thumbnail for links in an article and retrieves data from them. 

In this plugin, an index of all links in the page/post is created at the end of the page/post. 

In addition, each link is periodically check to see if the link is still valid. 

p&gt;In addition, each link is periodically check to see if the link is still valid. 

You may dispaly a thumbnail of the URL when the user move its mouse over the link.

This plugin is under GPL licence. 

= Multisite - Wordpress MU =

Works with MU installation !

= Localization =

* English (United States), default language
* French (France) translation provided by 

= Features of the framework =

This plugin uses the SL framework. This framework eases the creation of new plugins by providing tools and frames (see dev-toolbox plugin for more info).

You may easily translate the text of the plugin and submit it to the developer, send a feedback, or choose the location of the plugin in the admin panel.

Have fun !

== Installation ==

1. Upload this folder links-synthesis to your plugin directory (for instance '/wp-content/plugins/')
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to the 'SL plugins' box
4. All plugins developed with the SL core will be listed in this box
5. Enjoy !

== Screenshots ==

1. Links with issues
2. All Links
3. Some parameters

== Changelog ==

= 1.2.1 = 
* NEW: Wkhtmltoimage may be used to create thumbnails (no external API need to create the image)
* NEW: do not display the table if no link is used in the page

= 1.2.0 = 
* NEW: Thumbnail of the URL may be displayed when the mouse is over
* NEW: Now the ignored page are listed 
* BUG: increase the resilience of the plugins

= 1.1.0 -&gt; 1.1.4 = 
* NEW: Enhance the appearence of the configuration page
* BUG: Improve the matching of the regexp
* BUG: Avoid taking in account links that has been added by other plugins
* NEW: Add a new feature to force the verification of all links and all page (for low traffic website)
* NEW: Delete old links when a page has been deleted
* NEW: Links with anchors are now handled (anchor are disregarded when the summary is performed and the anchor are search in the page to see if it exists).

= 1.0.0 -&gt; 1.0.9 = 
* Various enhancement
* Exclude links that are added by plugins
* Remove blank after the sup links
* Regexp is improved and then changed to be more robust : WARNING you shoud update your regexp if you have one
* It is now possible to use pseudo function REPLACE and EXPLODE
* Limit the number of update of the DB
* Resolve a bug which delete valid link
* Improve the management of links
* It is now possible to change URL with redirection to the redirected URL
* First release

== Frequently Asked Questions ==

 
InfoVersion:f7432d50d2ba9b9b9f8bb321f772e522