<?php
/**
Plugin Name: Links synthesis
Plugin Tag: tag
Description: <p>This plugin enables a synthesis of all links and the creation of thumbnail for links in an article and retrieves data from them. </p><p>In this plugin, an index of all links in the page/post is created at the end of the page/post. </p><p>In addition, each link is periodically check to see if the link is still valid. </p>p>In addition, each link is periodically check to see if the link is still valid. </p><p>You may display a thumbnail of the URL when the user move its mouse over the link.</p><p>This plugin is under GPL licence. </p>
Version: 1.2.3
Framework: SL_Framework
Author: sedLex
Author URI: http://www.sedlex.fr/
Author Email: sedlex@sedlex.fr
Framework Email: sedlex@sedlex.fr
Plugin URI: http://wordpress.org/plugins/links-synthesis/
License: GPL3
*/

//Including the framework in order to make the plugin work

require_once('core.php') ; 
require_once('include/grabzit2.1.4/lib/GrabzItClient.class.php') ; 

/** ====================================================================================================================================================
* This class has to be extended from the pluginSedLex class which is defined in the framework
*/
class links_synthesis extends pluginSedLex {
	
	/** ====================================================================================================================================================
	* Plugin initialization
	* 
	* @return void
	*/
	static $instance = false;

	protected function _init() {
		global $wpdb ; 

		// Name of the plugin (Please modify)
		$this->pluginName = 'Links synthesis' ; 
		
		// The structure of the SQL table if needed (for instance, 'id_post mediumint(9) NOT NULL, short_url TEXT DEFAULT '', UNIQUE KEY id_post (id_post)') 
		$this->tableSQL = "id mediumint(9) NOT NULL AUTO_INCREMENT, url TEXT DEFAULT '', http_code mediumint(9) NOT NULL, last_check DATETIME, failure_first DATETIME, redirect_url TEXT DEFAULT '', title TEXT DEFAULT '', metatag TEXT DEFAULT '', header TEXT DEFAULT '', occurrence TEXT DEFAULT '', UNIQUE KEY id (id)" ; 
		// The name of the SQL table (Do no modify except if you know what you do)
		$this->table_name = $wpdb->prefix . "pluginSL_" . get_class() ; 

		//Configuration of callbacks, shortcode, ... (Please modify)
		// For instance, see 
		//	- add_shortcode (http://codex.wordpress.org/Function_Reference/add_shortcode)
		//	- add_action 
		//		- http://codex.wordpress.org/Function_Reference/add_action
		//		- http://codex.wordpress.org/Plugin_API/Action_Reference
		//	- add_filter 
		//		- http://codex.wordpress.org/Function_Reference/add_filter
		//		- http://codex.wordpress.org/Plugin_API/Filter_Reference
		// Be aware that the second argument should be of the form of array($this,"the_function")
		// For instance add_action( "wp_ajax_foo",  array($this,"bar")) : this function will call the method 'bar' when the ajax action 'foo' is called
		add_action( "wp_ajax_changeURL",  array($this,"changeURL")) ; 
		add_action( "wp_ajax_recheckURL",  array($this,"recheckURL")) ; 
		add_action( "wp_ajax_ignoreURL",  array($this,"ignoreURL")) ; 
		add_action( "wp_ajax_doNotIgnoreURL",  array($this,"doNotIgnoreURL")) ; 
		
		add_action( "wp_ajax_stopAnalysisLinks",  array($this,"stopAnalysisLinks")) ; 
		add_action( "wp_ajax_forceAnalysisLinks",  array($this,"forceAnalysisLinks")) ; 

		// Important variables initialisation (Do not modify)
		$this->path = __FILE__ ; 
		$this->pluginID = get_class() ; 
		
		// activation and deactivation functions (Do not modify)
		register_activation_hook(__FILE__, array($this,'install'));
		register_deactivation_hook(__FILE__, array($this,'deactivate'));
		register_uninstall_hook(__FILE__, array('links_synthesis','uninstall_removedata'));
		
		$this->count_nb = 0 ; 
		$this->img_count = 1 ; 
		$this->table_links = array() ; 
		add_action( 'wp_ajax_nopriv_checkLinksSynthesis', array( $this, 'checkLinksSynthesis'));
		add_action( 'wp_ajax_checkLinksSynthesis', array( $this, 'checkLinksSynthesis'));
	}
	
	/** ====================================================================================================================================================
	* In order to uninstall the plugin, few things are to be done ... 
	* (do not modify this function)
	* 
	* @return void
	*/
	
	static public function uninstall_removedata () {
		global $wpdb ;
		// DELETE OPTIONS
		delete_option('links_synthesis'.'_options') ;
		if (is_multisite()) {
			delete_site_option('links_synthesis'.'_options') ;
		}
		
		// DELETE SQL
		if (function_exists('is_multisite') && is_multisite()){
			$old_blog = $wpdb->blogid;
			$old_prefix = $wpdb->prefix ; 
			// Get all blog ids
			$blogids = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM ".$wpdb->blogs));
			foreach ($blogids as $blog_id) {
				switch_to_blog($blog_id);
				$wpdb->query("DROP TABLE ".str_replace($old_prefix, $wpdb->prefix, $wpdb->prefix . "pluginSL_" . 'links_synthesis')) ; 
			}
			switch_to_blog($old_blog);
		} else {
			$wpdb->query("DROP TABLE ".$wpdb->prefix . "pluginSL_" . 'links_synthesis' ) ; 
		}
	}
	
	/**====================================================================================================================================================
	* Function called when the plugin is activated
	* For instance, you can do stuff regarding the update of the format of the database if needed
	* If you do not need this function, you may delete it.
	*
	* @return void
	*/
	
	public function _update() {
		SLFramework_Debug::log(get_class(), "Update the plugin." , 4) ; 
	}
	
	/**====================================================================================================================================================
	* Function called to return a number of notification of this plugin
	* This number will be displayed in the admin menu
	*
	* @return int the number of notifications available
	*/
	 
	public function _notify() {
		global $wpdb ; 
		$nb = 0 ; 
		if ($this->get_param('show_nb_error')) {
			$nb = $wpdb->get_var("SELECT COUNT(*) FROM ".$this->table_name." WHERE http_code!='200' AND http_code!='-1' AND http_code!='-2'") ; 
		}
		return $nb ; 
	}
	
	
	/** ====================================================================================================================================================
	* Init javascript for the public side
	* If you want to load a script, please type :
	* 	<code>wp_enqueue_script( 'jsapi', 'https://www.google.com/jsapi');</code> or 
	*	<code>wp_enqueue_script('links_synthesis_script', plugins_url('/script.js', __FILE__));</code>
	*	<code>$this->add_inline_js($js_text);</code>
	*	<code>$this->add_js($js_url_file);</code>
	*
	* @return void
	*/
	
	function _public_js_load() {	
		ob_start() ; 
		?>
			function checkLinksSynthesis() {
				
				var arguments = {
					action: 'checkLinksSynthesis'
				} 
				var ajaxurl2 = "<?php echo admin_url()."admin-ajax.php"?>" ; 
				jQuery.post(ajaxurl2, arguments, function(response) {
					// We do nothing as the process should be as silent as possible
				});    
			}
			
			// We launch the callback
			if (window.attachEvent) {window.attachEvent('onload', checkLinksSynthesis);}
			else if (window.addEventListener) {window.addEventListener('load', checkLinksSynthesis, false);}
			else {document.addEventListener('load', checkLinksSynthesis, false);} 
		<?php 
		
		$java = ob_get_clean() ; 
		$this->add_inline_js($java) ; 
		return ; 
	}
	
	/** ====================================================================================================================================================
	* Init css for the public side
	* If you want to load a style sheet, please type :
	*	<code>$this->add_inline_css($css_text);</code>
	*	<code>$this->add_css($css_url_file);</code>
	*
	* @return void
	*/
	
	function _public_css_load() {	
	
		// We check whether there is an exclusion
		$exclu = $this->get_param('exclu') ;
		$exclu = explode("\n", $exclu) ;
		foreach ($exclu as $e) {
			$e = trim(str_replace("\r", "", $e)) ; 
			if ($e!="") {
				$e = "@".$e."@i"; 
				if (preg_match($e, $_SERVER['REQUEST_URI'])) {
					return ; 
				}
			}
		}
		
		$this->add_inline_css(".thumbnail_url_LS{
	display:none;
	position:absolute;
}
".$this->get_param('css')) ; 
		
		return ; 
	}
	
	/** ====================================================================================================================================================
	* Init javascript for the admin side
	* If you want to load a script, please type :
	* 	<code>wp_enqueue_script( 'jsapi', 'https://www.google.com/jsapi');</code> or 
	*	<code>wp_enqueue_script('links_synthesis_script', plugins_url('/script.js', __FILE__));</code>
	*	<code>$this->add_inline_js($js_text);</code>
	*	<code>$this->add_js($js_url_file);</code>
	*
	* @return void
	*/
	
	function _admin_js_load() {	
		return ; 
	}
	
	/** ====================================================================================================================================================
	* Init css for the admin side
	* If you want to load a style sheet, please type :
	*	<code>$this->add_inline_css($css_text);</code>
	*	<code>$this->add_css($css_url_file);</code>
	*
	* @return void
	*/
	
	function _admin_css_load() {	
		return ; 
	}

	/** ====================================================================================================================================================
	* Called when the content is displayed
	*
	* @param string $content the content which will be displayed
	* @param string $type the type of the article (e.g. post, page, custom_type1, etc.)
	* @param boolean $excerpt if the display is performed during the loop
	* @return string the new content
	*/
	
	function _modify_content($content, $type, $excerpt, $pid=-1) {	
		global $post ; 
		global $wpdb ; 
		
		
		if ($pid==-1) {
			$pid = $post->ID ; 
		} 
		
		$postpost = get_post($pid) ; 
		$this->content_for_callback = $postpost->post_content ; // on ne met pas content car il y a beaucoup de lien ajoutŽ par d'autres plugins
		
		// We check whether there is an exclusion
		$exclu = $this->get_param('exclu') ;
		$exclu = explode("\n", $exclu) ;
		foreach ($exclu as $e) {
			$e = trim(str_replace("\r", "", $e)) ; 
			if ($e!="") {
				$e = "@".$e."@i"; 
				if (preg_match($e, $_SERVER['REQUEST_URI'])) {
					return $content ; 
				}
			}
		}
		
		// We check whether it is to be dispalyed in 
		if ( ($excerpt == true) && (!$this->get_param('display_in_excerpt')) )
			return $content ; 
		
		// ANALYSIS
		$pattern = '/<a([^>]*?)href=["\']([^"\']*?)["\']([^>]*?)>(.*?)<\/a>/is';
		$content = preg_replace_callback($pattern, array($this,"_modify_content_callback"), $content);
		
		ob_start() ; 
		
			foreach ($this->table_links as $tl) {
				if (trim($tl['title'])=="") {
					$tl['title'] = $tl['url'] ; 
				}
				$truc="" ; 
				
				// Display only if it is not an anchor
				if (!$tl['anchor']) {
				
					// We search for the correct display
					$regexps = $this->get_param_macro('custom_regexp') ; 
					$entries = $this->get_param_macro('custom_display') ; 
					for ($i=0 ; $i<count($regexps) ; $i++) {
						if (preg_match($regexps[$i],$tl['url'])) {
							$truc = $entries[$i]."\r\n"  ; 
							break ; 
						}
					}
				
					// Default display
					if ($truc=="") {
						$truc = $this->get_param('html_entry')."\r\n" ; 
					}
				
					$truc = str_replace("%num%", $tl['num'], $truc) ; 
					$truc = str_replace("%anchor%", "<a id='links_ref".$tl['num']."'></a>", $truc) ;
					$truc = str_replace("%href%", $tl['url'], $truc) ; 
					$truc = str_replace("%title%", $tl['title'], $truc) ; 
					if (is_user_logged_in()) {
						$truc = str_replace("%admin_status%", $tl['status'], $truc) ; 
					} else {
						$truc = str_replace("%admin_status%", "", $truc) ; 
					}
					// Metatag
					$meta = unserialize(str_replace("##&#39;##", "'", $tl['metatag'])) ; 
					if (is_array($meta)) {
						foreach ($meta as $mt) {
							$truc = str_replace("%meta_".$mt['name']."%", $mt['value'], $truc) ; 
						}
					}
					// Header
					if ($tl['http_code']!=0) {
						$head = unserialize(str_replace("##&#39;##", "'", $tl['header'])) ; 
						if (is_array($head)) {
							foreach ($head as $mtk=>$mt) {
								if (is_string($mt) && is_string($mtk)) {
									$truc = str_replace("%head_".$mtk."%", $mt, $truc) ; 
								}
							}
						}
					}
					// #REPLACE(word_to_find##word_to_replace##sentence)# 
					if (strpos($truc, "#REPLACE(")!==false) {
						$truc = preg_replace_callback("/#REPLACE\((.*)##(.*)##(.*)\)#/mU", array($this, "_replace_in_links"), $truc) ;
					}
					// #EXPLODE(delimiter##sentence)(nb)# 
					if (strpos($truc, "#EXPLODE(")!==false) {
						$truc = preg_replace_callback("/#EXPLODE\((.*)##(.*)\)\((.*)\)#/mU", array($this, "_explode_in_links"), $truc) ;
					}
				
					// remove unused tag
					$truc = preg_replace("/\%[^\%]*\%/i", "", $truc) ; 
					// Cosmetic
					$truc = str_replace("()", "", $truc) ; 
					$truc = str_replace(",,", "", $truc) ; 
					$truc = trim($truc) ; 
					$truc = str_replace(", ,", "", $truc) ; 
					$truc = trim($truc) ; 
					echo $truc ;
				}
	
				// We update the number of occurrence
				$result = $wpdb->get_results("SELECT * FROM ".$this->table_name." WHERE url='".str_replace("'", "&#39;", $tl['url'])."' LIMIT 1 ; ") ; 

				if ( $result ) {

					$current_occurrence = unserialize(str_replace("##&#39;##", "'", $result[0]->occurrence)) ; 
					
					if (!is_array($current_occurrence)) {
						$current_occurrence = array() ; 
					}
					
					$renumerot_necessaire = false ; 
					
					// On parcours les occurrences deja enregistre pour mettre a jour celle ci
					$count_init = count($current_occurrence) ; 
					for ($i=0 ; $i<$count_init ; $i++) {
						if ($current_occurrence[$i]["id"] == "selectPostWithID".$pid) {
							if (isset($tl["occ"][$current_occurrence[$i]["text"]])) {
								if ($current_occurrence[$i]["nb"]!=$tl["occ"][$current_occurrence[$i]["text"]]) {
									$current_occurrence[$i]["nb"] = $tl["occ"][$current_occurrence[$i]["text"]] ; 
								}
							// Si on a pas trouve l'occurrence dans $tl["occ"], cela veut dire qu'il a été supprimé, on le supprime aussi de $current_occurrence
							} else {
								unset($current_occurrence[$i]);
								$renumerot_necessaire = true ; 
							}
						}
					}
					// On renumerote les index si necessaire
					if ($renumerot_necessaire) {
						$current_occurrence = array_values($current_occurrence) ; 
					}
					// On parcours $tl["occ"] pour mettre a jour les occurrences deja enregistre 
					foreach ($tl["occ"] as $k => $v) {
						$found_occ = false ; 
						for ($i=0 ; $i<count($current_occurrence) ; $i++) {
							if ($k == $current_occurrence[$i]["text"]) {
								$found_occ = true ; 
							}
						}
						if (!$found_occ) {
							$current_occurrence[] = array("id"=>"selectPostWithID".$pid, "text"=>$k, "nb"=>$v) ; 
						}
					}
					// On verifie que l'on a besoin de mettre a jour ou non la base SQL
					$new_serialized_occurrence = str_replace("'", "##&#39;##", serialize($current_occurrence)) ; 
					
					if ($new_serialized_occurrence != $result[0]->occurrence) {
						if (count($current_occurrence)>0) {
							$update = "UPDATE ".$this->table_name." SET occurrence='".$new_serialized_occurrence."' WHERE id='".$result[0]->id."'" ; 
							$wpdb->query($update) ; 
						} else {
							$delete = "DELETE FROM ".$this->table_name." WHERE id='".$result[0]->id."'" ; 
							$wpdb->query($delete) ; 							
						}
					}
				} 
			}
			
		$reftable = ob_get_clean() ;
		 
		if ( ($this->get_param('display')) || ( ($this->get_param('display_admin'))&&(is_user_logged_in()) ) ) {
			if (trim($reftable)!="") {
				$content = $content.str_replace("%links_synthesis%", $reftable, $this->get_param('html')) ; 
			} else {
				// nothing
			}
		} else {
			$vide = $reftable ; 
		}
		
		// we delete these entries that are not in $this->table_links
		$result = $wpdb->get_results("SELECT * FROM ".$this->table_name." WHERE occurrence like '%selectPostWithID".$pid."%'; ") ; 
		if (is_array($result)) {
			foreach ($result as $r) {
				$present = false ; 
				foreach ($this->table_links as $tl) {
					if ($tl['url']==$r->url) {
						$present = true ; 
						break ; 
					}
				}
				if (!$present) {
					// We delete this occurrence of this URL in the database and for this page id
					$current_occurrence = unserialize(str_replace("##&#39;##", "'", $r->occurrence)) ; 
					if (is_array($current_occurrence)) {
						$new_occurrence = array() ; 
						foreach ($current_occurrence as $ao) {
							if ($ao['id']!="selectPostWithID".$pid) {
								$new_occurrence[] = $ao ; 
							}
						}
						
						$new_serialized_occurrence = str_replace("'", "##&#39;##", serialize($new_occurrence)) ; 
						if ($new_serialized_occurrence != $r->occurrence) {
							if (count($new_occurrence)>0) {
								$update = "UPDATE ".$this->table_name." SET occurrence='".$new_serialized_occurrence."' WHERE id='".$r->id."'" ; 
							} else {
								$update = "DELETE FROM ".$this->table_name." WHERE id='".$r->id."'" ; 
							}
							$wpdb->query($update) ; 
						}
					}		
				}
			}	
		}
		
		// We delete entries that have no occurrence (Seulement en cas de probleme ==> Nettoyage)
		//$wpdb->query("DELETE FROM ".$this->table_name." WHERE occurrence not like '%selectPostWithID%'") ; 
		return $content; 
	}
	
	/** ====================================================================================================================================================
	* Called when #REPLACE(word_to_find#word_to_replace#sentence)# is found
	*
	* @param array matches found in the content
	* @return string the new content to be replaced with
	*/
	
	function _replace_in_links($matches) {
  		// comme d'habitude : $matches[0] represente la valeur totale
  		// $matches[1] represente la premiere parenthese capturante
  		
  		return str_replace($matches[1], $matches[2], $matches[3]) ; 
  	}

	/** ====================================================================================================================================================
	* Called when #EXPLODE(delimiter#sentence)(nb)# is found
	*
	* @param array matches found in the content
	* @return string the new content to be replaced with
	*/
	
	function _explode_in_links($matches) {
  		// comme d'habitude : $matches[0] represente la valeur totale
  		// $matches[1] represente la premiere parenthese capturante
  		
  		$explode_str = explode($matches[1], $matches[2]) ; 
  		if (isset($explode_str[$matches[3]])) {
  			return $explode_str[$matches[3]] ; 
  		} else {
  		  	return $matches[2] ; 
  		}
  	}

	
	/** ====================================================================================================================================================
	* Called when a link is found
	*
	* @param array matches found in the content
	* @return string the new content to be replaced with
	*/
	
	function _modify_content_callback($matches) {
		global $wpdb ; 
		global $blog_id ; 
  		
  		// comme d'habitude : $matches[0] represente la valeur totale
  		// $matches[1] represente la premiere parenthese capturante
  		// /<a([^>]*?)href=["\']([^"\']*?)["\']([^>]*?)>(.*?)<\/a>/is
		
		$true_content = $this->content_for_callback ; 
		
		if ($matches[2]=="") {
			return $matches[0] ; 
		}
		
		// si non present dans le post initial, on ne fait rien
		if (strpos($true_content, $matches[2])===false) {
  			return $matches[0] ; 
  		}
  		
  		// si image on ne fait rien
  		if (preg_match("/<img/iesU", $matches[4])) {
  			return $matches[0] ; 
  		}
  		
  		// si lien interne sur la page on ne fait rien
  		if (preg_match("/^(\s)*#/iesU", $matches[2])) {
  			return $matches[0] ; 
  		}
  		
  		// si lien vers admin on ne fait rien
  		if (strpos($matches[2], site_url('/wp-admin/'))!==false) {
  			return $matches[0] ; 
  		}
  		
  		// On regarde si on doit nettoyer le lien pour supprimer l'ancre (pour l'affichage uniquement)
  		$key_url = sha1($matches[2]) ; 
  		$short_url = $matches[2] ; 
  		if ($this->get_param('handle_anchor')) {
  			$tmp = explode("#",$matches[2]) ; 
  			$key_url = sha1($tmp[0]) ;
  			$short_url = $tmp[0] ; 
  		}
  		
  		// On regarde si on doit l'afficher
  		$toBeDisplayed = true ; 
  		if ($this->get_param('only_external')) {
			if (strpos($matches[2], home_url())!==false) {
				return $matches[0] ; 
			}		
  		}		
		
		// We update the list
   		if (!isset($this->table_links[$key_url])) {
			$this->count_nb ++ ; 
  	   		$result = $wpdb->get_results("SELECT * FROM ".$this->table_name." WHERE url='".str_replace("'", "&#39;", $matches[2])."' LIMIT 1 ; ") ; 
			$status = "" ; 
			if ( $result ) {
				if ($result[0]->http_code==200) {
					$status = "" ; 
				} else if ($result[0]->http_code==210) { // Mme si l'ancre est manquante on dit que ok car c'est pour la key_url i.e. sans l'ancre
					$status = "" ; 
				} else if ($result[0]->http_code==0) {
					$status = $this->http_status_code_string(0, true, true, $result[0]->header) ; 
				} else {
					$status = $this->http_status_code_string($result[0]->http_code, true, true); 
				}
				$this->table_links[$key_url] = array("anchor"=>false, "num"=>$this->count_nb, "occ"=> array(), "url"=>$short_url, "title"=>$result[0]->title, "status"=>$status, "metatag"=>$result[0]->metatag, "header"=>$result[0]->header, "http_code"=>$result[0]->http_code) ; 				
			} else {
				$this->table_links[$key_url] = array("anchor"=>false, "num"=>$this->count_nb, "occ"=> array(), "url"=>$short_url, "title"=>"", "status"=>$this->http_status_code_string(-1, true, true) , "metatag"=>serialize(array()), "header"=>serialize(array()), "http_code"=>-1) ; 				
				$wpdb->query("INSERT INTO ".$this->table_name." (url, http_code) VALUES ('".str_replace("'", "&#39;", $matches[2])."', -1) ;") ; 	
			}
   		} 
   		
   		// We put the anchor in the list (but hidden)
   		$ancre_status = "" ; 
   		if (!isset($this->table_links[sha1($matches[2])])) {
  	   		$result = $wpdb->get_results("SELECT * FROM ".$this->table_name." WHERE url='".str_replace("'", "&#39;", $matches[2])."' LIMIT 1 ; ") ; 
			$ancre_status = "" ; 
			if ( $result ) {
				if ($result[0]->http_code==200) {
					$ancre_status = "" ;  
				} else if ($result[0]->http_code==0) {
					$ancre_status = $this->http_status_code_string(0, true, true, $result[0]->header) ; 
				} else {
					$ancre_status = $this->http_status_code_string($result[0]->http_code, true, true); 
				}
				$this->table_links[sha1($matches[2])] = array("anchor"=>true, "num"=>-1, "occ"=> array(), "url"=>$matches[2], "title"=>$result[0]->title, "status"=>$ancre_status, "metatag"=>$result[0]->metatag, "header"=>$result[0]->header, "http_code"=>$result[0]->http_code) ; 				
			} else {
				$this->table_links[sha1($matches[2])] = array("anchor"=>true, "num"=>-1, "occ"=> array(), "url"=>$matches[2], "title"=>"", "status"=>$this->http_status_code_string(-1, true, true) , "metatag"=>serialize(array()), "header"=>serialize(array()), "http_code"=>-1) ; 				
				$wpdb->query("INSERT INTO ".$this->table_name." (url, http_code) VALUES ('".str_replace("'", "&#39;", $matches[2])."', -1) ;") ; 	
			}
   		} 
   		
   		// We update the occurrence of text in the list
		if (isset($this->table_links[$key_url]["occ"][trim($matches[4])])) {
			$this->table_links[$key_url]["occ"][trim($matches[4])] ++ ; 
		} else {
			$this->table_links[$key_url]["occ"][trim($matches[4])] = 1 ; 
		}
		
		// We update the occurrence of the anchor in the list
		if ($key_url!=sha1($matches[2])) {
			if (isset($this->table_links[sha1($matches[2])]["occ"][trim($matches[4])])) {
				$this->table_links[sha1($matches[2])]["occ"][trim($matches[4])] ++ ; 
			} else {
				$this->table_links[sha1($matches[2])]["occ"][trim($matches[4])] = 1 ; 
			}
		}
		
		$lien_final = $matches[0] ; 
				
		// Si on veut avoir une miniature lorsque l'on met la souris dessus
		// /<a([^>]*?)href=["\']([^"\']*?)["\']([^>]*?)>(.*?)<\/a>/is
		if ($this->get_param("show_thb_onover")) {
									
			// We create the folder for the img files
			$blog_fold = "" ; 
			if (is_multisite()) {
				$blog_fold = $blog_id."/" ; 
			}
	
			if ($this->get_param('enable_grabzit')) {
				if (is_file(WP_CONTENT_DIR."/sedlex/links-synthesis/".$blog_fold."/img_".sha1($matches[2]).".jpg")) {
					$lien_final = '<a'.$matches[1].'href="'.$matches[2].'"'.$matches[3].' onmouseover="jQuery(\'.img_'.sha1($matches[2])."_".$this->img_count.'\').fadeIn(\'slow\');" onmouseout="jQuery(\'.img_'.sha1($matches[2])."_".$this->img_count.'\').fadeOut(\'slow\');">'.$matches[4].'</a>' ; 
					$lien_final .= '<img class="thumbnail_url_LS img_'.sha1($matches[2])."_".$this->img_count.'" src="'.WP_CONTENT_URL."/sedlex/links-synthesis/".$blog_fold."/img_".sha1($matches[2]).".jpg".'" />' ; 
					$this->img_count ++ ; 
				}
			}
			
			if ($this->get_param('enable_wkhtmltoimage')) {
				if (is_file(WP_CONTENT_DIR."/sedlex/links-synthesis/".$blog_fold."wk_th_".sha1($matches[2].$this->get_param('enable_wkhtmltoimage_winw').$this->get_param('enable_wkhtmltoimage_w').$this->get_param('enable_wkhtmltoimage_h')).".jpg")) {
					$lien_final = '<a'.$matches[1].'href="'.$matches[2].'"'.$matches[3].' onmouseover="jQuery(\'.img_'.sha1($matches[2])."_".$this->img_count.'\').fadeIn(\'slow\');" onmouseout="jQuery(\'.img_'.sha1($matches[2])."_".$this->img_count.'\').fadeOut(\'slow\');">'.$matches[4].'</a>' ; 
					$lien_final .= '<img class="thumbnail_url_LS img_'.sha1($matches[2])."_".$this->img_count.'" src="'.WP_CONTENT_URL."/sedlex/links-synthesis/".$blog_fold."wk_th_".sha1($matches[2].$this->get_param('enable_wkhtmltoimage_winw').$this->get_param('enable_wkhtmltoimage_w').$this->get_param('enable_wkhtmltoimage_h')).".jpg".'" />' ; 
					$this->img_count ++ ; 
				}
			}
		}
  		
  		if (($toBeDisplayed) && ( ($this->get_param('display')) || ( ($this->get_param('display_admin'))&&(is_user_logged_in()) ) )) {
  			if (($this->get_param('display_error_admin'))&&(is_user_logged_in())) {
  				if ($ancre_status!="") {
  					return $lien_final."<sup class='synthesis_sup'><a href='#links_ref".$this->table_links[$key_url]["num"]."'>".$this->table_links[$key_url]["num"]."</a></sup>".$ancre_status ; 
  				} else {
  					return $lien_final."<sup class='synthesis_sup'><a href='#links_ref".$this->table_links[$key_url]["num"]."'>".$this->table_links[$key_url]["num"]."</a></sup>".$this->table_links[$key_url]["status"] ; 
				}
  			} else {
  				return $lien_final."<sup class='synthesis_sup'><a href='#links_ref".$this->table_links[$key_url]["num"]."'>".$this->table_links[$key_url]["num"]."</a></sup>" ; 
  			}
  		} else {
  			return $lien_final ; 
  		}
  		
	}
	/** ====================================================================================================================================================
	* Add a button in the TinyMCE Editor
	*
	* To add a new button, copy the commented lines a plurality of times (and uncomment them)
	* 
	* @return array of buttons
	*/
	
	function add_tinymce_buttons() {
		$buttons = array() ; 
		//$buttons[] = array(__('title', $this->pluginID), '[tag]', '[/tag]', WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename( __FILE__)).'img/img_button.png') ; 
		return $buttons ; 
	}
	
	/**====================================================================================================================================================
	* Function to instantiate the class and make it a singleton
	* This function is not supposed to be modified or called (the only call is declared at the end of this file)
	*
	* @return void
	*/
	
	public static function getInstance() {
		if ( !self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}
	
	/** ====================================================================================================================================================
	* Define the default option values of the plugin
	* This function is called when the $this->get_param function do not find any value fo the given option
	* Please note that the default return value will define the type of input form: if the default return value is a: 
	* 	- string, the input form will be an input text
	*	- integer, the input form will be an input text accepting only integer
	*	- string beggining with a '*', the input form will be a textarea
	* 	- boolean, the input form will be a checkbox 
	* 
	* @param string $option the name of the option
	* @return variant of the option
	*/
	public function get_default_option($option) {
		switch ($option) {
			// Alternative default return values (Please modify)
			case 'handle_anchor' : return true ; 
			case 'check_presence_anchor' : return true ; 
			
			case 'display' : return true ; 
			case 'display_admin' : return true ; 
			case 'display_error_admin' : return false ; 
			case 'display_error_admin_summ' : return true ; 
			case 'display_in_excerpt' : return true ; 
			case 'check_cron' : return 7 ; 
			case 'check_cron_error' : return 1 ; 
			case 'only_external' : return true ; 
			case 'nb_check'		: return 2 ; break ; 
			case 'exclu'		: return "*" ; break ; 
			case 'show_nb_error' : return true ; break ;
			case 'css' 		: return "*.links_synthesis {
   background-color:#EEEEEE ; 
   margin:10px;
   padding : 10px;
}

.links_synthesis_title {
   font-size:12px ;
   text-align:center;
   font-weight:bold;
}

p.links_synthesis_entry {
   font-size:12px ;
   margin:2px;  
   padding:2px;  
}

.links_synthesis_print_only {
	display:none;
}

.synthesis_sup {
	font-size:70% ;
	vertical-align: super;
}

.thumbnail_url_LS {
    z-index:999 ; 
    padding:0px;
    border:1px solid #666666 ; 
}

@media print {
   .links_synthesis_print_only {
      display:block;
   }
}"		; break ; 
			case 'html' 		: return "*<div class='links_synthesis'>
<p class='links_synthesis_title'>Reference table:</p>
     %links_synthesis%
</div>"			; break ; 
			case 'html_entry' 		: return "*<p class='links_synthesis_entry'>%anchor%%num%) <a href='%href%'>%title%</a><span class='links_synthesis_print_only'> (%href%)</span> %admin_status%</p>"			; break ; 
		
			case 'custom_regexp'		: return "" ; break ; 
			case 'custom_display' 		: return "*<p class='links_synthesis_entry'>%anchor%%num%) SPECIAL DISPLAY <a href='%href%'>%title%</a><span class='links_synthesis_print_only'> (%href%)</span></p>"			; break ; 

			case 'type_page' 		: return "page,post" 		; break ; 
			case 'list_post_id_to_check': return array()			; break ; 
			case 'nb_post_to_check'  : return 0 ; break ; 
			case 'list_link_id_to_check': return array()			; break ; 
			case 'nb_link_to_check'  : return 0 ; break ; 
			case 'max_page_to_check'  : return 200 ; break ; 
			
			case 'show_thb_onover' : return false ; break ;  
			case 'enable_grabzit' : return false ; break ;  
			case 'grabzit_Application_Key': return "" ; break ; 
			case 'grabzit_Application_Secret': return "" ; break ; 

			case 'enable_wkhtmltoimage' : return false ; break ;  
			case 'enable_wkhtmltoimage_winw' : return 1024 ; break ;  
			case 'enable_wkhtmltoimage_w' : return 300 ; break ;  
			case 'enable_wkhtmltoimage_h' : return 300 ; break ;  
			
		}
		return null ;
	}

	/** ====================================================================================================================================================
	* The admin configuration page
	* This function will be called when you select the plugin in the admin backend 
	*
	* @return void
	*/
	
	public function configuration_page() {
		global $wpdb;
		global $blog_id ; 
		
		$blog_fold = "" ; 
		if (is_multisite()) {
			$blog_fold = $blog_id."/" ; 
		}

		SLFramework_Debug::log(get_class(), "Print the configuration page." , 4) ; 
				
		?>
		<div class="plugin-titleSL">
			<h2><?php echo $this->pluginName ?></h2>
		</div>
		
		<div class="plugin-contentSL">		
			<?php echo $this->signature ; ?>

			<?php
			//===============================================================================================
			// After this comment, you may modify whatever you want
			?>
			<?php
			
			// We check rights
			$this->check_folder_rights( array(array(WP_CONTENT_DIR."/sedlex/test/", "rwx")) ) ;
			
			$tabs = new SLFramework_Tabs() ; 
			
			ob_start() ; 
				echo "<div id='table_links_synthesis'>"  ; 
				$this->displayErrorTable() ;
				echo "</div>" ; 
			
				echo "<p>" ; 
				echo "<img id='wait_analysisLS' src='".WP_PLUGIN_URL."/".str_replace(basename(__FILE__),"",plugin_basename( __FILE__))."core/img/ajax-loader.gif' style='display: none;'>" ; 
				echo "<input type='button' id='forceAnalysisLS' class='button-primary validButton' onClick='forceAnalysisLS()'  value='". __('Force analysis',$this->pluginID)."' />" ; 
				echo "<script>jQuery('#forceAnalysisLS').removeAttr('disabled');</script>" ; 
				echo " <input type='button' id='stopAnalysisLS' class='button validButton' onClick='stopAnalysisLS()'  value='". __('Stop analysis',$this->pluginID)."' />" ; 
				echo "<script>jQuery('#stopAnalysisLS').attr('disabled', 'disabled');</script>" ; 
				echo "</p>" ; 			
												
			$tabs->add_tab(__('Links with Errors',  $this->pluginID), ob_get_clean()) ; 

			ob_start() ; 
				$maxnb = 20 ; 
				
				$all_regexp = $this->get_param_macro('custom_regexp') ; 
				
				// Affichage 
				if (count($all_regexp)>0) {
					echo "<h3>".__("Filter links based on Custom rule ", $this->pluginID)."</h3>" ; 
					echo "<form method='get' action='".remove_query_arg(array('show_regexp'))."'>" ; 
					$check = "" ;
					$regexp_to_be_matched = array() ; 

					echo '<input name="page" value="'.str_replace('"', "", $_GET['page']).'" type="hidden"/>' ; 
					foreach ($all_regexp as $r) {
						$check = "" ; 
						if (isset($_GET['show_regexp'])) {
							$show_regexp = $_GET['show_regexp'] ; 
							foreach ($show_regexp as $sr) {
								if ($sr==sha1($r)) {
									$check = "checked" ; 
									$regexp_to_be_matched[] = $r ;  
								}
							}
						}
						echo "<p><input type='checkbox' name='show_regexp[]' value='".sha1($r)."' $check> ".sprintf(__("Links which match regex %s", $this->pluginID), "<code>".$r."</code>")."</p>" ; 
					}
					echo "<p><input type='submit' class='button-primary validButton' value='".__("Filter results based on regexp",$this->pluginID)."'/></p>" ; 
					echo "</form>";	
				}	
				
				$table = new SLFramework_Table(0, $maxnb, true, true) ;
				$table->title(array(__('URL', $this->pluginID), __('Posts/Articles', $this->pluginID), __('Status', $this->pluginID), __('Keywords', $this->pluginID))) ; 
				
				// Tous les resultats
				$results = $wpdb->get_results("SELECT * FROM ".$this->table_name) ; 
				
				// On filtre les resultats
				$filtered_results = array() ; 
				$filter = explode(" ", $table->current_filter()) ; 
				foreach ($results as $r) {
				
					// We first look if the url match a selected regexp
					$match_regexp = false ; 
					$match = false ;
					for ($i=0 ; $i<count($regexp_to_be_matched) ; $i++) {
						if (preg_match($regexp_to_be_matched[$i],$r->url)) {
							$match_regexp = true ; 
							break ; 
						}
					}
					
					// We then look if the url match the text entered in the field
					if (count($regexp_to_be_matched)==0) {
						$match = true ;
						foreach ($filter as $fi) {
							if ($fi!="") {
								if ((strpos($r->title, $fi)===FALSE)&&(strpos($r->url, $fi)===FALSE)&&(strpos($r->http_code, $fi)===FALSE)) {
									$match = false ; 
									break ; 
								}
							}
						}
					}
					
					if ($match||$match_regexp) {
						$metatag = "" ; 
						if (is_array(unserialize(str_replace("##&#39;##", "'", $r->metatag)))) {
							foreach (unserialize(str_replace("##&#39;##", "'", $r->metatag)) as $k=>$m) {
								$metatag .= "<p><i><strong>%meta_".$m['name']."%</strong></i> : ".$m['value']."</p>" ; 
							}
						}	
						if ($r->http_code!=0) {					
							if (is_array(unserialize(str_replace("##&#39;##", "'", $r->header)))) {
								foreach (unserialize(str_replace("##&#39;##", "'", $r->header)) as $k=>$m) {
									if (is_string($m)) {
										$metatag .= "<p><i><strong>%head_".$k."%</strong></i> : ".$m."</p>" ; 
									}
								}
							}
						}
						if ($r->title!="") {
							$metatag = "<p><i><strong>%title%</strong></i> : ".$r->title."</p>".$metatag ;
						}
						$occurrence = "" ; 
						$occurrence_array = "[" ; 
						if (is_array(unserialize(str_replace("##&#39;##", "'", $r->occurrence)))) {
							foreach (unserialize(str_replace("##&#39;##", "'", $r->occurrence)) as $m) {
								if (is_array($m)) {
									$postId = intval(str_replace("selectPostWithID", "", $m['id'])) ; 
									$postpost = get_post($postId) ; 
									$occurrence_array .= $postId."," ; 
									if (!is_null($postpost)) {
										$postpost_post_title = $postpost->post_title ; 
									} else {
										$postpost_post_title = __("(Missing page)",$this->pluginID) ; 
									}
									$occurrence .= "<p>".sprintf(__("%s in page %s (%s occurrences)", $this->pluginID), "<code>".$m['text']."</code>", "<a href='".get_permalink($postId)."'>".$postpost_post_title."</a> <span style='font-size:70%'>(<a href='".get_edit_post_link($postId)."'>".__("Edit", $this->pluginID)."</a>)</span>", $m['nb'])."</p>" ; 
								}
							}
						}
						$occurrence_array .= "-1]" ; 

						$filtered_results[] = array($r->url, $occurrence, $r->http_code,  $metatag, $r->id, $r->last_check, $r->failure_first, $r->header, $occurrence_array) ; 
					}
				}
				$count = count($filtered_results);
				$table->set_nb_all_Items($count) ; 
				
				
				// We order the posts page according to the choice of the user
				if ($table->current_orderdir()=="ASC") {
					$ordered_results = SLFramework_Utils::multicolumn_sort($filtered_results, $table->current_ordercolumn()-1, true) ;  
				} else { 
					$ordered_results = SLFramework_Utils::multicolumn_sort($filtered_results, $table->current_ordercolumn()-1, false) ;  
				}

				// on limite l'affichage en fonction des param
				$displayed_results = array_slice($ordered_results,($table->current_page()-1)*$maxnb,$maxnb);
				
				// on affiche
				$ligne = 0 ; 
				foreach ($displayed_results as $r) {
					$ligne++ ; 
					
					$add_img = "" ; 
					
					if ($this->get_param('enable_grabzit')) {
						if (is_file(WP_CONTENT_DIR."/sedlex/links-synthesis/".$blog_fold."/img_".sha1($r[0]).".jpg")) {
							$add_img .= '<p style="text-align:center">'.sprintf(__("Thumbnail generated by %s", $this->pluginID), "<code>Grabz.it</code>").'</p>' ; 
							$add_img .= '<p style="text-align:center"><img src="'.WP_CONTENT_URL."/sedlex/links-synthesis/".$blog_fold."/img_".sha1($r[0]).".jpg".'" /></p>' ; 
						} else {
							$add_img .= '<p style="text-align:center; color:#999999;">'.sprintf(__("Thumbnail not yet generated by %s", $this->pluginID), "<code>Grabz.it</code>").'</p>' ; 
						}
					}
			
					if ($this->get_param('enable_wkhtmltoimage')) {
						if (is_file(WP_CONTENT_DIR."/sedlex/links-synthesis/".$blog_fold."wk_th_".sha1($r[0].$this->get_param('enable_wkhtmltoimage_winw').$this->get_param('enable_wkhtmltoimage_w').$this->get_param('enable_wkhtmltoimage_h')).".jpg")) {
							$add_img .= '<p style="text-align:center">'.sprintf(__("Thumbnail generated by %s", $this->pluginID), "<code>wkHtmlToImage</code>").'</p>' ; 
							$add_img .= '<p style="text-align:center"><img src="'.WP_CONTENT_URL."/sedlex/links-synthesis/".$blog_fold."wk_th_".sha1($r[0].$this->get_param('enable_wkhtmltoimage_winw').$this->get_param('enable_wkhtmltoimage_w').$this->get_param('enable_wkhtmltoimage_h')).".jpg".'" /></p>' ; 
						} else {
							if (is_file(WP_CONTENT_DIR."/sedlex/links-synthesis/".$blog_fold."wk_".sha1($r[0].$this->get_param('enable_wkhtmltoimage_winw').$this->get_param('enable_wkhtmltoimage_w').$this->get_param('enable_wkhtmltoimage_h')).".log")) {
								$add_img .= '<p style="text-align:center; color:#999999;">'.sprintf(__("%s have generated an error while trying to generate the thumbnail: %s", $this->pluginID), "<code>wkHtmlToImage</code>", file_get_contents(WP_CONTENT_DIR."/sedlex/links-synthesis/".$blog_fold."wk_".sha1($r[0].$this->get_param('enable_wkhtmltoimage_winw').$this->get_param('enable_wkhtmltoimage_w').$this->get_param('enable_wkhtmltoimage_h')).".log")).'</p>' ; 
							} else {
								$add_img .= '<p style="text-align:center; color:#999999;">'.sprintf(__("Thumbnail not yet generated by %s", $this->pluginID), "<code>wkHtmlToImage</code>").'</p>' ; 
							}
						}
					}
					
					$cel_url = new adminCell("<p><a href='".$r[0]."'>".$r[0]."</a></p>".$add_img) ;
					$cel_url->add_action(__("Recheck", $this->pluginID), "recheckURL('".$r[4]."');") ; 
					$cel_occurrence = new adminCell($r[1]) ;
					$status_string = $this->http_status_code_string($r[2], true, true, $r[7]) ; 
					$last_check = "" ; 
					if ($r[2]>=0) {
						$now = $wpdb->get_var("SELECT CURRENT_TIMESTAMP ;") ;
						$nb_days =  floor((strtotime($now) - strtotime($r[5]))/86400) ; 
						if ($nb_days == 0) {
							$last_check = "<p>".sprintf(__("Last check: less than one day ago.", $this->pluginID),$nb_days)."</p>" ; 
						} else if ($nb_days == 1) {
							$last_check = "<p>".sprintf(__("Last check: one day ago.", $this->pluginID),$nb_days)."</p>" ; 
						} else {
							$last_check = "<p>".sprintf(__("Last check: %s days ago.", $this->pluginID),$nb_days)."</p>" ; 
						}
					}
					$redirect_url = "" ; 
					if (($r[2]>=300)&&($r[2]<=399)) {
						$header_array = unserialize(str_replace("##&#39;##", "'", $r[7])) ; 
						if ((is_array($header_array))&&(isset($header_array['redirect_url']))) {
							$redirect_url = "<p>".sprintf(__("Redirection URL: %s.", $this->pluginID),"<a href='".$header_array['redirect_url']."'>".$header_array['redirect_url']."</a>")."</p>" ; 
						} 
					}					
					$first_failure = "" ; 
					if ((($r[2]<200)||($r[2]>=400))&&($r[2]!=-1)&&($r[2]!=-2)) {
						$now = $wpdb->get_var("SELECT CURRENT_TIMESTAMP ;") ;
						$nb_days =  floor((strtotime($now) - strtotime($r[6]))/86400) ; 
						if ($nb_days == 0) {
							$first_failure = "<p>".sprintf(__("First failure: less than one day ago.", $this->pluginID),$nb_days)."</p>" ; 
						} else if ($nb_days == 1) {
							$first_failure = "<p>".sprintf(__("First failure: one day ago.", $this->pluginID),$nb_days)."</p>" ; 
						} else {
							$first_failure = "<p>".sprintf(__("First failure: %s days ago.", $this->pluginID),$nb_days)."</p>" ; 
						}
					}
					$cel_status = new adminCell("<p>".$status_string."</p>".$redirect_url.$last_check.$first_failure) ;
					if ($redirect_url!="") {
						$cel_status->add_action(__("Change to the redirected URL", $this->pluginID), "changeURL('".$r[0]."','".$header_array['redirect_url']."',".$r[8].",".$r[4].");") ; 
					}
					$cel_meta_header = new adminCell("<div id='idmht_".$r[4]."' class='meta_close' onclick='return toogleMetatag(\"".$r[4]."\");'>".$r[3]."</div><div id='sm_mht_".$r[4]."' class='seemore_mht'><a href='#' onclick='return toogleMetatag(\"".$r[4]."\");'>".__("See more keywords...", $this->pluginID)."</a></div><div id='h_mht_".$r[4]."' class='hide_mht'><a href='#' onclick='toogleMetatag(\"".$r[4]."\");'>".__("Hide keywords...", $this->pluginID)."</a></div>") ;
					$table->add_line(array($cel_url, $cel_occurrence, $cel_status, $cel_meta_header), $r[4]) ; 
				}
				echo $table->flush() ; 
				

			$tabs->add_tab(__('All links',  $this->pluginID), ob_get_clean()) ; 	
			
			ob_start() ; 
				$maxnb = 20 ; 
				
				$table = new SLFramework_Table(0, $maxnb, true, true) ;
				$table->title(array(__('URL', $this->pluginID), __('Posts/Articles', $this->pluginID), __('Keywords', $this->pluginID))) ; 
				
				// Tous les resultats qui ont ŽtŽ ignorŽs
				$results = $wpdb->get_results("SELECT * FROM ".$this->table_name." WHERE http_code='-2' ") ; 
				
				// On filtre les resultats
				$filtered_results = array() ; 
				$filter = explode(" ", $table->current_filter()) ; 
				foreach ($results as $r) {
					
					// We then look if the url match the text entered in the field
					$match = true ;
					foreach ($filter as $fi) {
						if ($fi!="") {
							if ((strpos($r->title, $fi)===FALSE)&&(strpos($r->url, $fi)===FALSE)&&(strpos($r->http_code, $fi)===FALSE)) {
								$match = false ; 
								break ; 
							}
						}
					}
					
					if ($match) {
						$metatag = "" ; 
						if (is_array(unserialize(str_replace("##&#39;##", "'", $r->metatag)))) {
							foreach (unserialize(str_replace("##&#39;##", "'", $r->metatag)) as $k=>$m) {
								$metatag .= "<p><i><strong>%meta_".$m['name']."%</strong></i> : ".$m['value']."</p>" ; 
							}
						}	
						if ($r->http_code!=0) {					
							if (is_array(unserialize(str_replace("##&#39;##", "'", $r->header)))) {
								foreach (unserialize(str_replace("##&#39;##", "'", $r->header)) as $k=>$m) {
									if (is_string($m)) {
										$metatag .= "<p><i><strong>%head_".$k."%</strong></i> : ".$m."</p>" ; 
									}
								}
							}
						}
						if ($r->title!="") {
							$metatag = "<p><i><strong>%title%</strong></i> : ".$r->title."</p>".$metatag ;
						}
						$occurrence = "" ; 
						$occurrence_array = "[" ; 
						if (is_array(unserialize(str_replace("##&#39;##", "'", $r->occurrence)))) {
							foreach (unserialize(str_replace("##&#39;##", "'", $r->occurrence)) as $m) {
								if (is_array($m)) {
									$postId = intval(str_replace("selectPostWithID", "", $m['id'])) ; 
									$postpost = get_post($postId) ; 
									$occurrence_array .= $postId."," ; 
									if (!is_null($postpost)) {
										$postpost_post_title = $postpost->post_title ; 
									} else {
										$postpost_post_title = __("(Missing page)",$this->pluginID) ; 
									}
									$occurrence .= "<p>".sprintf(__("%s in page %s (%s occurrences)", $this->pluginID), "<code>".$m['text']."</code>", "<a href='".get_permalink($postId)."'>".$postpost_post_title."</a> <span style='font-size:70%'>(<a href='".get_edit_post_link($postId)."'>".__("Edit", $this->pluginID)."</a>)</span>", $m['nb'])."</p>" ; 
								}
							}
						}
						$occurrence_array .= "-1]" ; 

						$filtered_results[] = array($r->url, $occurrence, $r->http_code,  $metatag, $r->id, $r->last_check, $r->failure_first, $r->header, $occurrence_array) ; 
					}
				}
				$count = count($filtered_results);
				$table->set_nb_all_Items($count) ; 
				
				
				// We order the posts page according to the choice of the user
				if ($table->current_orderdir()=="ASC") {
					$ordered_results = SLFramework_Utils::multicolumn_sort($filtered_results, $table->current_ordercolumn()-1, true) ;  
				} else { 
					$ordered_results = SLFramework_Utils::multicolumn_sort($filtered_results, $table->current_ordercolumn()-1, false) ;  
				}

				// on limite l'affichage en fonction des param
				$displayed_results = array_slice($ordered_results,($table->current_page()-1)*$maxnb,$maxnb);
				
				// on affiche
				$ligne = 0 ; 
				foreach ($displayed_results as $r) {
					$ligne++ ; 
					
					$add_img = "" ; 
					if ($this->get_param('enable_grabzit')) {
						if (is_file(WP_CONTENT_DIR."/sedlex/links-synthesis/".$blog_fold."/img_".sha1($r[0]).".jpg")) {
							$add_img .= '<p style="text-align:center">'.sprintf(__("Thumbnail generated by %s", $this->pluginID), "<code>Grabz.it</code>").'</p>' ; 
							$add_img .= '<p style="text-align:center"><img src="'.WP_CONTENT_URL."/sedlex/links-synthesis/".$blog_fold."/img_".sha1($r[0]).".jpg".'" /></p>' ; 
						} else {
							$add_img .= '<p style="text-align:center; color:#999999;">'.sprintf(__("Thumbnail not yet generated by %s", $this->pluginID), "<code>Grabz.it</code>").'</p>' ; 
						}
					}
			
					if ($this->get_param('enable_wkhtmltoimage')) {
						if (is_file(WP_CONTENT_DIR."/sedlex/links-synthesis/".$blog_fold."wk_th_".sha1($r[0].$this->get_param('enable_wkhtmltoimage_winw').$this->get_param('enable_wkhtmltoimage_w').$this->get_param('enable_wkhtmltoimage_h')).".jpg")) {
							$add_img .= '<p style="text-align:center">'.sprintf(__("Thumbnail generated by %s", $this->pluginID), "<code>wkHtmlToImage</code>").'</p>' ; 
							$add_img .= '<p style="text-align:center"><img src="'.WP_CONTENT_URL."/sedlex/links-synthesis/".$blog_fold."wk_th_".sha1($r[0].$this->get_param('enable_wkhtmltoimage_winw').$this->get_param('enable_wkhtmltoimage_w').$this->get_param('enable_wkhtmltoimage_h')).".jpg".'" /></p>' ; 
						} else {
							if (is_file(WP_CONTENT_DIR."/sedlex/links-synthesis/".$blog_fold."wk_".sha1($r[0].$this->get_param('enable_wkhtmltoimage_winw').$this->get_param('enable_wkhtmltoimage_w').$this->get_param('enable_wkhtmltoimage_h')).".log")) {
								$add_img .= '<p style="text-align:center; color:#999999;">'.sprintf(__("%s have generated an error while trying to generate the thumbnail: %s", $this->pluginID), "<code>wkHtmlToImage</code>", file_get_contents(WP_CONTENT_DIR."/sedlex/links-synthesis/".$blog_fold."wk_".sha1($r[0].$this->get_param('enable_wkhtmltoimage_winw').$this->get_param('enable_wkhtmltoimage_w').$this->get_param('enable_wkhtmltoimage_h')).".log")).'</p>' ; 
							} else {
								$add_img .= '<p style="text-align:center; color:#999999;">'.sprintf(__("Thumbnail not yet generated by %s", $this->pluginID), "<code>wkHtmlToImage</code>").'</p>' ; 
							}
						}
					}
					
					$cel_url = new adminCell("<p><a href='".$r[0]."'>".$r[0]."</a></p>".$add_img) ;
					$cel_url->add_action(__("Do not Ignore", $this->pluginID), "doNotIgnoreURL(".$r[4].");") ; 
					$cel_occurrence = new adminCell($r[1]) ;
					
					$cel_meta_header = new adminCell("<div id='idmht_".$r[4]."' class='meta_close' onclick='return toogleMetatag(\"".$r[4]."\");'>".$r[3]."</div><div id='sm_mht_".$r[4]."' class='seemore_mht'><a href='#' onclick='return toogleMetatag(\"".$r[4]."\");'>".__("See more keywords...", $this->pluginID)."</a></div><div id='h_mht_".$r[4]."' class='hide_mht'><a href='#' onclick='toogleMetatag(\"".$r[4]."\");'>".__("Hide keywords...", $this->pluginID)."</a></div>") ;
					$table->add_line(array($cel_url, $cel_occurrence, $cel_meta_header), $r[4]) ; 
				}
				echo $table->flush() ; 

			$tabs->add_tab(__('Ignored Links',  $this->pluginID), ob_get_clean()) ; 	


			// HOW To
			ob_start() ;
				echo "<p>".__('This plugin allows the supervision of all external links.', $this->pluginID)."</p>" ; 
				echo "<p>".__('It checks that no dead links exists and no redirection have been set up externally.', $this->pluginID)."</p>" ; 
			$howto1 = new SLFramework_Box (__("Purpose of that plugin", $this->pluginID), ob_get_clean()) ; 
			ob_start() ;
				echo "<p>".__('An index of all links in the page/post may be created at the end of the page/post if you want.', $this->pluginID)."</p>" ; 
			$howto2 = new SLFramework_Box (__("Summary of external links", $this->pluginID), ob_get_clean()) ; 
			ob_start() ;
				echo "<p>".__('You may also create a thumbnail of the URL to be displayed when the mouse if over the link.', $this->pluginID)."</p>" ; 
				echo "<p>".__('To create thumbnail, you may use:', $this->pluginID)."</p>" ; 
				echo "<ul style='list-style-type: disc;padding-left:40px;'>" ; 
					echo "<li><p>".sprintf(__("%s service:", $this->pluginID), "Grabz.It")."</p></li>" ; 
						echo "<ul style='list-style-type: circle;padding-left:40px;'>" ; 
							echo "<li><p>".sprintf(__("You have to create an account on the %s website;", $this->pluginID), '<a href="http://grabz.it/">Grabz.it</a>')."</p></li>" ; 
							echo "<li><p>".__("Please be aware that the number of thumbnails created may be restricted. The size of the thumbnail cannot be adapted", $this->pluginID)."</p></li>" ; 
						echo "</ul>" ;
					echo "<li><p>".sprintf(__("%s local service:", $this->pluginID), "wkhtmltoimage")."</p></li>" ; 
						echo "<ul style='list-style-type: circle;padding-left:40px;'>" ; 
							echo "<li><p>".sprintf(__("You have to download the %s binary at %s;", $this->pluginID),"wkhtmltoimage", "<a href='http://wkhtmltopdf.org/downloads.html'>http://wkhtmltopdf.org/</a>")."</p></li>" ; 
							echo "<li><p>".__("This option is only available on Linux server (Windows server are not supported yet).", $this->pluginID)."</p></li>" ; 
						echo "</ul>" ;				
				echo "</ul>" ; 
			$howto3 = new SLFramework_Box (__("Thumbnails", $this->pluginID), ob_get_clean()) ; 
			ob_start() ;
				 echo $howto1->flush() ; 
				 echo $howto2->flush() ; 
				 echo $howto3->flush() ; 
			$tabs->add_tab(__('How To',  $this->pluginID), ob_get_clean() , plugin_dir_url("/").'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_how.png") ; 	
	

			ob_start() ; 
				$params = new SLFramework_Parameters($this, "tab-parameters") ; 
				$params->add_title(__('Create an index of all links',  $this->pluginID)) ; 
				$params->add_param('display', __('Display the index at the bottom of the page/post:',  $this->pluginID)) ; 
				$params->add_comment(__('Deactivating this option may be useful for a fresh install of the plugin. Thus, information on links may be retrieved and the display will be cleaner.',  $this->pluginID)) ; 
				$params->add_param('display_admin', __('Display the index when the user is authenticated:',  $this->pluginID)) ; 
				$params->add_comment(__('This option is useful when you want to see and prepare the display of the plugin, without showing it to the public.',  $this->pluginID)) ; 
				$params->add_param('only_external', __('Display only external links (i.e. to external websites):',  $this->pluginID)) ; 
				$params->add_param('display_in_excerpt', __('Display this synthesis in excerpt:',  $this->pluginID)) ; 
				$params->add_param('html', __('HTML:',  $this->pluginID)) ; 
				$params->add_comment(__('The default value is:',  $this->pluginID)) ; 
				$params->add_comment_default_value('html') ; 
				$params->add_param('html_entry', __('HTML for each entry of the synthesis (see below for custom HTML):',  $this->pluginID)) ; 
				
				$params->add_comment(__('The default value is:',  $this->pluginID)) ; 
				$params->add_comment_default_value('html_entry') ; 
				$comment  = "<br>".sprintf(__("- %s for the title of the page",$this->pluginID), "<code>%title%</code>") ; 
				$comment .= "<br>".sprintf(__("- %s for the num of the link in the page",$this->pluginID), "<code>%num%</code>");
				$comment .= "<br>".sprintf(__("- %s for the HTML anchor used to attach the link in the article to the synthesis (mandatory)",$this->pluginID), "<code>%anchor%</code>");
				$comment .= "<br>".sprintf(__("- %s for the URL of the link",$this->pluginID), "<code>%href%</code>");
				$comment .= "<br>".sprintf(__("- %s for the status of the link check (displayed only when a user is logged)",$this->pluginID), "<code>%admin_status%</code>");
				$comment .= "<br>".__("- and all the keywords indicated in the previous tab",$this->pluginID);
				$comment .= "<br>" ; 
				$comment .= "<br>".sprintf(__("You may also used this : %s to replace an expression.",$this->pluginID), "<code>#REPLACE(word_to_find##word_to_replace##sentence)#</code>");
				$comment .= "<br>".sprintf(__("You may also used this : %s to explode an expression according to a given delimiter and to keep the occurrence %s.",$this->pluginID), "<code>#EXPLODE(delimiter##sentence)(num)#</code>", "<code>delimiter</code>", "<code>num</code>" );
				$params->add_comment(sprintf(__('In this HTML you may use: %s',  $this->pluginID), $comment)) ; 
				$params->add_param('css', __('CSS:',  $this->pluginID)) ; 
				$params->add_comment(__('The default value is:',  $this->pluginID)) ; 
				$params->add_comment_default_value('css') ; 
				
				$params->add_title(__('Retrieve the links information',  $this->pluginID)) ; 
				$params->add_param('display_error_admin', __('Display the status of links (only for authenticated users):',  $this->pluginID)) ; 
				$params->add_param('check_cron', __('Check normal links every (days):',  $this->pluginID)) ;  
				$params->add_param('check_cron_error', __('Check links with errors every (days):',  $this->pluginID)) ;  
				$params->add_param('nb_check', __('Number of links to be check by iteration:',  $this->pluginID)) ;  

				$params->add_title(__('Advanced options',  $this->pluginID)) ; 
				$params->add_param('exclu', __('Pages that should be excluded from any actions of this plugin:',  $this->pluginID)) ;  
				$params->add_param('show_nb_error', __('Show the number of errors in the admin menu:',  $this->pluginID)) ;  
				$params->add_param('handle_anchor', __('Consider links which differ by the anchor as identical (frontend only):',  $this->pluginID)) ;  
				$params->add_comment(sprintf(__('Thus, %s and %s will be consider as identical (only for the display in the frontend)',  $this->pluginID), "<code>http://url/page.html#anchor1</code>", "<code>http://url/page.html#anchor2</code>")) ; 
				$params->add_param('check_presence_anchor', __('When checking the link, check that the page contains an appropriate anchor:',  $this->pluginID)) ;  
				
				$params->add_title(__('Create Thumbnail of external Websites',  $this->pluginID)) ; 
				$params->add_param('show_thb_onover', __('Show the thumbnail if exists, when the mouse is over the link:',  $this->pluginID)) ; 
				$params->add_comment(__('You may use one of the following API or binary (please selected only one).',  $this->pluginID)) ; 
				$params->add_param('enable_grabzit', sprintf(__('Use %s API:',  $this->pluginID), "<a href='http://grabz.it/api/'>Grabz.it</a>"), "","", array("grabzit_Application_Key", "grabzit_Application_Secret")) ; 
				$params->add_param('grabzit_Application_Key', __('Grabz.it Application Key:',  $this->pluginID)) ; 
				$params->add_param('grabzit_Application_Secret', __('Grabz.it Application Secret:',  $this->pluginID)) ; 
		
				$upload_dir = wp_upload_dir() ;
				$command_wk =  $upload_dir['basedir']."/sedlex/wkhtmltox/bin/wkhtmltoimage" ; 
		
				$params->add_param('enable_wkhtmltoimage', sprintf(__('Use the executable %s:',  $this->pluginID), "<a href='http://wkhtmltopdf.org/'>Wkhtmltoimage</a>"), "","", array("enable_wkhtmltoimage_h", "enable_wkhtmltoimage_w", "enable_wkhtmltoimage_winw")) ; 				
				$params->add_comment(sprintf(__('To use this option, you should download the binary of %s compatible with your system and install it in %s',  $this->pluginID), "<a href='http://wkhtmltopdf.org/'>Wkhtmltoimage</a>", "<code>$command_wk</code>")) ; 
				if (is_file($command_wk)) {
					$res = $this->wkurltoimage('http://www.google.com/', 1024, 100, 100) ; 
					if ($res['success']) {
						$params->add_comment(sprintf(__('If you see an image here, it mean it works %s',  $this->pluginID), "<img src='".$res["thumb"]."'>")) ; 
					} else {
						$params->add_comment(sprintf(__('There is a problem with the installation: %s',  $this->pluginID), "<code>".$res["msg"]."</code>")) ; 
					}
				} else {
					$params->add_comment(sprintf(__('For now, it appears that the file %s does not exist. This option cannot work if activated.',  $this->pluginID), "<code>$command_wk</code>")) ; 
				}
				$params->add_param('enable_wkhtmltoimage_winw', __('Width of the window for the screenshot:',  $this->pluginID)) ; 
				$params->add_param('enable_wkhtmltoimage_w', __('Width of the thumbnail:',  $this->pluginID)) ; 
				$params->add_param('enable_wkhtmltoimage_h', __('Height of the thumbnail:',  $this->pluginID)) ; 
	
				$params->add_title(__('Advanced options for the forced analysis',  $this->pluginID)) ; 
				$params->add_param('type_page', __('Type of page to be analysed:',  $this->pluginID)) ; 
				$params->add_param('max_page_to_check', __('Max number of post to be checked when an analysis is forced:',  $this->pluginID)) ; 
	
				$params->add_title_macroblock(__('Custom rule %s',  $this->pluginID), __('Add a new custom rule',  $this->pluginID)) ; 
				$params->add_param('custom_regexp', __('Regexp for this rule:',  $this->pluginID)) ;  
				$params->add_param('custom_display', __('Custom HTML for each entry of the synthesis:',  $this->pluginID)) ;  
				
				$comment  = "<br>".sprintf(__("- %s for the title of the page",$this->pluginID), "<code>%title%</code>") ; 
				$comment .= "<br>".sprintf(__("- %s for the num of the link in the page",$this->pluginID), "<code>%num%</code>");
				$comment .= "<br>".sprintf(__("- %s for the HTML anchor used to attach the link in the article to the synthesis (mandatory)",$this->pluginID), "<code>%anchor%</code>");
				$comment .= "<br>".sprintf(__("- %s for the URL of the link",$this->pluginID), "<code>%href%</code>");
				$comment .= "<br>".sprintf(__("- %s for the status of the link check (displayed only when a user is logged)",$this->pluginID), "<code>%admin_status%</code>");
				$comment .= "<br>".__("- and all the keywords indicated in the previous tab",$this->pluginID);
				$comment .= "<br>";
				$comment .= "<br>".sprintf(__("You may also used this : %s to replace an expression.",$this->pluginID), "<code>#REPLACE(word_to_find##word_to_replace##sentence)#</code>");
				$comment .= "<br>".sprintf(__("You may also used this : %s to explode an expression according to a given delimiter and to keep the occurrence %s.",$this->pluginID), "<code>#EXPLODE(delimiter##sentence)(num)#</code>", "<code>delimiter</code>", "<code>num</code>" );
				$params->add_comment(sprintf(__('In this HTML you may use: %s',  $this->pluginID), $comment)) ; 
				
				
				
				$params->flush() ; 
				
			$tabs->add_tab(__('Parameters',  $this->pluginID), ob_get_clean() , WP_PLUGIN_URL.'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_param.png") ; 	
			
			$frmk = new coreSLframework() ;  
			if (((is_multisite())&&($blog_id == 1))||(!is_multisite())||($frmk->get_param('global_allow_translation_by_blogs'))) {
				ob_start() ; 
					$plugin = str_replace("/","",str_replace(basename(__FILE__),"",plugin_basename( __FILE__))) ; 
					$trans = new SLFramework_Translation($this->pluginID, $plugin) ; 
					$trans->enable_translation() ; 
				$tabs->add_tab(__('Manage translations',  $this->pluginID), ob_get_clean() , WP_PLUGIN_URL.'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_trad.png") ; 	
			}

			ob_start() ; 
				$plugin = str_replace("/","",str_replace(basename(__FILE__),"",plugin_basename( __FILE__))) ; 
				$trans = new SLFramework_Feedback($plugin, $this->pluginID) ; 
				$trans->enable_feedback() ; 
			$tabs->add_tab(__('Give feedback',  $this->pluginID), ob_get_clean() , WP_PLUGIN_URL.'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_mail.png") ; 	
			
			ob_start() ; 
				// A list of plugin slug to be excluded
				$exlude = array('wp-pirate-search') ; 
				// Replace sedLex by your own author name
				$trans = new SLFramework_OtherPlugins("sedLex", $exlude) ; 
				$trans->list_plugins() ; 
			$tabs->add_tab(__('Other plugins',  $this->pluginID), ob_get_clean() , WP_PLUGIN_URL.'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_plug.png") ; 	
			
			echo $tabs->flush() ; 
			
			
			// Before this comment, you may modify whatever you want
			//===============================================================================================
			?>
			<?php echo $this->signature ; ?>
		</div>
		<?php
	}

	/** ====================================================================================================================================================
	* Get an image based on url
	*
	* @return void
	*/

	function wkurltoimage($url, $winw=0, $cropw=0, $croph=0) {
		global $blog_id ; 
	
		// We create the folder for the img files
		$blog_fold = "" ; 
		if (is_multisite()) {
			$blog_fold = $blog_id."/" ; 
		}
		
		if (!is_dir(WP_CONTENT_DIR."/sedlex/links-synthesis/".$blog_fold)) {
			@mkdir(WP_CONTENT_DIR."/sedlex/links-synthesis/".$blog_fold, 0777, true) ; 
		}
		
		$upload_dir = wp_upload_dir() ;
		$command_wk =  $upload_dir['basedir']."/sedlex/wkhtmltox/bin/wkhtmltoimage" ; 
		$path_img = WP_CONTENT_DIR."/sedlex/links-synthesis/".$blog_fold."wk_".sha1($url.$winw.$cropw.$croph).".jpg" ; 
		$url_img = WP_CONTENT_URL."/sedlex/links-synthesis/".$blog_fold."wk_".sha1($url.$winw.$cropw.$croph).".jpg" ;

		$path_th = WP_CONTENT_DIR."/sedlex/links-synthesis/".$blog_fold."wk_th_".sha1($url.$winw.$cropw.$croph).".jpg" ; 
		$url_th = WP_CONTENT_URL."/sedlex/links-synthesis/".$blog_fold."wk_th_".sha1($url.$winw.$cropw.$croph).".jpg" ;
		
		$path_log = WP_CONTENT_DIR."/sedlex/links-synthesis/".$blog_fold."wk_".sha1($url.$winw.$cropw.$croph).".log" ; 
		
		if (is_file($path_img)) {
			@unlink($path_img) ; 
		}
		if (is_file($path_log)) {
			@unlink($path_log) ; 
		}

		if (is_file($command_wk)){
			$additional_cmd = "" ; 
			if ($winw!=0) {
				$additional_cmd .= " --width ".$winw ; 
			}
			if ($cropw==0) {
				$cropw = 200 ; 
			}
			if ($croph==0) {
				$croph = 200 ; 
			}
			// Local file for wkhtmltoimage
			$command = $command_wk.$additional_cmd." ".$url." ".$path_img ; 
			$str = exec($command, $output, $return) ; 
			
			// on teste l'existence du fichier
			if (is_file($path_img)) {
			    list($width, $height) = getimagesize($path_img);
			    $myImage = imagecreatefromjpeg($path_img);
				
				// Si le ratio de largeur est plus grand que le ratio de hauteur, 
				// cela signifie que la contrainte sera la largeur
				if ($cropw/$width > $croph/$height) {
				  $x = 0;
				  $origin_w = $width;
				  $origin_h = $croph*$width/$cropw ;
				// Sinon, 
				// cela signifie que la contrainte sera la hauteur
				} else {
				  $origin_w = $cropw*$height/$croph ;
				  $origin_h = $height;
				  $x = ($width-$origin_w)/2;
				}

				// copying the part into thumbnail
				
				$thumb = imagecreatetruecolor($cropw, $croph);
				imagecopyresampled($thumb, $myImage, 0, 0, $x, 0, $cropw, $croph, $origin_w, $origin_h);

				if (imagejpeg ($thumb, $path_th, 92)) {
					return array("success"=>true, "url"=>$url_img, "thumb"=>$url_th) ; 
				} else {
					@file_put_contents($path_log, sprintf(__("%s has failed.", $this->pluginID), "imagejpeg")) ; 
					return array("success"=>false, "msg"=>sprintf(__("%s has failed.", $this->pluginID), "imagejpeg")) ; 
				}
				
			} else { 
				@file_put_contents($path_log, sprintf(__("%s has failed: %s.", $this->pluginID), "<code>".$command_wk."</code>", "<code>".implode(" - ",$output)."</code>")) ; 
				return array("success"=>false, "msg"=>sprintf(__("%s has failed: %s.", $this->pluginID), "<code>".$command_wk."</code>", "<code>".implode(" - ",$output)."</code>")) ; 
			}
		} else {
			@file_put_contents($path_log, sprintf(__("%s does not exist.", $this->pluginID), "<code>".$command_wk."</code>")) ; 
			return array("success"=>false, "msg"=>sprintf(__("%s does not exist.", $this->pluginID), "<code>".$command_wk."</code>")) ; 
		}
	}
	
	/** ====================================================================================================================================================
	* Display the error
	*
	* @return void
	*/
	
	function displayErrorTable() {
		global $wpdb ; 
		global $blog_id ; 
		
		$blog_fold = "" ; 
		if (is_multisite()) {
			$blog_fold = $blog_id."/" ; 
		}

		$maxnb = 20 ; 

		$synthesis = "<p>".__("All the links are showed here.", $this->pluginID)."</p>" ; 
		$nb_all = $wpdb->get_var("SELECT COUNT(*) FROM ".$this->table_name."") ; 
		$nb_200 = $wpdb->get_var("SELECT COUNT(*) FROM ".$this->table_name." WHERE http_code='200'") ; 
		$nb_notchecked = $wpdb->get_var("SELECT COUNT(*) FROM ".$this->table_name." WHERE http_code='-1'") ; 

		$synthesis .= "<p>".sprintf(__("For now, there are %s links (%s ok, %s with errors and %s not yet checked).", $this->pluginID), $nb_all, $nb_200, $nb_all-$nb_200-$nb_notchecked, $nb_notchecked)."</p>" ; 
		$synthesis .= "<p>".__("You have just to wait until the background process analysis each links or you may also force the analysis (see below).", $this->pluginID)."</p>" ; 
		echo $synthesis ; 
	
		$table = new SLFramework_Table(0, $maxnb, true, true) ;
		$table->title(array(__('URL', $this->pluginID), __('Posts/Articles', $this->pluginID), __('Status', $this->pluginID))) ; 
	
		// Tous les resultats
		$results = $wpdb->get_results("SELECT * FROM ".$this->table_name." WHERE http_code!='200' AND http_code!='-1' AND http_code!='-2'") ; 
	
		$filtered_results = array() ; 
		$filter = explode(" ", $table->current_filter()) ; 
		foreach ($results as $r) {
		
			// We filter the results
			$match = true ;
			foreach ($filter as $fi) {
				if ($fi!="") {
					if ((strpos($r->title, $fi)===FALSE)&&(strpos($r->url, $fi)===FALSE)&&(strpos($r->http_code, $fi)===FALSE)) {
						$match = false ; 
						break ; 
					}
				}
			}
		
			if ($match) {
				$occurrence = "" ; 
				$occurrence_array = "[" ; 
				if (is_array(unserialize(str_replace("##&#39;##", "'", $r->occurrence)))) {
					foreach (unserialize(str_replace("##&#39;##", "'", $r->occurrence)) as $m) {
						if (is_array($m)) {
							$postId = intval(str_replace("selectPostWithID", "", $m['id'])) ;
							$occurrence_array .= $postId."," ; 
							$postpost = get_post($postId) ;
							if (!is_null($postpost)) {
								$postpost_post_title = $postpost->post_title ; 
							} else {
								$postpost_post_title = __("(Missing page)",$this->pluginID) ; 
							}
							$occurrence .= "<p>".sprintf(__("%s in page %s (%s occurrences)", $this->pluginID), "<code>".$m['text']."</code>", "<a href='".get_permalink($postId)."'>".$postpost_post_title."</a> <span style='font-size:70%'>(<a href='".get_edit_post_link($postId)."'>".__("Edit", $this->pluginID)."</a>)</span>", $m['nb'])."</p>" ; 
						}
					}
				}
				$occurrence_array .= "-1]" ; 
				$filtered_results[] = array($r->url, $occurrence, $r->http_code,  "", $r->id, $r->last_check, $r->failure_first, $r->header, $occurrence_array) ; 
			}
		}
		$count = count($filtered_results);
		$table->set_nb_all_Items($count) ; 
	
	
		// We order the posts page according to the choice of the user
		if ($table->current_orderdir()=="ASC") {
			$ordered_results = SLFramework_Utils::multicolumn_sort($filtered_results, $table->current_ordercolumn()-1, true) ;  
		} else { 
			$ordered_results = SLFramework_Utils::multicolumn_sort($filtered_results, $table->current_ordercolumn()-1, false) ;  
		}

		// on limite l'affichage en fonction des param
		$displayed_results = array_slice($ordered_results,($table->current_page()-1)*$maxnb,$maxnb);
	
		// on affiche
		$ligne = 0 ; 
		foreach ($displayed_results as $r) {
			$ligne++ ; 
			
			$add_img = "" ; 
			if ($this->get_param('enable_grabzit')) {
				if (is_file(WP_CONTENT_DIR."/sedlex/links-synthesis/".$blog_fold."/img_".sha1($r[0]).".jpg")) {
					$add_img .= '<p style="text-align:center">'.sprintf(__("Thumbnail generated by %s", $this->pluginID), "<code>Grabz.it</code>").'</p>' ; 
					$add_img .= '<p style="text-align:center"><img src="'.WP_CONTENT_URL."/sedlex/links-synthesis/".$blog_fold."/img_".sha1($r[0]).".jpg".'" /></p>' ; 
				} else {
					$add_img .= '<p style="text-align:center; color:#999999;">'.sprintf(__("Thumbnail not yet generated by %s", $this->pluginID), "<code>Grabz.it</code>").'</p>' ; 
				}
			}
	
			if ($this->get_param('enable_wkhtmltoimage')) {
				if (is_file(WP_CONTENT_DIR."/sedlex/links-synthesis/".$blog_fold."wk_th_".sha1($r[0].$this->get_param('enable_wkhtmltoimage_winw').$this->get_param('enable_wkhtmltoimage_w').$this->get_param('enable_wkhtmltoimage_h')).".jpg")) {
					$add_img .= '<p style="text-align:center">'.sprintf(__("Thumbnail generated by %s", $this->pluginID), "<code>wkHtmlToImage</code>").'</p>' ; 
					$add_img .= '<p style="text-align:center"><img src="'.WP_CONTENT_URL."/sedlex/links-synthesis/".$blog_fold."wk_th_".sha1($r[0].$this->get_param('enable_wkhtmltoimage_winw').$this->get_param('enable_wkhtmltoimage_w').$this->get_param('enable_wkhtmltoimage_h')).".jpg".'" /></p>' ; 
				} else {
					if (is_file(WP_CONTENT_DIR."/sedlex/links-synthesis/".$blog_fold."wk_".sha1($r[0].$this->get_param('enable_wkhtmltoimage_winw').$this->get_param('enable_wkhtmltoimage_w').$this->get_param('enable_wkhtmltoimage_h')).".log")) {
						$add_img .= '<p style="text-align:center; color:#999999;">'.sprintf(__("%s have generated an error while trying to generate the thumbnail: %s", $this->pluginID), "<code>wkHtmlToImage</code>", file_get_contents(WP_CONTENT_DIR."/sedlex/links-synthesis/".$blog_fold."wk_".sha1($r[0].$this->get_param('enable_wkhtmltoimage_winw').$this->get_param('enable_wkhtmltoimage_w').$this->get_param('enable_wkhtmltoimage_h')).".log")).'</p>' ; 
					} else {
						$add_img .= '<p style="text-align:center; color:#999999;">'.sprintf(__("Thumbnail not yet generated by %s", $this->pluginID), "<code>wkHtmlToImage</code>").'</p>' ; 
					}
				}
			}
			
			$cel_url = new adminCell("<p id='url".$r[4]."'><a href='".$r[0]."'>".$r[0]."</a></p><p id='change".$r[4]."' style='display:none;'><input id='newURL".$r[4]."' type='text' value='".$r[0]."' style='width: 100%;'/><br/><input type='button' onclick='modifyURL2(\"".$r[0]."\",\"".$r[4]."\",".$r[8].");' value='".__("Modify", $this->pluginID)."' class='button-primary validButton'/> &nbsp; <input type='button' onclick='annul_modifyURL(".$r[4].")' value='".__("Cancel", $this->pluginID)."' class='button validButton'/></p>".$add_img) ;
			$cel_url->add_action(__("Recheck", $this->pluginID), "recheckURL('".$r[4]."');") ; 
			$cel_url->add_action(__("Modify", $this->pluginID), "modifyURL('".$r[4]."');") ; 
			$cel_url->add_action(__("Ignore", $this->pluginID), "ignoreURL('".$r[4]."');") ; 
					
			$cel_occurrence = new adminCell($r[1]) ;
			$status_string = $this->http_status_code_string($r[2], true, true, $r[7]) ; 
			$last_check = "" ; 
			if ($r[2]>=0) {
				$now = $wpdb->get_var("SELECT CURRENT_TIMESTAMP ;") ;
				$nb_days =  floor((strtotime($now) - strtotime($r[5]))/86400) ; 
				if ($nb_days == 0) {
					$last_check = "<p>".sprintf(__("Last check: less than one day ago.", $this->pluginID),$nb_days)."</p>" ; 
				} else if ($nb_days == 1) {
					$last_check = "<p>".sprintf(__("Last check: one day ago.", $this->pluginID),$nb_days)."</p>" ; 
				} else {
					$last_check = "<p>".sprintf(__("Last check: %s days ago.", $this->pluginID),$nb_days)."</p>" ; 
				}
			}
			$redirect_url = "" ; 
			if (($r[2]>=300)&&($r[2]<=399)) {
				$header_array = unserialize(str_replace("##&#39;##", "'", $r[7])) ; 
				if ((is_array($header_array))&&(isset($header_array['redirect_url']))) {
					$redirect_url = "<p>".sprintf(__("Redirection URL: %s.", $this->pluginID),"<a href='".$header_array['redirect_url']."'>".$header_array['redirect_url']."</a>")."</p>" ; 
				} 
			}
			$first_failure = "" ; 
			if ((($r[2]<200)||($r[2]>=400))&&($r[2]!=-1)) {
				$now = $wpdb->get_var("SELECT CURRENT_TIMESTAMP ;") ;
				$nb_days =  floor((strtotime($now) - strtotime($r[6]))/86400) ; 
				if ($nb_days == 0) {
					$first_failure = "<p>".sprintf(__("First failure: less than one day ago.", $this->pluginID),$nb_days)."</p>" ; 
				} else if ($nb_days == 1) {
					$first_failure = "<p>".sprintf(__("First failure: one day ago.", $this->pluginID),$nb_days)."</p>" ; 
				} else {
					$first_failure = "<p>".sprintf(__("First failure: %s days ago.", $this->pluginID),$nb_days)."</p>" ; 
				}
			}
			$cel_status = new adminCell("<p>".$status_string."</p>".$redirect_url.$last_check.$first_failure) ;
			if ($redirect_url!="") {
				$cel_status->add_action(__("Change to the redirected URL", $this->pluginID), "changeURL('".$r[0]."','".$header_array['redirect_url']."',".$r[8].",".$r[4].");") ; 
			}
			$table->add_line(array($cel_url, $cel_occurrence, $cel_status), $r[4]) ; 
		}
		echo $table->flush() ; 
	}
	
	/** ====================================================================================================================================================
	* Ajax Callback to force attachment anaysis
	* @return void
	*/
	function forceAnalysisLinks() {
		global $post, $wpdb ; 
		
		// Initialize the list
		$at = $this->get_param('list_post_id_to_check') ; 
		$li = $this->get_param('list_link_id_to_check') ; 
		if ((empty($at))&&(empty($li))) {
			// We get the post 
			$args = array(
				'posts_per_page'     => intval($this->get_param('max_page_to_check')),
				'orderby'         => 'rand',
				'post_type'       => explode(",",$this->get_param('type_page')),
				'fields'        => 'ids',
				'post_status'     => 'publish' 
			);
			
			$myQuery = new WP_Query( $args ); 

			//Looping through the posts
			$post_temp = array() ; 
			while ( $myQuery->have_posts() ) {
				$myQuery->the_post();
				$post_temp[] = $post;
			}

			// Reset Post Data
			wp_reset_postdata();
			
			$this->set_param('list_post_id_to_check', $post_temp) ; 
			$this->set_param('list_link_id_to_check', array()) ; 
			$this->set_param('nb_post_to_check', count($post_temp)) ; 
			$this->set_param('nb_link_to_check', 0) ; 
		}
		
		$at = $this->get_param('list_post_id_to_check') ; 
		if (!empty($at)) {
			// Get the first post of the list
			$post_temp = $this->get_param('list_post_id_to_check') ; 
			$pid = array_pop($post_temp) ; 
			$this->set_param('list_post_id_to_check', $post_temp) ; 
		
			// On fait semblant que la page est affichŽe pour rŽcupŽrer les liens des pages
			$postpost = get_post($pid) ; 
			$this->_modify_content($postpost->post_content, 'post', false, $pid) ; 
		}
		
		$this->displayErrorTable() ; 	
		
		$at = $this->get_param('list_post_id_to_check') ; 
		if (empty($at)) {
			$li = $this->get_param('list_link_id_to_check') ; 
			if (empty($li)) {
				// Get all links
				$results = $wpdb->get_results("SELECT id FROM ".$this->table_name."") ;
				$link_temp = array() ;  
				foreach($results as $res) {
					$link_temp[] = $res->id ; 	
				}
				$this->set_param('list_link_id_to_check', $link_temp) ; 
				$this->set_param('nb_link_to_check', count($link_temp)) ; 
			}
			// Get the first link of the list
			$link_temp = $this->get_param('list_link_id_to_check') ; 
			$lid = array_pop($link_temp) ; 
			$this->set_param('list_link_id_to_check', $link_temp) ; 
			
			// On verifie le lien
			$this->checkLinksSynthesis(true,$lid) ; 
			
			$li = $this->get_param('list_link_id_to_check') ; 

			if (empty($li)) {
				$this->set_param('nb_post_to_check', 0) ; 
				$this->set_param('nb_link_to_check', 0) ; 
			} else {
				$pb = new SLFramework_Progressbar(500, 20, 100, "PROGRESS POSTS - ".$this->get_param('nb_post_to_check')." / ".$this->get_param('nb_post_to_check')) ; 
				echo "<br>" ; 
				$pb->flush() ;	
				$pc = floor(100*($this->get_param('nb_link_to_check')-count($this->get_param('list_link_id_to_check')))/$this->get_param('nb_link_to_check')) ; 
				$pb = new SLFramework_Progressbar(500, 20, $pc, "PROGRESS LINKS - ".($this->get_param('nb_link_to_check')-count($this->get_param('list_link_id_to_check')))." / ".$this->get_param('nb_link_to_check')) ;
				echo "<br>" ; 
				$pb->flush() ;				
			}
		} else {
			$pc = floor(100*($this->get_param('nb_post_to_check')-count($this->get_param('list_post_id_to_check')))/$this->get_param('nb_post_to_check')) ; 
			$pb = new SLFramework_Progressbar(500, 20, $pc, "PROGRESS POSTS - ".($this->get_param('nb_post_to_check')-count($this->get_param('list_post_id_to_check')))." / ".$this->get_param('nb_post_to_check')) ; 
			echo "<br>" ; 
			$pb->flush() ;	
			$pb = new SLFramework_Progressbar(500, 20, 0, "PROGRESS LINKS - ".__('Wait...', $this->pluginID)) ; 
			echo "<br>" ; 
			$pb->flush() ;	
		}
		
		die() ; 
	}
	
	/** ====================================================================================================================================================
	* Ajax Callback to stop analysis
	* @return void
	*/
	function stopAnalysisLinks() {
		global $post, $wpdb ; 
		
		$this->set_param('list_post_id_to_check', array()) ; 
		$this->set_param('nb_post_to_check', 0) ; 
		$this->set_param('list_link_id_to_check', array()) ; 
		$this->set_param('nb_link_to_check', 0) ; 

		echo "OK" ; 
		
		die() ; 
	}
	
	/** ====================================================================================================================================================
	* Callback updating the table with zip files
	*
	* @return void
	*/
	
	function checkLinksSynthesis($forced=false, $id_tocheck=-1) {
		global $wpdb ; 
		global $blog_id ; 
		
		
		$max_num = $this->get_param('nb_check') ; 
				
		if ($id_tocheck==-1) {
			// sectionner un au hasard 
			$results = $wpdb->get_results("SELECT * FROM ".$this->table_name." WHERE ISNULL(last_check) ORDER BY RAND() LIMIT ".$max_num) ; 
		} else {
			$results = $wpdb->get_results("SELECT * FROM ".$this->table_name." WHERE id='".$id_tocheck."'") ; 
		}
		if ( $results ) {
			//nothing
		} else {
			$results = $wpdb->get_results("SELECT * FROM ".$this->table_name." WHERE (last_check < NOW() - INTERVAL ".$this->get_param('check_cron')." DAY AND http_code='200')  OR (last_check < NOW() - INTERVAL ".$this->get_param('check_cron_error')." DAY AND http_code<>'200' AND http_code<>'-2') ORDER BY RAND() LIMIT ".$max_num) ; 
			if ( $results ) {
				//nothing
			} else {
				if (!$forced) {
					echo "No link to check" ; 
					die() ; 
				} else {
					$results = $wpdb->get_results("SELECT * FROM ".$this->table_name." ORDER BY RAND() LIMIT ".$max_num) ; 
				}
			}
		}
		
		// on recupere le contenu du code distant
		foreach ($results as $r) {	
			// Si le code est -2, cela veux dire qu'on doit ignorer
			if ($r->http_code.""!="-2") {	
				$r->url = str_replace("&amp;", "&", $r->url) ; 
				$redirection = 0 ; 
				$real_code = -1 ; 
				$redirect_link = "" ; 
				$i=0 ; 
				while($i<2) {
					$i++ ; 
					$content = wp_remote_get($r->url, array( 'timeout'=>13, 'redirection'=>$redirection, 'user-agent' => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:23.0) Gecko/20131011 Firefox/23.0', 'headers'=>array('user-agent' => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:23.0) Gecko/20131011 Firefox/23.0'))) ; 
					if ($redirection!=0) {
						break ; 
					}
					if ((!is_wp_error( $content ))&&(isset($content['response']['code']))&&($content['response']['code']>=300)&&($content['response']['code']<=399)) {
						$real_code = $content['response']['code'] ; 
						$redirection = 5 ; 
						$redirect_link = $content['headers']['location'] ; 
					} else {
						$break ; 
					}
				}
				if (!$forced) { 
					echo "Update the link: ".$r->url."\n\r" ; 
				}
				if( is_wp_error( $content ) ) {
					$error_message = $content->get_error_message();
					echo "Something went wrong: $error_message" ;
					$header = esc_sql($error_message) ; 
					echo $header ; 
					if (is_null($r->failure_first)) {
						if (($r->http_code==-2)||($r->http_code=="-2")) {
							$update = "UPDATE ".$this->table_name." SET last_check=NOW(), http_code='-2', failure_first=NOW(), header='".$header."'  WHERE id='".$r->id."'" ; 
					 	} else {
							$update = "UPDATE ".$this->table_name." SET last_check=NOW(), http_code='0', failure_first=NOW(), header='".$header."'  WHERE id='".$r->id."'" ; 
						}
					} else {
						if (($r->http_code==-2)||($r->http_code=="-2")) {
							$update = "UPDATE ".$this->table_name." SET last_check=NOW(), http_code='-2', header='".$header."' WHERE id='".$r->id."'" ; 
						} else {
							$update = "UPDATE ".$this->table_name." SET last_check=NOW(), http_code='0', header='".$header."' WHERE id='".$r->id."'" ; 
						}
					}
					$wpdb->query($update) ; 
				}  else {
					if ($redirect_link!="") {
						$content['headers']['redirect_url'] = $redirect_link ; 
					}
					$res = $this->parser_general($content) ; 

					if ($res['http_code']!=200) {
						if (is_null($r->failure_first)) {
							if (($r->http_code==-2)||($r->http_code=="-2")) {
								$update = "UPDATE ".$this->table_name." SET last_check=NOW(), http_code='-2', failure_first=NOW() WHERE id='".$r->id."'" ; 
							} else {
								$update = "UPDATE ".$this->table_name." SET last_check=NOW(), http_code='".$res['http_code']."', failure_first=NOW() WHERE id='".$r->id."'" ; 
							}
						} else {
							if (($r->http_code==-2)||($r->http_code=="-2")) {
								$update = "UPDATE ".$this->table_name." SET last_check=NOW(), http_code='-2' WHERE id='".$r->id."'" ; 
							} else {
								$update = "UPDATE ".$this->table_name." SET last_check=NOW(), http_code='".$res['http_code']."' WHERE id='".$r->id."'" ; 
							}
						}
					} else {
						if ($real_code!=-1) {
							$res['http_code'] = $real_code ; 
						}
						// On verifie, si on a une ancre, que celle ci se retrouve bien dans la page
						if (($res['http_code']==200)&&($this->get_param('check_presence_anchor'))) {
							$anchor = explode("#",$r->url) ; 
							if (isset($anchor[1])) {
								$content_body  = $content['body'] ; 
								if ((!preg_match("/<a(\s)([^>]*)name='".$anchor[1]."'([^>]*)>/u",$content_body))&&(!preg_match('/<a(\s)([^>]*)name="'.$anchor[1].'"([^>]*)>/u',$content_body))) {
									$res['http_code'] = 210 ; // no anchor found
								}
							}
						}
						if (($r->http_code==-2)||($r->http_code=="-2")) {
							$update = "UPDATE ".$this->table_name." SET last_check=NOW(), http_code='-2', failure_first=NULL, redirect_url='".$res['redirect_url']."', title='".str_replace("'", "&#39;", $res['title'])."', metatag='".str_replace("'", "##&#39;##", $res['metatag'])."', header='".str_replace("'", "##&#39;##", $res['header'])."' WHERE id='".$r->id."'" ; 
						} else {
							$update = "UPDATE ".$this->table_name." SET last_check=NOW(), http_code='".$res['http_code']."', failure_first=NULL, redirect_url='".$res['redirect_url']."', title='".str_replace("'", "&#39;", $res['title'])."', metatag='".str_replace("'", "##&#39;##", $res['metatag'])."', header='".str_replace("'", "##&#39;##", $res['header'])."' WHERE id='".$r->id."'" ; 
						}
					}
					$wpdb->query($update) ; 
				}
			
				// Remove the pages that does not exists anymore in occurrence
				$current_occurrence = unserialize(str_replace("##&#39;##", "'", $r->occurrence)) ; 
				if (is_array($current_occurrence)) {
					$new_occurrence = array() ; 
					foreach ($current_occurrence as $ao) {
						$id_occ = intval(str_replace("selectPostWithID", "", $ao['id'])) ;
						$post_occ = get_post ($id_occ) ; 
						if ((!is_null($post_occ))&&($post_occ->post_status!="trash")) {
							$new_occurrence[] = $ao ; 
						}
					}
					$new_serialized_occurrence = str_replace("'", "##&#39;##", serialize($new_occurrence)) ;
					if ($new_serialized_occurrence != $r->occurrence) {
						if (count($new_occurrence)>0) {
							$update = "UPDATE ".$this->table_name." SET occurrence='".$new_serialized_occurrence."' WHERE id='".$r->id."'" ; 
						} else {
							$update = "DELETE FROM ".$this->table_name." WHERE id='".$r->id."'" ; 
						}
						$wpdb->query($update) ; 
					}
				}	
				
				// Screenshot of the external website
				if ($this->get_param('enable_grabzit')) {
					$grabzIt = new GrabzItClient($this->get_param('grabzit_Application_Key'), $this->get_param('grabzit_Application_Secret'));
					$grabzIt->SetImageOptions($r->url); 
							
					// We create the folder for the img files
					$blog_fold = "" ; 
					if (is_multisite()) {
						$blog_fold = $blog_id."/" ; 
					}
		
					if (!is_dir(WP_CONTENT_DIR."/sedlex/links-synthesis/".$blog_fold)) {
						@mkdir(WP_CONTENT_DIR."/sedlex/links-synthesis/".$blog_fold, 0777, true) ; 
					}

					$filepath = WP_CONTENT_DIR."/sedlex/links-synthesis/".$blog_fold."/img_".sha1($r->url).".jpg";
										
					$grabzIt->SaveTo($filepath);
				}
				
				// ScreenShot with wkhtmltoimage
				if ($this->get_param('enable_wkhtmltoimage')) {
					$this->wkurltoimage($r->url, $this->get_param('enable_wkhtmltoimage_winw'), $this->get_param('enable_wkhtmltoimage_w'), $this->get_param('enable_wkhtmltoimage_h')) ; 
				}
				
			}
		}
		if (!$forced) {
			die() ; 
		}
	}
	
	
	/** ====================================================================================================================================================
	* Get metatag
	*
	* @return array list of parsed element
	*/

	function get_metatag($content) {
		$result = array() ;
		 
		// dans le bon sens
		preg_match_all('/<[^<>]*?meta[^<>]*?(?:name|http-equiv|property|content)=(["\'])((?:[^<>])*?)\1[^<>]*?(?:content|title)=(["\'])((?:[^<>])*?)\3[^<>]*?[\/]?[^<>]*?>/i', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
        	$result[] = array('name'=>trim(strip_tags(strtolower($m[2]))), 'value'=>trim(strip_tags($m[4]))) ; 
        }

		// dans le bon sens
		preg_match_all('/<[^<>]*?meta[^<>]*?(?:content|title)=(["\'])((?:[^<>])*?)\1[^<>]*?(?:name|http-equiv|property|content)=(["\'])((?:[^<>])*?)\3[^<>]*?[\/]?[^<>]*?>/i', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
        	$result[] = array('name'=>trim(strip_tags(strtolower($m[4]))), 'value'=>trim(strip_tags($m[2]))) ; 
        }
        		
		return $result ; 
	}
	
	
	/** ====================================================================================================================================================
	* Parser to get information on page
	*
	* @return array list of parsed element
	*/
	
	function parser_general($content) {
		// Code HTTP
		$res["http_code"] = $content['response']['code'] ; 
		$res["title"] = "" ; 
		$res["redirect_url"] = "" ; 
        
		// On cherche le titre
		preg_match('/<title>([^>]*)<\/title>/si', $content['body'], $match );
		if (isset($match) && is_array($match) && count($match) > 0) {
		    $res["title"] = trim(strip_tags($match[1]));
		}
		
		// On regarde les metatag
		$res["metatag"] = serialize($this->get_metatag($content['body'])) ; 
		// On regarde les metatag
		$res["header"] = serialize($content['headers']) ; 

		return $res ;
	}
	
	/** ====================================================================================================================================================
	* Get Status string for a code
	*
	* @return array list of parsed element
	*/

	function http_status_code_string($code, $include_code=false, $include_color=false, $comment="") {
		switch( $code ) {
			case -2: $string = __('(Asked to be ignored)', $this->pluginID); $color="#8F8F8F"; break;
			case -1: $string = __('(Not yet checked)', $this->pluginID); $color="#8F8F8F"; break;

			case 0: $string = $comment; $color="#CC0000"; $include_code=false; break;

			// 1xx Informational
			case 100: $string = 'Continue'; $color="#33AD5C"; break;
			case 101: $string = 'Switching Protocols'; $color="#33AD5C"; break;
			case 102: $string = 'Processing';  $color="#33AD5C";break; // WebDAV
			case 122: $string = 'Request-URI too long';  $color="#33AD5C"; break; // Microsoft

			// 2xx Success
			case 200: $string = 'OK'; $color="#009933"; break;
			case 201: $string = 'Created'; $color="#009933"; break;
			case 202: $string = 'Accepted'; $color="#009933"; break;
			case 203: $string = 'Non-Authoritative Information'; $color="#009933"; break; // HTTP/1.1
			case 204: $string = 'No Content'; $color="#009933"; break;
			case 205: $string = 'Reset Content'; $color="#009933"; break;
			case 206: $string = 'Partial Content'; $color="#009933"; break;
			case 207: $string = 'Multi-Status'; $color="#009933"; break; // WebDAV
			
			case 210: $string = 'OK but no anchor found'; $color="#FF9900"; break; // Custom error code to indicate that no anchor found

			// 3xx Redirection
			case 300: $string = 'Multiple Choices'; $color="#FF9900"; break;
			case 301: $string = 'Moved Permanently'; $color="#FF9900"; break;
			case 302: $string = 'Found'; $color="#FF9900"; break;
			case 303: $string = 'See Other'; $color="#FF9900"; break; //HTTP/1.1
			case 304: $string = 'Not Modified'; $color="#FF9900"; break;
			case 305: $string = 'Use Proxy'; $color="#FF9900"; break; // HTTP/1.1
			case 306: $string = 'Switch Proxy'; $color="#FF9900"; break; // Depreciated
			case 307: $string = 'Temporary Redirect'; $color="#FF9900"; break; // HTTP/1.1

			// 4xx Client Error
			case 400: $string = 'Bad Request'; $color="#CC0000"; break;
			case 401: $string = 'Unauthorized'; $color="#CC0000"; break;
			case 402: $string = 'Payment Required'; $color="#CC0000"; break;
			case 403: $string = 'Forbidden'; $color="#CC0000"; break;
			case 404: $string = 'Not Found'; $color="#CC0000"; break;
			case 405: $string = 'Method Not Allowed'; $color="#CC0000"; break;
			case 406: $string = 'Not Acceptable'; $color="#CC0000"; break;
			case 407: $string = 'Proxy Authentication Required'; $color="#CC0000"; break;
			case 408: $string = 'Request Timeout'; $color="#CC0000"; break;
			case 409: $string = 'Conflict'; $color="#CC0000"; break;
			case 410: $string = 'Gone'; $color="#CC0000"; break;
			case 411: $string = 'Length Required'; $color="#CC0000"; break;
			case 412: $string = 'Precondition Failed'; $color="#CC0000"; break;
			case 413: $string = 'Request Entity Too Large'; $color="#CC0000"; break;
			case 414: $string = 'Request-URI Too Long'; $color="#CC0000"; break;
			case 415: $string = 'Unsupported Media Type'; $color="#CC0000"; break;
			case 416: $string = 'Requested Range Not Satisfiable'; $color="#CC0000"; break;
			case 417: $string = 'Expectation Failed'; $color="#CC0000"; break;
			case 422: $string = 'Unprocessable Entity';  $color="#CC0000";break; // WebDAV
			case 423: $string = 'Locked'; $color="#CC0000"; break; // WebDAV
			case 424: $string = 'Failed Dependency'; $color="#CC0000"; break; // WebDAV
			case 425: $string = 'Unordered Collection'; $color="#CC0000"; break; // WebDAV
			case 426: $string = 'Upgrade Required'; $color="#CC0000"; break;
			case 449: $string = 'Retry With'; $color="#CC0000"; break; // Microsoft
			case 450: $string = 'Blocked'; $color="#CC0000"; break; // Microsoft

			// 5xx Server Error
			case 500: $string = 'Internal Server Error'; $color="#A30000"; break;
			case 501: $string = 'Not Implemented'; $color="#A30000"; break;
			case 502: $string = 'Bad Gateway'; $color="#A30000"; break;
			case 503: $string = 'Service Unavailable'; $color="#A30000"; break;
			case 504: $string = 'Gateway Timeout'; $color="#A30000"; break;
			case 505: $string = 'HTTP Version Not Supported'; $color="#A30000"; break;
			case 506: $string = 'Variant Also Negotiates'; $color="#A30000"; break;
			case 507: $string = 'Insufficient Storage'; $color="#A30000"; break; // WebDAV
			case 509: $string = 'Bandwidth Limit Exceeded'; $color="#A30000"; break; // Apache
			case 510: $string = 'Not Extended'; $color="#A30000"; break;

			// Unknown code:
			default: $string = 'Unknown '.$code;  $color="#FF0066"; break;
		}
		
		if (($include_code)&&($code!=-1)&&($code!=-2)) {
			$string = $code . ' '.$string;
		}
		if ($include_color) {
			$string = "<span style='color:$color'>".$string."</span>";
		}
		
		return $string;
	}


	/** ====================================================================================================================================================
	* Callback to recheck the URL 
	*
	* @return array list of parsed element
	*/
	
	function recheckURL() {
		global $wpdb ; 
		
		$id = $_POST['id'] ; 

		$update = "UPDATE ".$this->table_name." SET last_check=(NOW()- INTERVAL 400 DAY), http_code='-1' WHERE id='".$id."'" ; 
		$wpdb->query($update) ; 
			
		die() ; 
	}
	
	/** ====================================================================================================================================================
	* Callback to ignore the URL 
	*
	* @return array list of parsed element
	*/
	
	function ignoreURL() {
		global $wpdb ; 
		
		$id = $_POST['id'] ; 

		$update = "UPDATE ".$this->table_name." SET http_code='-2' WHERE id='".$id."'" ; 
		
		$wpdb->query($update) ; 
			
		die() ; 
	}
	
	/** ====================================================================================================================================================
	* Callback to not ignore the URL 
	*
	* @return array list of parsed element
	*/
	
	function doNotIgnoreURL() {
		global $wpdb ; 
		
		$id = $_POST['id'] ; 

		$update = "UPDATE ".$this->table_name." SET http_code='-1' WHERE id='".$id."'" ; 
		$wpdb->query($update) ; 
			
		die() ; 
	}
	

	/** ====================================================================================================================================================
	* Callback to change the URL with a new URL
	*
	* @return array list of parsed element
	*/
	
	function changeURL() {
		global $wpdb ; 
		
		$newURL = $_POST['newURL'] ; 
		$oldURL = $_POST['oldURL'] ; 
		$idPost = $_POST['idPost'] ; 
		$idLink = $_POST['id'] ; 
		
		if (is_array($idPost)) {
			foreach ($idPost as $id) {
				if ($id != -1) {
					$postToReplace = get_post($id, ARRAY_A) ; 
					$old = $postToReplace['post_content'] ; 
					$postToReplace['post_content'] = str_replace("href='".$oldURL."'", "href='".$newURL."'", $postToReplace['post_content']) ; 
					$postToReplace['post_content'] = str_replace('href="'.$oldURL.'"', 'href="'.$newURL.'"', $postToReplace['post_content']) ; 
					if ($old==$postToReplace['post_content']) {
						$oldURL = str_replace("&", "&amp;",$oldURL) ; 
						$postToReplace['post_content'] = str_replace("href='".$oldURL."'", "href='".$newURL."'", $postToReplace['post_content']) ; 
						$postToReplace['post_content'] = str_replace('href="'.$oldURL.'"', 'href="'.$newURL.'"', $postToReplace['post_content']) ; 
						if ($old==$postToReplace['post_content']) {
							echo sprintf(__("Cannot found '%s' in '%s'. There is probably an issue with special characters. Thus edit the article manually to modify this link.", $this->pluginID), $oldURL, $postToReplace['post_title']) ; 
							die() ; 
						}
					}
					wp_update_post($postToReplace) ; 
				}
			}
			$update = "DELETE FROM ".$this->table_name." WHERE id='".$idLink."';" ; 
			$wpdb->query($update) ; 
			
		} else {
			echo "Error: the IDs are not in an array ..." ; 
		} 
		die() ; 
	}

}

$links_synthesis = links_synthesis::getInstance();

?>