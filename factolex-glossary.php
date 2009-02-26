<?php
/*
Plugin Name: Factolex Glossary
Plugin URI: http://www.factolex.com/support/wordpress/
Description: Provides a glossary for your blog posts by using definitions from Factolex.com. Please deactivate and re-activate the plugin when upgrading.
Version: 0.2
Author: Factolex.com
Author URI: http://www.factolex.com/
License: GPL v2 - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

/*  Copyright 2009 Alexander Kirk (email: alex@factolex.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

require_once ABSPATH . WPINC . '/class-snoopy.php';

class FactolexGlossary {
	private $term_table, $terms_in_post_table, $fact_table, $plugin_url, $box_title, $defaults;
	
	private function version($what = false) {
		if ($what === "db") return 2;
		if ($what === "js") return 2;
		if ($what === "css") return 1;
		return 0.2;
	}
	
	public function __construct() {
		global $wpdb;
		$this->term_table = $wpdb->prefix . "factolex_term";
		$this->terms_in_post_table = $wpdb->prefix . "factolex_terms_in_post";
		$this->fact_table = $wpdb->prefix . "factolex_fact";
		$this->plugin_url = trailingslashit( get_bloginfo("wpurl") ) . PLUGINDIR . "/" . dirname( plugin_basename(__FILE__) ) . "/";
		$this->box_title = "Factolex Glossary";
		
		$this->defaults = array(
			"border_color" => "#9FC2E1",
			"header_bg_color" => "#BCD0E8",
			"header_font_color" => "#002FA5",
			"bg_color" => "#FFF",
			"font_color" => "#333",
			"link_color" => "#002FA5",
			"width" => "auto",
			"height" => "auto",
		);
	}
	
	public function shortcode_pre25($content) {
		if (!preg_match_all("/\[factolex([^\]]*)\]/i", $content, $matches, PREG_SET_ORDER)) return $content;
		
		$lexicon = array();
		foreach ($matches[0] as $match) {
			if (isset($lexicon[$match[0]])) continue;
			$lexicon[$match[0]] = true;
			
			$atts = array();
			if (preg_match_all("/[^|\b]([^=]+)=([(.*?)])[\s|$]/", $match[1], $attr, PREG_SET_ORDER)) {
				foreach ($attr[0] as $a) {
					$atts[$a[1]] = $a[2];
				}
			}
			
			$l = $this->shortcode($atts);
			$content = str_replace($match[0], $l, $content);
		}
		
		return $content;
	}
	
	public function shortcode($atts, $content = null) {
		if (get_option("factolex_hidden") && !is_user_logged_in()) {
			return "";
		}
		
		$atts = shortcode_atts(array(
				'width' => false,
				'height' => false,
				'style' => false,
				'title' => __("Glossary powered by Factolex.com", "factolex-glossary"),
		), $atts);
		
		if (is_user_logged_in()) {
			$this->updateMyLexicon();
		}
		
		$style = $t = "";
		if ($atts["width"]) {
			if (is_numeric($atts["width"])) $atts["width"] .= "px";
			$style .= $t . "width: " . htmlspecialchars($atts["width"]);
			$t = "; ";
		}
		if ($atts["height"]) {
			if (is_numeric($atts["height"])) $atts["height"] .= "px";
			$style .= $t . "height: " . htmlspecialchars($atts["height"]);
			$t = "; ";
		}
		if ($atts["style"]) {
			$style .= $t . htmlspecialchars($atts["style"]);
			$t = "; ";
		}
		if ($style != "") $style = ' style="' . $style . '"';
		
		$subdomain = get_option("factolex_language");
		if ($subdomain != "en" && $subdomain != "de") $subdomain = "www";
		
		$lexicon = '<div class="factolex-glossary"' . $style . '><div class="factolex-glossary-header"><a href="http://' . $subdomain . '.factolex.com/';
		$username = get_option("factolex_username");
		if ($username) $lexicon .= "user/" . htmlspecialchars($username) . "/lexicon";
		$lexicon .= '">' . htmlspecialchars($atts["title"]) . '</a></div><div class="factolex-glossary-content">';
		$t = "";
		$last_id = false;
		$username = get_option("factolex_username");
		foreach ($this->getTermsForPost(get_the_id()) as $term) {
			if ($last_id !== $term->id) {
				$lexicon .= $t . "<h2><a href=\"" . htmlspecialchars(stripslashes($term->link)) . "\">" . htmlspecialchars(stripslashes($term->title)) . "</a></h2><ul>";
				$t = "</ul>";
				$last_id = $term->id;
				$last_position = false;
			}
			if ($last_position !== false && $last_position < 100 && $term->fact_position >= 100) continue; // if there were facts from the lexicon, skip the remaining default one 
			$lexicon .= "<li>";
			if ($term->fact_type == "picture") {
				$lexicon .= '<img src="' . htmlspecialchars(stripslashes($term->fact_source)) . '" alt="' . htmlspecialchars(stripslashes($term->fact)) . '" title="' . htmlspecialchars(stripslashes($term->fact)) . '" />';
			} else {
				$lexicon .= htmlspecialchars(stripslashes($term->fact));
			}
			$lexicon .= "</li>";
			$last_position = $term->fact_position;
		}
		if ($t == "") {
			$lexicon .= __("No terms have been selected for this glossary.", "factolex-glossary");
		}
		$lexicon .= $t . "</div></div>";
		
		return $lexicon;
	}
	
	private function json_slashes($text) {
		return str_replace(array("\"", "/", "\n", "\r"), array("\\\"", "\\/", "\\n", "\\r"), $text);
	}
	
	public function addTerm() {
		if (!isset($_POST["post"]) || !isset($_POST["term"])) return false;
		global $wpdb;
		
		$now = date("Y-m-d H:i:s");
		$sql = "INSERT IGNORE INTO `" . $this->terms_in_post_table . "` (`postId`, `termId`, `creationDate`) VALUES ('" . $wpdb->escape($_POST["post"]) . "', '" . $wpdb->escape($_POST["term"]) . "', '" . $wpdb->escape($now) . "')";
		$results = $wpdb->query( $sql );
		if (isset($_POST["title"])) {
			$sql = "INSERT INTO `" . $this->term_table . "` (`id`, `title`, `link`, `tags`, `creationDate`) VALUES ('" . $wpdb->escape($_POST["term"]) . "', '" . $wpdb->escape($_POST["title"]) . "', '" . $wpdb->escape($_POST["link"]) . "', '" . $wpdb->escape($_POST["tags"]) . "', '" . $wpdb->escape($now) . "') ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `link` = VALUES(`link`), `tags` = VALUES(`tags`)";
			$results = $wpdb->query( $sql );
			$sql = "INSERT INTO `" . $this->fact_table . "` (`id`, `termId`, `username`, `title`, `type`, `position`, `source`, `creationDate`) VALUES ('" . $wpdb->escape($_POST["fact_id"]) . "', '" . $wpdb->escape($_POST["term"]) . "', NULL, '" . $wpdb->escape($_POST["fact_title"]) . "', '" . $wpdb->escape($_POST["fact_type"]) . "', '100', '" . $wpdb->escape($_POST["fact_source"]) . "', '" . $wpdb->escape($now) . "') ON DUPLICATE KEY UPDATE `username` = VALUES(`username`), `title` = VALUES(`title`), `type` = VALUES(`type`), `position` = VALUES(`position`), `source` = VALUES(`source`)";
			$results = $wpdb->query( $sql );
		}
	}
	
	public function removeTerm() {
		if (!isset($_POST["post"]) || !isset($_POST["term"])) return false;
		global $wpdb;
		$sql = "DELETE FROM `" . $this->terms_in_post_table . "` WHERE `postId` = '" . $wpdb->escape($_POST["post"]) . "' AND `termId` = '" . $wpdb->escape($_POST["term"]) . "' LIMIT 1";
		$results = $wpdb->query( $sql );
	}
	
	public function termsInText() {
		if (!isset($_POST["text"])) return false;
		$this->updateMyLexicon();
		
		// ini_set("html_errors", "off");
		$text = preg_replace("!\[factolex[^\]]*\]!", "", $_POST["text"]);
		$text = preg_replace("!\\n{2,}!", "\n\n", strip_tags(stripslashes($text)));
		$response = $this->queryApi("termsintext", array(), array("text" => $text));
		
		$p = 0;
		$json = "";
		$words = array();
		foreach ($response["words"] as $word) {
			$q = $p;
			$p = strpos($text, $word["title"], $q);
			if ($p === false) {
				$p = $q;
				continue;
			}
			
			$json .= substr($text, $q, $p - $q);
			$p += strlen($word["title"]);
			$json .= '<span class="factolex-term">' . $word["title"] .  "</span>";
			$words[$word["title"]] = $word["terms"];
		}
		$json .= substr($text, $p);
		
		$json = '{"html":"' . $this->json_slashes(nl2br($json)) . '","words":{';
		
		$term_ids = array();
		foreach ($words as $word => $terms) {
			foreach ($terms as $term) {
				$term_ids[] = $term["id"];
			}
		}
		$facts = $this->getFromMyLexicon($term_ids);
		
		$term_fields = array("title", "id", "link", "tags");
		$ttt = "";
		foreach ($words as $word => $terms) {
			$json .= $ttt . '"' . $this->json_slashes($word) . '":[';
			$tt = "";
			foreach ($terms as $term) {
				$json .= $tt . "{";
				$t = "";
				foreach ($term_fields as $field) {
					$json .= $t . '"' . $field . '":"' . $this->json_slashes($term[$field]) . '"';
					$t = ",";
				}
				$field = "synonym_for";
				if (isset($term[$field])) {
					$json .= $t . '"' . $field . '":"' . $this->json_slashes($term[$field]) . '"';
					$t = ",";
				}
				if (isset($facts[$term["id"]])) {
					$fact = $facts[$term["id"]];
				} else {
					$fact = reset($term["facts"]);
				}
				$json .= $t . '"fact":"' . $this->json_slashes($fact["title"]) . '"';
				$json .= ',"fact_source":"' . $this->json_slashes($fact["source"]) . '"';
				$json .= ',"fact_id":"' . $this->json_slashes($fact["id"]) . '"';
				$json .= ',"fact_type":"' . $this->json_slashes($fact["type"]) . '"';
				$json .= "}";
				$tt = ",";
			}
			$json .= "]";
			$ttt = ",";
		}
		$json .= "}}";
		echo $json;
		exit;
	}
	
	private function queryApi($action, $params = array(), $post = array()) {
		$client = new Snoopy();
		$client->agent = "WordPress/" . $GLOBALS['wp_version'] . " FactolexGlossary/" . $this->version() . " " . get_option("home");
		$client->read_timeout = 5;
		$client->use_gzip = true;
		
		$t = "?";
		if ( !is_array($params) ) $params = array();
		
		$params["lang"] = get_option('factolex_language');
		$params["format"] = "php";
		foreach ($params as $k => $v) {
			$action .= $t . $k . "=" . urlencode($v);
			$t = "&";
		}
		
		$url = "http://api.factolex.com/v1/" . $action;
		if (empty($post)) {
			@$client->fetch($url);
		} else {
			@$client->submit($url, $post);
		}
		
		if ( $client->timed_out ) return false;
		if ( $client->status != 200 ) return false;	
		
		return unserialize($client->results);
	}
	
	private function getFromMyLexicon($ids = array()) {
		if (empty($ids)) return array();
		
		global $wpdb;
		
		$term_ids = $t = "";
		foreach ($ids as $id) {
			$term_ids .= $t . $wpdb->escape($id);
			$t = "', '";
		}
		
		$facts = $wpdb->get_results("SELECT `f`.* FROM `" . $this->fact_table . "` `f` WHERE `f`.`termId` IN ('" . $term_ids . "') AND `f`.`username` = '" . $wpdb->escape(get_option("factolex_username")) . "' AND `f`.`position` < 100 ORDER BY `f`.`position` ASC");
		
		$ret = array();
		foreach ($facts as $fact) {
			if (isset($ret[$fact->termId])) continue;
			
			$ret[$fact->termId] = array(
				"id" => $fact->id,
				"title" => $fact->title,
				"source" => $fact->source,
				"type" => $fact->type,
			);
		}
		
		return $ret;
	}
	
	private function updateMyLexicon() {
		$username = get_option("factolex_username");
		if (!$username) return false;
		
		$l = $this->queryApi("lexicon", array("user" => $username, "factids" => 1));
		if (isset($l["error"])) {
			update_option("factolex_username", "");
			return false;
		}
		$now = date("Y-m-d H:i:s");
		
		global $wpdb;
		foreach ($l["lexicon"] as $term) {
			$sql = "INSERT INTO `" . $this->term_table . "` (`id`, `title`, `link`, `tags`, `creationDate`) VALUES ('" . $wpdb->escape($term["id"]) . "', '" . $wpdb->escape($term["title"]) . "', '" . $wpdb->escape($term["link"]) . "', '" . $wpdb->escape($term["tags"]) . "', '" . $wpdb->escape($now) . "') ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `link` = VALUES(`link`), `tags` = VALUES(`tags`)";
			$results = $wpdb->query( $sql );
			
			$sql = "INSERT INTO `" . $this->fact_table . "` (`id`, `termId`, `username`, `position`, `title`, `type`, `source`, `creationDate`) VALUES (";
			
			$c = 1; $t = "";
			foreach ($term["facts"] as $fact) {
				$sql .= $t . "'" . $wpdb->escape($fact["id"]) . "', '" . $wpdb->escape($term["id"]) . "', '" . $wpdb->escape($username) . "', '" . $wpdb->escape($c) . "', '" . $wpdb->escape($fact["title"]) . "', '" . $wpdb->escape($fact["type"]) . "', '" . $wpdb->escape($fact["source"]) . "', '" . $wpdb->escape($now) . "'";
				$t = "), (";
				$c += 1;
			}
			if ($t == "") continue; // no facts? -> don't insert
			$sql .= ") ON DUPLICATE KEY UPDATE `username` = VALUES(`username`), `title` = VALUES(`title`), `position` = VALUES(`position`), `type` = VALUES(`type`), `source` = VALUES(`source`)";
			$results = $wpdb->query( $sql );
		}
	}
	
	public function pluginMenu() {
		global $wp_version;
		
		add_options_page(__("Factolex Glossary Settings", "factolex-glossary"), __("Factolex Glossary", "factolex-glossary"), 8, plugin_basename(__FILE__), array($this, "pluginOptions"));
		
		if ( function_exists( "add_meta_box" )) {
			$context = version_compare( $wp_version, '2.7', '<' ) ? "normal" : "side";
			add_meta_box( "factolex_termbox", $this->box_title, array($this, "boxOnPostPage"), "post" , $context , "high" );
			add_meta_box( "factolex_termbox", $this->box_title, array($this, "boxOnPostPage"), "page" , $context , "high" );
		} else {
			add_action("dbx_post_sidebar", array($this, "boxOnPostPage_pre25" ) );
			add_action("dbx_post_sidebar", array($this, "boxOnPostPage_pre25" ) );
		}
	}
	
	public function getTermsForPost($id) {
		global $wpdb;
		$terms = $wpdb->get_results("SELECT `t`.`id`, `t`.`title`, `t`.`link`, `f`.`title` AS `fact`, `f`.`type` AS `fact_type`, `f`.`source` AS `fact_source`, `f`.`username`, `f`.`position` AS `fact_position` FROM `" . $this->terms_in_post_table . "` `tp` LEFT JOIN `" . $this->term_table . "` `t` ON `t`.`id` = `tp`.`termId` LEFT JOIN `" . $this->fact_table . "` `f` ON `t`.`id` = `f`.`termId` WHERE `tp`.`postId` = '" . $wpdb->escape($id) . "' AND (`f`.`username` IS NULL OR `f`.`username` = '" . $wpdb->escape(get_option("factolex_username")) . "') ORDER BY `t`.`title` ASC, `f`.`position` ASC");
		return $terms;
	}
	
	public function pluginOptions() {
		$lang = get_option('factolex_language');
		?><div class="wrap">
<h2><?php _e("Factolex Glossary Settings", "factolex-glossary"); ?></h2>
<form method="post" action="options.php"><?php wp_nonce_field('update-options'); ?>
	<input type="hidden" name="action" value="update" />
	<input type="hidden" name="page_options" value="factolex_username, factolex_language, factolex_hidden, factolex_border_color, factolex_header_bg_color, factolex_header_font_color, factolex_bg_color, factolex_font_color, factolex_link_color, factolex_width, factolex_height, factolex_delete_data_upon_deactivation" />
	<h3><?php _e("User data", "factolex-glossary"); ?></h3>
	<p><?php echo str_replace("%factolex", '"http://www.factolex.com/"', __("If you have registered at <a href=%factolex>Factolex.com</a>, you will be able change and add multiple facts as explanations.", "factolex-glossary")); ?></p>
	<p><?php _e("You don't have to specify an account: leave the field blank if you want to try it first, or are satisfied with the results as they are right now.", "factolex-glossary"); ?></p>
	<table class="form-table">
	<tr valign="top">
		<th scope="row"><label for="factolex_username"><?php _e("Factolex Username", "factolex-glossary"); ?></label></th>
		<td><input name="factolex_username" type="text" id="factolex_username" value="<?php echo get_option('factolex_username'); ?>" /></td>
	</tr>
	<tr valign="top">
		<th scope="row"><label for="factolex_username"><?php _e("Language", "factolex-glossary"); ?></label></th>
		<td><select name="factolex_language" id="factolex_language">
		 	<option value="en"<?php if (!$lang || $lang == "en") echo ' selected="selected"'; ?>><?php _e("English", "factolex-glossary"); ?></option>
		 	<option value="de"<?php if ($lang == "de") echo ' selected="selected"'; ?>><?php _e("German", "factolex-glossary"); ?></option>
		</select></td>
	</tr>
	<tr valign="top">
		<th scope="row"><label for="factolex_hidden"><?php _e("Hide the glossary", "factolex-glossary"); ?></label></th>
		<td><input name="factolex_hidden" type="checkbox" id="factolex_hidden" value="1"<?php echo (get_option('factolex_hidden') ? ' checked="checked"' : ''); ?> /> <?php _e("only show the plugin output to logged in users <i>(good for testing the plugin without having everyone see it)", "factolex-glossary"); ?></i></td>
	</tr>
	</table>
	<p class="submit">
		<input type="submit" name="Submit" class="button-primary" value="<?php _e("Save Changes", "factolex-glossary"); ?>" />
	</p>
	<h3><?php _e("Layout settings", "factolex-glossary"); ?></h3>
	<p><?php _e("Here you can change the visual appearance of the glossary on your page.", "factolex-glossary"); ?></p>
	<table class="form-table">
	<tr valign="top">
		<th scope="row"><label for="factolex_border_color"><?php _e("Border Color", "factolex-glossary"); ?></label></th>
		<td><input name="factolex_border_color" type="text" id="factolex_border_color" size="8" value="<?php echo get_option('factolex_border_color'); ?>" /> <?php echo str_replace(array("%w3", "%default"), array('"http://www.w3schools.com/css/css_colors.asp"', "<i>" . $this->defaults["border_color"] . "</i>"), __("Format: <a href=%w3>CSS Colors</a>, default: %default", "factolex-glossary")); ?></td>
	</tr>
	<tr valign="top">
		<th scope="row"><label for="factolex_header_bg_color"><?php _e("Header Background Color", "factolex-glossary"); ?></label></th>
		<td><input name="factolex_header_bg_color" type="text" id="factolex_header_bg_color" size="8" value="<?php echo get_option('factolex_header_bg_color'); ?>" /> <?php echo str_replace(array("%w3", "%default"), array('"http://www.w3schools.com/css/css_colors.asp"', "<i>" . $this->defaults["header_bg_color"] . "</i>"), __("Format: <a href=%w3>CSS Colors</a>, default: %default", "factolex-glossary")); ?></i></td>
	</tr>
	<tr valign="top">
		<th scope="row"><label for="factolex_header_font_color"><?php _e("Header Font Color", "factolex-glossary"); ?></label></th>
		<td><input name="factolex_header_font_color" type="text" id="factolex_header_font_color" size="8" value="<?php echo get_option('factolex_header_font_color'); ?>" /> <?php echo str_replace(array("%w3", "%default"), array('"http://www.w3schools.com/css/css_colors.asp"', "<i>" . $this->defaults["header_font_color"] . "</i>"), __("Format: <a href=%w3>CSS Colors</a>, default: %default", "factolex-glossary")); ?></td>
	</tr>
	<tr valign="top">
		<th scope="row"><label for="factolex_bg_color"><?php _e("Main Background Color", "factolex-glossary"); ?></label></th>
		<td><input name="factolex_bg_color" type="text" id="factolex_bg_color" size="8" value="<?php echo get_option('factolex_bg_color'); ?>" /> <?php echo str_replace(array("%w3", "%default"), array('"http://www.w3schools.com/css/css_colors.asp"', "<i>" . $this->defaults["bg_color"] . "</i>"), __("Format: <a href=%w3>CSS Colors</a>, default: %default", "factolex-glossary")); ?></td>
	</tr>
	<tr valign="top">
		<th scope="row"><label for="factolex_font_color"><?php _e("Main Font Color", "factolex-glossary"); ?></label></th>
		<td><input name="factolex_font_color" type="text" id="factolex_font_color" size="8" value="<?php echo get_option('factolex_font_color'); ?>" /> <?php echo str_replace(array("%w3", "%default"), array('"http://www.w3schools.com/css/css_colors.asp"', "<i>" . $this->defaults["font_color"] . "</i>"), __("Format: <a href=%w3>CSS Colors</a>, default: %default", "factolex-glossary")); ?></td>
	</tr>
	<tr valign="top">
		<th scope="row"><label for="factolex_link_color"><?php _e("Link Color", "factolex-glossary"); ?></label></th>
		<td><input name="factolex_link_color" type="text" id="factolex_link_color" size="8" value="<?php echo get_option('factolex_link_color'); ?>" /> <?php echo str_replace(array("%w3", "%default"), array('"http://www.w3schools.com/css/css_colors.asp"', "<i>" . $this->defaults["link_color"] . "</i>"), __("Format: <a href=%w3>CSS Colors</a>, default: %default", "factolex-glossary")); ?></td>
	</tr>
	<tr valign="top">
		<th scope="row"><label for="factolex_width"><?php _e("Width", "factolex-glossary"); ?></label></th>
		<td><input name="factolex_width" type="text" id="factolex_width" size="6" value="<?php echo get_option('factolex_width'); ?>" /> <?php echo str_replace(array("%w3", "%default"), array('"http://www.w3schools.com/css/pr_dim_width.asp"', "<i>" . $this->defaults["width"] . "</i>"), __("Format: <a href=%w3>CSS Dimensions</a>, default: %default", "factolex-glossary")); ?></td>
	</tr>
	<tr valign="top">
		<th scope="row"><label for="factolex_height"><?php _e("Height", "factolex-glossary"); ?></label></th>
		<td><input name="factolex_height" type="text" id="factolex_height" size="6" value="<?php echo get_option('factolex_height'); ?>" /> <?php echo str_replace(array("%w3", "%default"), array('"http://www.w3schools.com/css/pr_dim_height.asp"', "<i>" . $this->defaults["height"] . "</i>"), __("Format: <a href=%w3>CSS Dimensions</a>, default: %default", "factolex-glossary")); ?></td>
	</tr>
	</table>
	<p class="submit">
		<input type="submit" name="Submit" class="button-primary" value="<?php _e("Save Changes", "factolex-glossary"); ?>" />
	</p>
	
	<h3><?php _e("Deactivation", "factolex-glossary"); ?></h3>
	<p><?php _e("Use this checkbox if you want to start over with the Factolex Glossary. Activate the checkbox, click Save and then deactivate and re-activate the plugin.", "factolex-glossary"); ?></p>
	<p><?php _e("<strong>Note:</strong> This will delete all the settings for this plugin and your term selections for your blog posts.", "factolex-glossary"); ?></p>
	<table class="form-table">
	<tr valign="top">
		<th scope="row"><label for="factolex_delete_data_upon_deactivation"><?php _e("Delete data upon deactivation", "factolex-glossary");?></label></th>
		<td><input name="factolex_delete_data_upon_deactivation" type="checkbox" id="factolex_delete_data_upon_deactivation" value="1"<?php echo (get_option('factolex_delete_data_upon_deactivation') ? ' checked="checked"' : ''); ?> /></i></td>
	</tr>
	</table>

<p class="submit">
	<input type="submit" name="Submit" class="button-primary" value="<?php _e("Save Changes", "factolex-glossary"); ?>" />
</p>
</form>
</div><?php
	}
	
	public function css() {
		wp_enqueue_style("factolex", $this->plugin_url . "factolex-glossary.css", false, $this->version("css"));
	}
	
	public function javascripts() {
		global $wp_version;
		if (version_compare( $wp_version, '2.7', '<' )) {
		    echo '<link rel="stylesheet" type="text/css" href="' . $this->plugin_url . "factolex-glossary.css?ver=" . $this->version("css") . '" />';
		}
		wp_enqueue_script("jquery");
		wp_enqueue_script("jquery-form");
		wp_enqueue_script("factolex", $this->plugin_url . "factolex-glossary.js", array("jquery", "jquery-form"), $this->version("js"));
		wp_localize_script("factolex", "factolexL10N", array(
			"loadingTerms" => __("Loading terms...", "factolex-glossary"),
			"errorLoading" => __("Either an error occurred when loading the terms from the Factolex API, or your text did not contain any terms available at Factolex.com. Please <a href=%tryagain>try again</a> or <a href=%close>close this</a>.", "factolex-glossary"),
			"explanation" => __("Click the terms that you want to enlist in your glossary and select the meaning by clicking on it. The term will appear in the box on the right. <a href=%close>Close this</a> when you are finished.", "factolex-glossary"),
			"add" => __("Add", "factolex-glossary"),
			"tags" => __("Tags:", "factolex-glossary"),
			"alternateSpelling" => __("alternate spelling", "factolex-glossary"),
			"originalSpelling" => __("main term spelling", "factolex-glossary"),
		));
		
    	?><style type="text/css">
<!--
.factolex-glossary {
	border-color: <?php echo htmlspecialchars(get_option("factolex_border_color")); ?>;
}
.factolex-glossary, .factolex-glossary .factolex-glossary-content h2 {
	color: <?php echo htmlspecialchars(get_option("factolex_font_color")); ?>;
	background-color: <?php echo htmlspecialchars(get_option("factolex_bg_color")); ?>;
}
.factolex-glossary .factolex-glossary-header {
		color: <?php echo htmlspecialchars(get_option("factolex_header_font_color")); ?>;
		background-color: <?php echo htmlspecialchars(get_option("factolex_header_bg_color")); ?>;
}
.factolex-glossary .factolex-glossary-header a, .factolex-glossary .factolex-glossary-header a:link, .factolex-glossary .factolex-glossary-header a:visited {
	color: <?php echo htmlspecialchars(get_option("factolex_header_font_color")); ?>;
}
.factolex-glossary .factolex-glossary-header a:hover {
	color: <?php echo htmlspecialchars(get_option("factolex_header_font_color")); ?>;
}
.factolex-glossary .factolex-glossary-content a, .factolex-glossary .factolex-glossary-content a:link, .factolex-glossary .factolex-glossary-content a:visited {
	color: <?php echo htmlspecialchars(get_option("factolex_link_color")); ?>;
}
.factolex-glossary .factolex-glossary-content h2  a, .factolex-glossary .factolex-glossary-content h2  a:link, .factolex-glossary .factolex-glossary-content h2  a:visited {
	color: <?php echo htmlspecialchars(get_option("factolex_font_color")); ?>;
}
.factolex-glossary .factolex-glossary-content a:hover {
	color: <?php echo htmlspecialchars(get_option("factolex_link_color")); ?>;
}
-->
</style><?php
	}
	
	public function boxOnPostPage_pre25() {
		echo '<div class="dbx-b-ox-wrapper">' . "\n";
		echo '<fieldset id="myplugin_fieldsetid" class="dbx-box">' . "\n";
		echo '<div class="dbx-h-andle-wrapper"><h3 class="dbx-handle">';
		echo $this->box_title;
		echo "</h3></div>";   

		echo '<div class="dbx-c-ontent-wrapper"><div class="dbx-content">';
		$this->boxOnPostPage();
		echo "</div></div></fieldset></div>\n";
	}
	
	public function boxOnPostPage() {
		?><p><?php _e("Insert <tt>[factolex]</tt> where the glossary should appear in your post.", "factolex-glossary"); ?></p>
<ul id="factolex-terms-in-glossary"><?php
		global $post_ID;
		$last_id = false;
		foreach ($this->getTermsForPost($post_ID) as $term) {
			if ($last_id === $term->id) continue; // just one line per term here
			$last_id = $term->id;
			
			?><li factolexid="<?php echo $term->id; ?>"><a href="<?php echo htmlspecialchars(stripslashes($term->link)); ?>" class="term"><?php echo htmlspecialchars(stripslashes($term->title)); ?></a> <a href="" class="remove"><?php _e("Remove", "factolex-glossary"); ?></a><br /><span class="fact"><?php echo htmlspecialchars(stripslashes($term->fact)); ?></span><?php
		}

?></ul>
<p><?php echo str_replace(array("<a>", "</a>"), array('<input type="button" id="factolex-check-button" class="button" tabindex="4" value="', '" />'), __("<a>Check for terms</a> at Factolex.com", "factolex-glossary")); ?></p>
<p><?php _e("<strong>Note:</strong> This will send the text to Factolex.com in order to analyze it.", "factolex-glossary"); ?></p><?php
	}
	
	public function settingsLink( $links ) { 
		array_unshift( $links, '<a href="options-general.php?page=' . plugin_basename(__FILE__) . '">' . __("Settings", "factolex-glossary") . '</a>' ); 
		return $links; 
	}
	
	public function deactivate() {
		if (!get_option("factolex_delete_data_upon_deactivation")) return true;
		
		global $wpdb;
		$sql = "DROP TABLE `" . $this->term_table . "`";
		$results = $wpdb->query( $sql );
		
		$sql = "DROP TABLE `" . $this->fact_table . "`";
		$results = $wpdb->query( $sql );
		
		$sql = "DROP TABLE `" . $this->terms_in_post_table . "`";
		$results = $wpdb->query( $sql );
		
		delete_option("factolex_db_version");
		delete_option("factolex_hidden");
		foreach ($this->defaults as $k => $v) {
			delete_option("factolex_" . $k);
		}
		delete_option("factolex_language");
		delete_option("factolex_delete_data_upon_deactivation");
		
	}
	
	public function setup() {
		global $wpdb;
		
		$current_db_version = get_option("factolex_db_version");
		$upgrade_db = ( $current_db_version !== false && $current_db_version != $this->version("db") );
		
		if ( $current_db_version === false || $upgrade_db || $wpdb->get_var("SHOW TABLES LIKE '" . $this->term_table . "'") != $this->term_table ) {
			if (version_compare( $wp_version, '2.3', '<' )) {
				require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
			} else {
				require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			}
			
			$sql = "CREATE TABLE `" . $this->term_table . "` (
			  `id` char(8) character set ascii collate ascii_bin NOT NULL,
			  `title` varchar(200) character set utf8 collate utf8_general_ci NOT NULL,
			  `link` varchar(240) character set utf8 collate utf8_general_ci NOT NULL,
			  `tags` varchar(70) character set utf8 collate utf8_general_ci NOT NULL,
			  `creationDate` datetime NOT NULL,
			  `lastUpdate` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
			  PRIMARY KEY  (`id`),
			  KEY title (`title`)
			) DEFAULT CHARSET=utf8;
			CREATE TABLE `" . $this->terms_in_post_table . "` (
			  `postId` int(11) NOT NULL,
			  `termId` char(8) character set ascii collate ascii_bin NOT NULL,
			  `creationDate` datetime NOT NULL,
			  PRIMARY KEY  (`postId`, `termId`)
			) DEFAULT CHARSET=utf8;
			CREATE TABLE `" . $this->fact_table . "` (
			  `id` char(6) character set ascii collate ascii_bin NOT NULL,
			  `termId` char(8) character set ascii collate ascii_bin NOT NULL,
			  `username` char(8) character set ascii,
			  `position` tinyint(4) NOT NULL DEFAULT '100',
			  `title` tinytext character set utf8 collate utf8_general_ci NOT NULL,
			  `type` enum('fact','link','picture','coords') character set ascii collate ascii_bin NOT NULL,
			  `source` varchar(255) character set utf8 collate utf8_general_ci NOT NULL,
			  `creationDate` datetime NOT NULL,
			  `lastUpdate` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
			  PRIMARY KEY  (`termId`, `id`),
			  KEY  (`termId`, `position`)
			) DEFAULT CHARSET=utf8;";
			$sql = str_replace("`", "", $sql); // it is actually good practise to use ` for encapsulating field names, but dbDelta doesn't support it
			dbDelta($sql);
			
			if (!$upgrade_db) {
				// for inital data
				add_option("factolex_db_version", $this->version("db"));
				add_option("factolex_hidden", "0");
				foreach ($this->defaults as $k => $v) {
					add_option("factolex_" . $k, $v);
				}
				
				$lang = "en";
				if (substr(WPLANG, 0, 2) == "de") $lang = "de";
				
				add_option("factolex_language", $lang);
				add_option("factolex_delete_data_upon_deactivation", "0");
			} else {
				update_option("factolex_db_version", $this->version("db"));
			}
		}
	}
}

$factolex = new FactolexGlossary;
$factolex_pluginname = plugin_basename(__FILE__); 

$plugin_dir = basename(dirname(__FILE__));
load_plugin_textdomain( "factolex-glossary", "wp-content/plugins/" . $plugin_dir . "/lang", $plugin_dir . "/lang" );

register_activation_hook($factolex_pluginname, array($factolex, "setup"));
register_deactivation_hook($factolex_pluginname, array($factolex, "deactivate"));
add_filter("plugin_action_links_" . $factolex_pluginname, array($factolex, "settingsLink")); 
add_action("admin_menu", array($factolex, "pluginMenu"));
add_action("wp_print_styles", array($factolex, "css"));
add_action("wp_print_scripts", array($factolex, "javascripts"));

// no 2.0.4 support because it doesn't support ajax
add_action("wp_ajax_factolex-checktext", array($factolex, "termsInText"));
add_action("wp_ajax_factolex-add-term", array($factolex, "addTerm"));
add_action("wp_ajax_factolex-remove-term", array($factolex, "removeTerm"));

if (version_compare( $wp_version, '2.5', '<' )) {
	add_action("show", array($factolex, "shortcode_pre25"));
} else {
	add_shortcode("factolex", array($factolex, "shortcode"));
}

