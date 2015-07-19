<?php
/*
Plugin Name: LH User Taxonomies
Plugin URI: http://lhero.org/plugins/lh-user-taxonomies/
Author: Peter Shaw
Author URI: http://shawfactor.com/
Description: Simplify the process of adding support for custom taxonomies for Users. Just use `register_taxonomy` and everything else is taken care of. With added functions by Peter Shaw.
Version:	1.3

== Changelog ==

= 1.0 =
*Initial Release

= 1.1 =
*Added icon

= 1.2 =
*Added various pathches, props nikolaynesov

= 1.3 =
*Better readme.txt

License:
Released under the GPL license
http://www.gnu.org/copyleft/gpl.html

Copyright 2014  Peter Shaw  (email : pete@localhero.biz)


This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published bythe Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


class LH_User_Taxonomies_plugin {
	private static $taxonomies	= array();
	
	/**
	 * Register all the hooks and filters we can in advance
	 * Some will need to be registered later on, as they require knowledge of the taxonomy name
	 */
	public function __construct() {
		// Taxonomies
		add_action('registered_taxonomy',		array($this, 'registered_taxonomy'), 10, 3);
		
		// Menus
		add_action('admin_menu',				array($this, 'admin_menu'));
		add_filter('parent_file',				array($this, 'parent_menu'));
		
		// User Profiles
		add_action('show_user_profile',			array($this, 'user_profile'));
		add_action('edit_user_profile',			array($this, 'user_profile'));
		add_action('personal_options_update',	array($this, 'save_profile'));
		add_action('edit_user_profile_update',	array($this, 'save_profile'));
		add_action('user_register',	array($this, 'save_profile'));
		add_filter('sanitize_user',				array($this, 'restrict_username'));
		add_filter('manage_users_columns', array($this, 'lh_user_taxonomies_add_user_id_column'));
		add_action('manage_users_custom_column',  array($this, 'lh_user_taxonomies_add_taxonomy_column_content'), 10, 3);
                add_action('pre_user_query', array($this, 'user_query'));
	}
	
	/**
	 * This is our way into manipulating registered taxonomies
	 * It's fired at the end of the register_taxonomy function
	 * 
	 * @param String $taxonomy	- The name of the taxonomy being registered
	 * @param String $object	- The object type the taxonomy is for; We only care if this is "user"
	 * @param Array $args		- The user supplied + default arguments for registering the taxonomy
	 */
	public function registered_taxonomy($taxonomy, $object, $args) {
		global $wp_taxonomies;
		
		// Only modify user taxonomies, everything else can stay as is
		if($object != 'user') return;
		
		// We're given an array, but expected to work with an object later on
		$args	= (object) $args;
		
		// Register any hooks/filters that rely on knowing the taxonomy now
		add_filter("manage_edit-{$taxonomy}_columns",	array($this, 'set_user_column'));
		add_action("manage_{$taxonomy}_custom_column",	array($this, 'set_user_column_values'), 10, 3);
		
		// Set the callback to update the count if not already set
		if(empty($args->update_count_callback)) {
			$args->update_count_callback	= array($this, 'update_count');
		}
		
		// We're finished, make sure we save out changes
		$wp_taxonomies[$taxonomy]		= $args;
		self::$taxonomies[$taxonomy]	= $args;
	}
	
	/**
	 * We need to manually update the number of users for a taxonomy term
	 * 
	 * @see	_update_post_term_count()
	 * @param Array $terms		- List of Term taxonomy IDs
	 * @param Object $taxonomy	- Current taxonomy object of terms
	 */
	public function update_count($terms, $taxonomy) {
		global $wpdb;
		
		foreach((array) $terms as $term) {
			$count	= $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $wpdb->term_relationships, $wpdb->users WHERE $wpdb->term_relationships.object_id = $wpdb->users.ID and $wpdb->term_relationships.term_taxonomy_id = %d", $term));
			
			do_action('edit_term_taxonomy', $term, $taxonomy);
			$wpdb->update($wpdb->term_taxonomy, compact('count'), array('term_taxonomy_id'=>$term));
			do_action('edited_term_taxonomy', $term, $taxonomy);
		}
	}
	
	/**
	 * Add each of the taxonomies to the Users menu
	 * They will behave in the same was as post taxonomies under the Posts menu item
	 * Taxonomies will appear in alphabetical order
	 */
	public function admin_menu() {
		// Put the taxonomies in alphabetical order
		$taxonomies	= self::$taxonomies;
		ksort($taxonomies);
		
		foreach($taxonomies as $key=>$taxonomy) {
			add_users_page(
				$taxonomy->labels->menu_name, 
				$taxonomy->labels->menu_name, 
				$taxonomy->cap->manage_terms, 
				"edit-tags.php?taxonomy={$key}"
			);
		}
	}
	
	/**
	 * Fix a bug with highlighting the parent menu item
	 * By default, when on the edit taxonomy page for a user taxonomy, the Posts tab is highlighted
	 * This will correct that bug
	 */
	function parent_menu($parent = '') {
		global $pagenow;
		
		// If we're editing one of the user taxonomies
		// We must be within the users menu, so highlight that
		if(!empty($_GET['taxonomy']) && $pagenow == 'edit-tags.php' && isset(self::$taxonomies[$_GET['taxonomy']])) {
			$parent	= 'users.php';
		}
		
		return $parent;
	}
	
	/**
	 * Correct the column names for user taxonomies
	 * Need to replace "Posts" with "Users"
	 */
	public function set_user_column($columns) {
		unset($columns['posts']);
		$columns['users']	= __('Users');
		return $columns;
	}
	
	/**
	 * Set values for custom columns in user taxonomies
	 */
	public function set_user_column_values($display, $column, $term_id) {
		if('users' === $column) {
			$term	= get_term($term_id, $_GET['taxonomy']);
			echo $term->count;
		}
	}
	
	/**
	 * Add the taxonomies to the user view/edit screen
	 * 
	 * @param Object $user	- The user of the view/edit screen
	 */
	public function user_profile($user) {
		// Using output buffering as we need to make sure we have something before outputting the header
		// But we can't rely on the number of taxonomies, as capabilities may vary
		ob_start();
		
		foreach(self::$taxonomies as $key=>$taxonomy):
			// Check the current user can assign terms for this taxonomy
			//if(!current_user_can($taxonomy->cap->assign_terms)) continue;
			
			// Get all the terms in this taxonomy
			$terms	= get_terms($key, array('hide_empty'=>false));

//print_r($terms);

$bars = wp_get_object_terms( $user->ID, $key);

$stack = array();

foreach($bars as $bar){

array_push($stack, $bar->slug);

}



if (!$taxonomy->single_value){




?>

			<table class="form-table">
				<tr>
					<th><label for=""><?php _e("Select {$taxonomy->labels->singular_name}")?></label></th>
					<td>
						<?php if(!empty($terms)):?>
							<?php foreach($terms as $term):?>






								<input type="checkbox" name="<?php echo $key?>[]" id="<?php echo "{$key}-{$term->slug}"?>" value="<?php echo $term->slug?>" <?php 

if ($user->ID){
if (in_array($term->slug, $stack)) {

echo "checked=\"checked\"";

}
}

?> />
<label for="<?php echo "{$key}-{$term->slug}"?>"><?php echo $term->name?></label> , 
							<?php endforeach; // Terms?>
						<?php else:?>
							<?php _e("There are no {$taxonomy->labels->name} available.")?>
						<?php endif?>
					</td>
				</tr>
			</table>

<?php

} else {

			?>
			<table class="form-table">
				<tr>
					<th><label for=""><?php _e("Select {$taxonomy->labels->singular_name}")?></label></th>
					<td>
						<?php if(!empty($terms)):?>
							<?php foreach($terms as $term):?>
								<input type="radio" name="<?php echo $key?>" id="<?php echo "{$key}-{$term->slug}"?>" value="<?php echo $term->slug?>" <?php 
if ($user->ID){
if (in_array($term->slug, $stack)) {

echo "checked=\"checked\"";

}
}




?> />
								<label for="<?php echo "{$key}-{$term->slug}"?>"><?php echo $term->name?></label>
							<?php endforeach; // Terms?>
						<?php else:?>
							<?php _e("There are no {$taxonomy->labels->name} available.")?>
						<?php endif?>
					</td>
				</tr>
			</table>
			<?php }
		endforeach; // Taxonomies
		
		// Output the above if we have anything, with a heading
		$output	= ob_get_clean();
		if(!empty($output)) {
			echo '<h3>', __('Taxonomies'), '</h3>';
			echo $output;
		}


	}
	
	/**
	 * Save the custom user taxonomies when saving a users profile
	 * 
	 * @param Integer $user_id	- The ID of the user to update
	 */
public function save_profile($user_id) {

		foreach(self::$taxonomies as $key=>$taxonomy) {
			// Check the current user can edit this user and assign terms for this taxonomy
			if(!current_user_can('edit_user', $user_id) && current_user_can($taxonomy->cap->assign_terms)) return false;

				if (isset($_POST[$key])) {
					if (is_array($_POST[$key])){

						$term = $_POST[$key];

						wp_set_object_terms($user_id, $term, $key, false);

					} else {

						$term	= esc_attr($_POST[$key]);
						wp_set_object_terms($user_id, array($term), $key, false);

					}
				}
				// Save the data

			clean_object_term_cache($user_id, $key);
		}
	}
	
	/**
	 * Usernames can't match any of our user taxonomies
	 * As otherwise it will cause a URL conflict
	 * This method prevents that happening
	 */
	public function restrict_username($username) {
		if(isset(self::$taxonomies[$username])) return '';
		
		return $username;
	}

	/**
	 * Add columns for columns with
	 * show_admin_column
	 */
	public function lh_user_taxonomies_add_user_id_column($columns) {

$args=array(
  'object_type' => array('user'),
'show_admin_column' => true
);


$taxonomies = get_taxonomies( $args, "objects");

foreach ($taxonomies as $taxonomy) {


$columns[$taxonomy->name] = $taxonomy->labels->name;

}


    return $columns;
}

	/**
	 * Just a private function to
	 * populate column content
	 */
	private function lh_user_taxonomies_get_user_taxonomies($user, $taxonomy, $page = null) {

$terms = wp_get_object_terms( $user, $taxonomy);
		if(empty($terms)) { return false; }
		$in = array();
		foreach($terms as $term) {
			$href = empty($page) ? add_query_arg(array($taxonomy => $term->slug), admin_url('users.php')) : add_query_arg(array('user-group' => $term->slug), $page);
			$in[] = sprintf('%s%s%s', '<a href="'.$href.'" title="'.esc_attr($term->description).'">', $term->name, '</a>');
		}

	  	return implode('', $in);
	}


	/**
	 * Add the column content
	 * 
	 */

	public function lh_user_taxonomies_add_taxonomy_column_content($value, $column_name, $user_id) {

if (taxonomy_exists($column_name)) {

return $this->lh_user_taxonomies_get_user_taxonomies($user_id,$column_name);

} else {
    return $value;
}


}
	/**
	 * Alters the User query
	 * to return a different list based on query vars on users.php
	 */

	public function user_query($Query = '') {
		global $pagenow,$wpdb;

if ( $pagenow == 'users.php' ){


$args=array(
  'object_type' => array('user'),
'show_admin_column' => true
);


$taxonomies = get_taxonomies( $args, "objects");




foreach ($taxonomies as $taxonomy) {

if(!empty($_GET[$taxonomy->name])) {



$term = get_term_by('slug', esc_attr($_GET[$taxonomy->name]), $taxonomy->name);



$new_ids = get_objects_in_term($term->term_id, $taxonomy->name);



if (!isset($ids) || empty($ids)){  

$ids = $new_ids;  

} else {   

$ids = array_intersect($ids, $new_ids);

}


}

}

if ($ids){  

$ids = implode(',', wp_parse_id_list( $ids ) );

$Query->query_where .= " AND $wpdb->users.ID IN ($ids)";

}

}		

	
}




}

new LH_User_Taxonomies_plugin;