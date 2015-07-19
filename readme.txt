=== LH User Taxonomies ===
Contributors: shawfactor
Donate link: http://lhero.org/plugins/lh-user-taxonomies/
Tags: user, users, taxonomy, custom taxonomy, register_taxonomy, developer
Requires at least: 3.0
Tested up to: 4.2
Stable tag: trunk

Simplify the process of adding support for custom taxonomies for Users. Just use `register_taxonomy` and everything else is taken care of.

== Description ==

This plugin extends the default taxonomy functionality and extends it to users, while automating all the boilerplate code.

Once activated, you can register user taxonomies using the following code:
`
register_taxonomy('profession', 'user', array(
	'public'		=>true,
	'single_value' => false,
	'show_admin_column' => true,
	'labels'		=>array(
		'name'						=>'Professions',
		'singular_name'				=>'Profession',
		'menu_name'					=>'Professions',
		'search_items'				=>'Search Professions',
		'popular_items'				=>'Popular Professions',
		'all_items'					=>'All Professions',
		'edit_item'					=>'Edit Profession',
		'update_item'				=>'Update Profession',
		'add_new_item'				=>'Add New Profession',
		'new_item_name'				=>'New Profession Name',
		'separate_items_with_commas'=>'Separate professions with commas',
		'add_or_remove_items'		=>'Add or remove professions',
		'choose_from_most_used'		=>'Choose from the most popular professions',
	),
	'rewrite'		=>array(
		'with_front'				=>true,
		'slug'						=>'author/profession',
	),
	'capabilities'	=> array(
		'manage_terms'				=>'edit_users',
		'edit_terms'				=>'edit_users',
		'delete_terms'				=>'edit_users',
		'assign_terms'				=>'read',
	),
));
`

Read more about [registering taxonomies in the codex](http://codex.wordpress.org/Function_Reference/register_taxonomy)
This is heavily inspired by previous work by [Justin Tadlock](http://justintadlock.com/archives/2011/10/20/custom-user-taxonomies-in-wordpress) and also forks Damian Gostomskis plugin in the repository to add additional functionality, including:

*Fixes a bug with display of existing user taxonomies in the user-edit screen
*Fixes a bug with taxonomy count in the old plugin where deleting users did not update the count
*Add support for 'single_value' attribute when registering a user taxonomy for taxonomies which should only have one value.
*Properly supports the capabilities associated with the taxonomy when registered.
*Supports 'show_admin_column' attribute when registering the taxonomy in the same way as post taxonomies.

Check out [our documentation][docs] for more information on how to register user taxonomies. 

All tickets for the project are being tracked on [GitHub][].


[docs]: http://lhero.org/plugins/lh-user-taxonomies/
[GitHub]: https://github.com/shawfactor/lh-user-taxonomies


== Installation ==

1. Upload the `lh-user-taxonomies` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Use `register_taxonomy` as shown in the description


== Changelog ==

**1.0 February 28, 2015**  
* Initial release

**1.2 July 15, 2015**  
* Code improvements

**1.3 July 17, 2015**  
* Documentation links

