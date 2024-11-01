<?php

/******************************************************************************************************************
Plugin Name: WPMU DEFAULT CONTENT IMPORT
Plugin URI: http://www.novietoprofessional.com
Description: WordPressMU plugin for site admin to set defaults content for new blogs. 
Version: 1.0
Author: Md. Mahabubur Rahman (mahabub1212@yahoo.com)

********************************************************************************************************************/

error_reporting(E_ERROR);

set_time_limit(0);

require_once(ABSPATH . 'wp-admin/includes/admin.php');

function wpmu_set_new_blog_default ( $blogid , $userid){
	
	
	global $wp_rewrite, $wpdb, $current_site;
	
	switch_to_blog ( $blogid );
	
	
	wp_delete_link(1);       //delete Wordpress.com blogroll link
    wp_delete_link(2);       //delete Wordpress.org blogroll link
    wp_delete_comment( 1 );  // delete the first comments
    
    
    // close comment on hello world
    $statement = "UPDATE $wpdb->posts SET comment_status = 'closed'  WHERE id = 1";
	$results = $wpdb->query( $statement );
	
	// Delete The First Post
	$statement = "UPDATE $wpdb->posts SET post_status = 'draft'  WHERE id = 1";
	$results = $wpdb->query( $statement );
	
	// read the file content
	$__content = wpmu_defaults_read_the_file();
	
	if ( preg_match('|xmlns:wp="http://wordpress[.]org/export/\d+[.]\d+/"|', $__content) ){
		preg_match('|<wp:base_site_url>(.*?)</wp:base_site_url>|is', $__content, $url);
		$base_url = $url[1];
	}
	
	
	
	// get categories
	$categories = array();
	if ( false !== strpos($__content, '<wp:category>') ) {
		preg_match_all('|<wp:category>(.*?)</wp:category>|is', $__content, $category);
		$categories[] = $category[1];
        if(!empty($categories)) { wpmu_default_process_categories($categories);}
	}

	
	
	//get the tags
	$tags = array();
	if ( false !== strpos($__content, '<wp:tag>') ) {
		preg_match_all('|<wp:tag>(.*?)</wp:tag>|is', $__content, $tag);
		$tags[] = $tag[1];
		if(!empty($tags)) { wpmu_default_advanced_process_tags( $tags );}

	}
	
	
	// get all the posts 
	$processed_ID = array();
	$orphans      = array();
	$total_lines = explode("\n",$__content);
	foreach ($total_lines as $newline){
		
		// if its the start of item tag
		if ( false !== strpos($newline, '<item>') ) {
			$post = '';
			$doing_entry = true;
			continue;
		}
		
		
		// end of the items tag
	   	if ( false !== strpos($newline, '</item>') ) {
	   		$doing_entry = false;
	   		wpmu_default_advanced_process_post($post, $base_url, $processed_ID, $orphans);
	   		continue;
		}
		
		
		// getting the data [post] between item tags
		if ( $doing_entry ) {
			$post .= $newline . "\n";
		}
		
		
	}
	
	
	
	restore_current_blog();
	
}


function wpmu_defaults_Advance_Author ( ){
	global $current_user;
	return $current_user->ID;
}


function wpmu_default_advanced_process_post( $post, $base_url, &$processed_ID, &$orphans){
	global $wpdb;

	$post_ID = (int) wpmu_defaults_advance_get_tag( $post, 'wp:post_id' );
	if($post_ID && !empty($processed_ID[$post_ID])) return false;
	
	$post_title     = wpmu_defaults_advance_get_tag( $post, 'title' );
	$post_date      = wpmu_defaults_advance_get_tag( $post, 'wp:post_date' );
	$post_date_gmt  = wpmu_defaults_advance_get_tag( $post, 'wp:post_date_gmt' );
	$comment_status = wpmu_defaults_advance_get_tag( $post, 'wp:comment_status' );
	$ping_status    = wpmu_defaults_advance_get_tag( $post, 'wp:ping_status' );
	$post_status    = wpmu_defaults_advance_get_tag( $post, 'wp:status' );
	$post_name      = wpmu_defaults_advance_get_tag( $post, 'wp:post_name' );
	$post_parent    = wpmu_defaults_advance_get_tag( $post, 'wp:post_parent' );
	$menu_order     = wpmu_defaults_advance_get_tag( $post, 'wp:menu_order' );
	$post_type      = wpmu_defaults_advance_get_tag( $post, 'wp:post_type' );
	$post_password  = wpmu_defaults_advance_get_tag( $post, 'wp:post_password' );
	$guid           = wpmu_defaults_advance_get_tag( $post, 'guid' );
	$post_author    = wpmu_defaults_advance_get_tag( $post, 'dc:creator' );

	$post_excerpt = wpmu_defaults_advance_get_tag( $post, 'excerpt:encoded' );
	$post_excerpt = preg_replace_callback('|<(/?[A-Z]+)|', create_function('$match', 'return "<" . strtolower($match[1]);'), $post_excerpt);
	$post_excerpt = str_replace('<br>', '<br />', $post_excerpt);
	$post_excerpt = str_replace('<hr>', '<hr />', $post_excerpt);


	$post_content = wpmu_defaults_advance_get_tag ( $post, 'content:encoded' );
	$post_content = preg_replace_callback('|<(/?[A-Z]+)|', create_function('$match', 'return "<" . strtolower($match[1]);'), $post_content);
	$post_content = str_replace('<br>', '<br />', $post_content);
	$post_content = str_replace('<hr>', '<hr />', $post_content);


	preg_match_all('|<category domain="tag">(.*?)</category>|is', $post, $tags);
	$tags = $tags[1];
	$tag_index = 0;
	foreach ($tags as $tag) {
		$tags[$tag_index] = $wpdb->escape(wpmu_defaults_advance_unhtmlentities(str_replace(array ('<![CDATA[', ']]>'), '', $tag)));
		$tag_index++;
	}

	preg_match_all('|<category>(.*?)</category>|is', $post, $categories);
	$categories = $categories[1];

	$cat_index = 0;

	foreach ($categories as $category) {
		$categories[$cat_index] = $wpdb->escape(wpmu_defaults_advance_unhtmlentities(str_replace(array ('<![CDATA[', ']]>'), '', $category)));
		$cat_index++;
	}

	

	$post_exists = post_exists($post_title, '', $post_date);
	if($post_exists){
		$comment_post_ID = $post_id = $post_exists;
	} else {
		$post_parent = (int) $post_parent;
		if($post_parent){
			if($parent = $processed_ID[$post_ID]) $post_parent = $parent;
			else $orphans[intval($post_ID)] = $post_parent;
		}
		

	$post_author = wpmu_defaults_Advance_Author( );
	$postdata = compact('post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_excerpt', 'post_title', 'post_status', 'post_name', 'comment_status', 'ping_status', 'guid', 'post_parent', 'menu_order', 'post_type', 'post_password');
	$postdata['import_id'] = $post_ID;

	if ($post_type == 'attachment') {
		$remote_url = wpmu_defaults_advance_get_tag( $post, 'wp:attachment_url' );
		if ( !$remote_url ) $remote_url = $guid;
		$comment_post_ID = $post_id = wpmu_defaults_advance_process_attachment($postdata, $remote_url, $base_url);
		if ( !$post_id or is_wp_error($post_id) ) return $post_id;
		} else {
			$comment_post_ID = $post_id = wp_insert_post($postdata);
	}

		
    if ( is_wp_error( $post_id ) ) return $post_id;
    if ( $post_id && $post_ID ) { $processed_ID[intval($post_ID)] = intval($post_id); }

	if (count($categories) > 0) {
		$post_cats = array();
		foreach ($categories as $category) {
			if ( '' == $category ) continue;
			$slug = sanitize_term_field('slug', $category, 0, 'category', 'db');
			$cat = get_term_by('slug', $slug, 'category');
			$cat_ID = 0;
			
			if ( ! empty($cat) ) $cat_ID = $cat->term_id;
			if ($cat_ID == 0) { 
				$category = $wpdb->escape($category);
				$cat_ID = wp_insert_category(array('cat_name' => $category));
				if ( is_wp_error($cat_ID) ) continue;
			}
			
			$post_cats[] = $cat_ID;
		}
		
		wp_set_post_categories($post_id, $post_cats);
	}
		
		
		
	if (count($tags) > 0) {
		$post_tags = array();
		
		foreach ($tags as $tag) {
			if ( '' == $tag ) continue;
			$slug = sanitize_term_field('slug', $tag, 0, 'post_tag', 'db');
			$tag_obj = get_term_by('slug', $slug, 'post_tag');
			$tag_id = 0;
			
			if ( ! empty($tag_obj) ) $tag_id = $tag_obj->term_id;
			if ( $tag_id == 0 ) {
				$tag = $wpdb->escape($tag);
				$tag_id = wp_insert_term($tag, 'post_tag');
				if ( is_wp_error($tag_id) ) continue;
				$tag_id = $tag_id['term_id'];
			}
			
			$post_tags[] = intval($tag_id);
		}
				
		wp_set_post_tags($post_id, $post_tags);
			
	}
		
		
	
	preg_match_all('|<wp:comment>(.*?)</wp:comment>|is', $post, $comments);
	$comments = $comments[1];
	$num_comments = 0;\
	if ( $comments) { 
		foreach ($comments as $comment) {
			
			$comment_author       = wpmu_defaults_advance_get_tag( $comment, 'wp:comment_author');
			$comment_author_email = wpmu_defaults_advance_get_tag( $comment, 'wp:comment_author_email');
			$comment_author_IP    = wpmu_defaults_advance_get_tag( $comment, 'wp:comment_author_IP');
			$comment_author_url   = wpmu_defaults_advance_get_tag( $comment, 'wp:comment_author_url');
			$comment_date         = wpmu_defaults_advance_get_tag( $comment, 'wp:comment_date');
			$comment_date_gmt     = wpmu_defaults_advance_get_tag( $comment, 'wp:comment_date_gmt');
			$comment_content      = wpmu_defaults_advance_get_tag( $comment, 'wp:comment_content');
			$comment_approved     = wpmu_defaults_advance_get_tag( $comment, 'wp:comment_approved');
			$comment_type         = wpmu_defaults_advance_get_tag( $comment, 'wp:comment_type');
			$comment_parent       = wpmu_defaults_advance_get_tag( $comment, 'wp:comment_parent');
			
			if ( !$post_exists || !comment_exists($comment_author, $comment_date) ) {
				
				$commentdata = compact('comment_post_ID', 'comment_author', 'comment_author_url', 'comment_author_email', 'comment_author_IP', 'comment_date', 'comment_date_gmt', 'comment_content', 'comment_approved', 'comment_type', 'comment_parent');
				wp_insert_comment($commentdata);
				$num_comments++;
			}
		} 
	  }
	}
	
	
	preg_match_all('|<wp:postmeta>(.*?)</wp:postmeta>|is', $post, $postmeta);
	$postmeta = $postmeta[1];
	if ( $postmeta) {
		foreach ($postmeta as $p) {
			$key   = wpmu_defaults_advance_get_tag( $p, 'wp:meta_key' );
			$value = wpmu_defaults_advance_get_tag( $p, 'wp:meta_value' );
			$value = stripslashes($value); // add_post_meta() will escape.
			wpmu_defaults_advance_process_post_meta($post_id, $key, $value);
	    } 
	}

		do_action('import_post_added', $post_id);
		


	

}



function wpmu_defaults_advance_unhtmlentities($string) { 
	$trans_tbl = get_html_translation_table(HTML_ENTITIES);
	$trans_tbl = array_flip($trans_tbl);
	return strtr($string, $trans_tbl);
}


function wpmu_defaults_advance_process_post_meta($post_id, $key, $value) {
	
	$_key = apply_filters('import_post_meta_key', $key);
	if ( $_key ) {
		add_post_meta( $post_id, $_key, $value );
		do_action('import_post_meta', $post_id, $_key, $value);
	}
}



/* Attachment process */
function wpmu_defaults_advance_process_attachment($postdata, $remote_url, $base_url) {
	if ($remote_url) {
		if ( preg_match('/^\/[\w\W]+$/', $remote_url) )
			$remote_url = rtrim($base_url,'/').$remote_url;
		$upload = wpmu_defaults_advance_fetch_remote_file($postdata, $remote_url);
		if ( is_wp_error($upload) ) {
			return $upload;
		}

		if ( $info = wp_check_filetype($upload['file']) ) {
			$postdata['post_mime_type'] = $info['type'];
		} else {
			return;
		}
		$postdata['guid'] = $upload['url'];
		
		$post_id = wp_insert_attachment($postdata, $upload['file']);
		wp_update_attachment_metadata( $post_id, wp_generate_attachment_metadata( $post_id, $upload['file'] ) );

		if ( preg_match('@^image/@', $info['type']) && $thumb_url = wp_get_attachment_thumb_url($post_id) ) {
			
			$parts = pathinfo($remote_url);
			$ext = $parts['extension'];
			$name = basename($parts['basename'], ".{$ext}");
		}
		return $post_id;

	}
}


/* fetch remote file */	
function wpmu_defaults_advance_fetch_remote_file($post, $url) {
	
	$upload = wp_upload_dir($post['post_date']);
	$file_name = basename($url);
	$upload = wp_upload_bits( $file_name, 0, '', $post['post_date']);
	if ( $upload['error'] ) {
		return new WP_Error( 'upload_dir_error', $upload['error'] );
	}
	$headers = wp_get_http($url, $upload['file']);

	if ( ! $headers ) {
		@unlink($upload['file']);
		return new WP_Error( 'import_file_error', __('Remote server did not respond') );
	}

	if ( $headers['response'] != '200' ) {
		@unlink($upload['file']);
		return new WP_Error( 'import_file_error', sprintf(__('Remote file returned error response %1$d %2$s'), $headers['response'], get_status_header_desc($headers['response']) ) );
	}

	elseif ( isset($headers['content-length']) && filesize($upload['file']) != $headers['content-length'] ) {
		@unlink($upload['file']);
		return new WP_Error( 'import_file_error', __('Remote file is incorrect size') );
	}
	
	$max_size = wpmu_defaults_advance_max_attachment_size();

	if ( !empty($max_size) and filesize($upload['file']) > $max_size ) {
		@unlink($upload['file']);
		return new WP_Error( 'import_file_error', sprintf(__('Remote file is too large, limit is %s', size_format($max_size))) );
	}

	return $upload;

}


function wpmu_defaults_advance_max_attachment_size() {

	return apply_filters('import_attachment_size_limit', 0);

}



/* process tags */
function wpmu_default_advanced_process_tags( $tags ) {
	global $wpdb;
	$tag_names = (array) get_terms('post_tag', 'fields=names');
	while ( $__tags = array_shift($tags) ) {
		$tag_name = trim( wpmu_defaults_advance_get_tag( $__tags, 'wp:tag_name' ));
		// If the category exists we leave it alone
		if ( in_array($tag_name, $tag_names) ) continue;
		//$slug = $this->get_tag( $__tags, 'wp:tag_slug' );
		$description = wpmu_defaults_advance_get_tag( $__tags, 'wp:tag_description' );
		$tagarr = compact('slug', 'description');
		$tag_ID = wp_insert_term($tag_name, 'post_tag', $tagarr);
	}
}


/*--------- PROCESS THE CATEGORIES------------ */
function wpmu_default_process_categories ( $categories ){
	global $wpdb;
    $category_names = (array)  get_terms('category', 'fields=names');

	while ($__category = array_shift($categories)) {
		
		$cat_name              = trim(  wpmu_defaults_advance_get_tag  ( $__category, 'wp:cat_name'           ) );
        $category_nicename	   =        wpmu_defaults_advance_get_tag  ( $__category, 'wp:category_nicename'    );
		$category_description  =        wpmu_defaults_advance_get_tag  ( $__category, 'wp:category_description' );
		$posts_private		   = (int)  wpmu_defaults_advance_get_tag  ( $__category, 'wp:posts_private'        );
		$links_private		   = (int)  wpmu_defaults_advance_get_tag  ( $__category, 'wp:links_private'        );
		$parent                =        wpmu_defaults_advance_get_tag  ( $__category, 'wp:category_parent'      );

		if( trim($parent) == ''){
			if(in_array($cat_name,$category_names)) {$continue = true; }
		} else {
			if(in_array($cat_name,$category_names) && in_array($parent, $category_names)) { $continue = true;}
		}
		if($continue) continue;
		if ( empty($parent) )
				$category_parent = '0';
			else
				$category_parent = category_exists($parent);
		$catarr = compact('category_nicename', 'category_parent', 'posts_private', 'links_private', 'posts_private', 'cat_name', 'category_description');
		$cat_ID = wp_insert_category($catarr);
	

	}

}



function wpmu_defaults_advance_get_tag( $string, $tag ) {

	global $wpdb;

	preg_match("|<$tag.*?>(.*?)</$tag>|is", $string, $return);

	$return = preg_replace('|^<!\[CDATA\[(.*)\]\]>$|s', '$1', $return[1]);

	$return = $wpdb->escape( trim( $return ) );

	return $return;

}


/*--- read the file contents ------*/
function wpmu_defaults_read_the_file ( ){
	
	
	
	
	$_file_url = ABSPATH . "wp-content/plugins/wpmu_default/mahabub.xml";
	$contents = '';
	$fp = fopen($_file_url,'r');
	
	if($fp){
		while (!feof($fp)) {
			$contents .= fread($fp, 8192);
		}
	}
	
	fclose($fp);
	return $contents;
	
}


function wpmu_default_admin_menu(){
	
	add_options_page("DEFAULT IMPORT", "DEFAULT IMPORT", 1, "DEFAULT IMPORT", "default_import_donate_form");  
	
}


function default_import_donate_form(){
	echo '<div align="center">';
	echo '<form action="http://solvease.com/donate/" method="post"> ';
	echo '<input type="image" src="http://redsoxmaniac.wordpress.com/files/2009/11/plugin_donation.jpg" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!"> ';
	echo '<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1"> ';
	echo '</form> ';
}


add_action('wpmu_new_blog', 'wpmu_set_new_blog_default', 100, 2);
add_action('wpmu_activate_blog', 'wpmu_set_new_blog_default', 10,2);
add_action('admin_menu', 'wpmu_default_admin_menu');  

?>