<?php
/*
 * @package Fm_People
 * @version 1.0
 */
/*
Plugin Name: FM People
Plugin URI: http://www.filmmakermagazine.com
Description: This plugin enables a configurable "People" database.
Author: VitaminM
Version: 1.0
Author URI: http://www.com
*/


/******* Plugin Hooks  ****************************************/

add_action( 'init', 'fmp_init' );
add_action( 'add_meta_boxes', 'fmp_meta_boxes', 10, 2);
add_action( 'save_post', 'fmp_save' );
add_action( 'restrict_manage_posts', 'todo_restrict_manage_posts' );
add_filter( 'parse_query','todo_convert_restrict' );

/**************************************************************/


wp_register_style( 'fmseries-admin', plugins_url('fm_series/css/admin.css') );

//TODO: Turn series & people into two separate classes
//TODO: separate people into an included lib



include_once('fm_series.php');



// Initial Setup Function - Run on wp 'init' hook.
function fmp_init(){

	//Create Custom Person Post Type
	fmp_create_post_type();
	//Alter Admin Screen Column Names
	add_filter('manage_edit-fmp_people_columns', 'fmp_columns');
	add_action("manage_posts_custom_column", "fmp_custom_column");	

	// Create Series taxonomy
	FMSeries::create_taxonomy();
}



function fmp_get_tab_names(){
	
	$default = array(
		'fmp_panel_1' => 'Essential Viewing',
		'fmp_panel_2' => 'Media',
		'fmp_panel_3' => 'Did You Know',
		'fmp_panel_4' => 'Magazine Features',
	);
	return $default;
}



//Create 'FMP People' post type
function fmp_create_post_type() {
	register_post_type( 'fmp_people',
		array(
			'labels' => array(
				'name' => __( 'People' ),
				'singular_name' => __( 'Person' ),
				'add_new' => __('Add Person'),
				'add_new_item' => __('Add New Person'),
				'edit_item' => __('Edit Person'),
				'new_item' => __('New Person'),
				'view_item' => __('View Person'),
				'search_items' => __('Search People')
			),
			'public' => true,
			'has_archive' => true,
			'rewrite' => array( 'slug'=>'people', 'with_front'=>FALSE),
			'supports' => array(
				'title',
				'editor',
				'thumbnail',
				'revisions',
				'comments'
			)
		)
	);
	
	//Heals improper .htaccess rules 	(temp fix)
	#flush_rewrite_rules( false );
}



//Change Column Names
function fmp_columns($gallery_columns) {
 	$new_columns['title'] = 'Name';
 	$new_columns['series'] = 'Series';
	$new_columns['date'] = 'Date';
	return $new_columns;
}


// Custom columns for People
function fmp_custom_column($column){	
	global $post;
	if ("series" == $column){
		echo get_the_term_list( $post->ID, 'fm_series');
	}  
}



// Does stuff…. 
function todo_restrict_manage_posts() {
    global $typenow;
    $args=array( 'public' => true, '_builtin' => false ); 
    $post_types = get_post_types($args);
    if ( in_array($typenow, $post_types) ) {
    $filters = get_object_taxonomies($typenow);
        foreach ($filters as $tax_slug) {
            $tax_obj = get_taxonomy($tax_slug);
            wp_dropdown_categories(array(
                'show_option_all' => __('Show All '.$tax_obj->label ),
                'taxonomy' => $tax_slug,
                'name' => $tax_obj->name,
                'orderby' => 'term_order',
                'selected' => $_GET[$tax_obj->query_var],
                'hierarchical' => $tax_obj->hierarchical,
                'show_count' => false,
                'hide_empty' => true
            ));
        }
    }
}



function todo_convert_restrict($query) {
    global $pagenow;
    global $typenow;
    if ($pagenow=='edit.php') {
        $filters = get_object_taxonomies($typenow);
        foreach ($filters as $tax_slug) {
            $var = &$query->query_vars[$tax_slug];
            if ( isset($var) ) {
                $term = get_term_by('id',$var,$tax_slug);
                $var = $term->slug;
            }
        }
    }
}



// Callback for saving Person
function fmp_save(){
	global $post;
	
	
	if ($post->post_type == 'fmp_people'){
		 
		
		//Must Assign Custom Fields
		$fmp_meta_fields = array(
								'fmp_twitter',
								'fmp_tag',
								'fmp_links',
								'fmp_panel_1',
								'fmp_panel_2',
								'fmp_panel_3',
								'fmp_panel_4',
		);
	
		foreach($fmp_meta_fields as $key){		
			if(array_key_exists($key, $_POST)){	
				$value = $_POST[$key];
				add_post_meta($post->ID, $key, $value, true) or update_post_meta($post->ID, $key, $value);
			}
		}
		
		
		//Get Terms to Check if already added to Series Order List
		$terms = get_the_terms($post->ID, "fm_series");
		if($terms){
			foreach ( $terms as $term ) {
				$t_id = $term->term_id;
				$term_meta = get_option( "taxonomy_$t_id" );
				
				//Add ID to Order List if not already present
					if (!in_array($post->ID, $term_meta['order'])) {
					    array_push($term_meta['order'], $post->ID);
						
						// Save the option array.
						update_option( "taxonomy_$t_id", $term_meta );
					}
			}
		}



	
	}
}
 


// Adds custom form fields to the admin edit screen 
function fmp_meta_boxes($thisTerm, $post) {
	//Special Options Box
	add_meta_box( 
        'fmp_meta',
        'Special Options',
        'fmp_special_options',
        'fmp_people',
        'normal',
        'high' 
    );    
    
	//Extra Links Box
    add_meta_box( 
        'fmp_meta_2',
        'Links',
        'fmp_extra_links',
        'fmp_people',
        'normal',
        'high' 
    );
    
    //Get the first series attached to this person
    $terms = get_the_terms($post->ID, 'fm_series');
    if(is_array($terms)){
    	$term = array_shift($terms); // get the first series
    }
    if($term){
    	$term_id = $term->term_id;
    } else {
    	$term_id = null;
    }
    
	//Panel names must be lowercase and alphanumeric
    $tab_names = FMSeries::get_tab_names($term_id);
    
	foreach($tab_names as $key => $name){
		add_meta_box( 
	        $key,
	        $name,
	        $key, //function
	        'fmp_people',
	        'normal',
	        'high' 
	    ); 
	}
}



//Ennumerates Special Options box fields
function fmp_special_options() {

	// Use nonce for verification
	wp_nonce_field( plugin_basename( __FILE__ ), 'fmp_nonce' );
  
	global $post;
	$custom = get_post_custom($post->ID);
	$twitter = htmlentities($custom['fmp_twitter'][0]);
	$tag = $custom['fmp_tag'][0];
		
	// The actual fields for data entry
	echo <<<EOL
<style>
	.helpTip {color:#999;font-size:.9em;}
	.fmpFormList label.inline { min-width:200px; display:inline-block; }
</style>
<ul class="fmpFormList">
<li>
	<label class="inline" for="fmp_twitter">Twitter HTML Embed Code:</label>
	<input type="text" id="fmp_twitter" name="fmp_twitter" value="$twitter" style="width: 250px;" /><br><span style="font-size: 11px;">( Code can be generated from clicking "Create New" at https://twitter.com/settings/widgets )</span>
</li>
<li><label class="inline" for="fmp_tag">Related Posts Tag(s):</label>
<input type="text" id="fmp_tag" name="fmp_tag" value="$tag" style="width: 250px;"  />
</li>
</ul>
EOL;
 
}



//Ennumerates Extra Links box fields
function fmp_extra_links() {
	
	// Use nonce for verification
	wp_nonce_field( plugin_basename( __FILE__ ), 'fmp_nonce' );
	
	global $post;
	$custom = get_post_custom($post->ID);
	$fmp_links = $custom['fmp_links'][0];	
	
	// The actual fields for data entry
	echo '<ul><li>';	
	echo '<span style="font-size: 11px;">( Use Format:  &lt;a href="link_reference"&gt;Link Text&lt;/a>&lt;br&gt; )</span>  <br> <textarea name="fmp_links" value="" rows="10" cols="60">' . $fmp_links. '</textarea>';	
	echo '</li></ul>';	
}



//Build Admin Panels
function fmp_panel_1() { fmp_build_panel('fmp_panel_1'); }
function fmp_panel_2() { fmp_build_panel('fmp_panel_2'); }
function fmp_panel_3() { fmp_build_panel('fmp_panel_3'); }
function fmp_panel_4() { fmp_build_panel('fmp_panel_4'); }

// Build an admin textbox panel. $name = var name
function fmp_build_panel($name){
	// Use nonce for verification
	wp_nonce_field( plugin_basename( __FILE__ ), 'fmp_nonce' );
	
	global $post;
	$custom = get_post_custom($post->ID);
	$value = $custom[$name][0];
	
	$id = str_replace('_','',strtolower($name));
	
	wp_editor($value, $id, array('textarea_name' => $name, 'wpautop'=>true));
		
}

	
?>
