=== Links synthesis ===

Author: sedLex
Contributors: sedLex
Author URI: http://www.sedlex.fr/
Plugin URI: http://wordpress.org/plugins/links-synthesis/
Tags: tag
Requires at least: 3.0
Tested up to: 3.8.1
Stable tag: trunk

This plugin enables a synthesis of all links in an article and retrieves data from them. 

== Description ==

This plugin enables a synthesis of all links in an article and retrieves data from them. 

In this plugin, an index of all links in the page/post is created at the end of the page/post. 

In addition, each link is periodically check to see if the link is still valid. 

Finally, you may customize the display of each link thanks to metatag and headers.

This plugin is under GPL licence. 

= Multisite - Wordpress MU =

= Localization =

* English (United States), default language
* French (France) translation provided by 

= Features of the framework =

This plugin uses the SL framework. This framework eases the creation of new plugins by providing incredible tools and frames.

For instance, a new created plugin comes with

* A translation interface to simplify the localization of the text of the plugin ; 
* An embedded SVN client (subversion) to easily commit/update the plugin in wordpress.org repository ; 
* A detailled documentation of all available classes and methodes ; 
* etc.

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

= 1.1.0 = 
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

 
InfoVersion:dde2ba3257bac7ae6abc4a8b1bdb17ff