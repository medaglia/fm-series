<?php
/*
	@name				fm_series
	@version			0.3
	@author				Michael Medaglia <mm@vitaminmlabs.com>
	@link				http://vitaminmlabs.com/
*/

/******* Plugin Hooks  ****************************************/

add_filter('manage_edit-fm_series_columns', array('FMSeries','name_columns'));
add_action('manage_fm_series_custom_column', array('FMSeries','custom_column'), 10, 3);
add_action('fm_series_edit_form_fields', array('FMSeries','display_extra_edit_fields'), 10, 2 );
add_action('edited_fm_series', array('FMSeries','save_extra_fields'), 10, 2 );  
add_action('create_fm_series', array('FMSeries','save_extra_fields'), 10, 2 );
add_action('fm_series_edit_form', array('FMSeries','edit_form'), 10, 1);
add_shortcode('list_all_series', 'FMSeries::list_all');
add_action( 'pre_get_posts', array('FMSeries','create_list_view'), 10, 1);

/**************************************************************/



class FMSeries{


	// Create 'FM Series' taxonomy
	public function create_taxonomy() {  
	    register_taxonomy(  
		    'fm_series',  
		    'fmp_people',  
		    array(  
		        'hierarchical' => false,
		        'show_tagcloud' => false,
		        'label' => 'Series',
		        'labels' => array(
		        	'name' => 'Series',
		        	'search_items' => 'Search Series',
		        	'edit_item' => 'Edit Series',
		        	'add_new_item' => 'Add New Series',
		        	'popular_items' => 'Popular Series'
		        ),  
		        'query_var' => true,  
		        'rewrite' => array('slug'=>'series', 'with_front'=>FALSE), 
		    )  
		);  
	
		//Heals improper .htaccess rules 	(temp fix)
		#flush_rewrite_rules( false );	
	}
	
	
	// Default tab names
	function default_tab_names(){
		return array(
			'fmp_panel_1' => 'Essential Viewing',
			'fmp_panel_2' => 'Media',
			'fmp_panel_3' => 'Did You Know',
			'fmp_panel_4' => 'Magazine Features',
		);
	}
	
	
	function get_tab_names($tax_id){
		if($tax_id){
			$term_meta = get_option( "taxonomy_$tax_id" );
			if(!empty($term_meta[tab_names])){
				return $term_meta[tab_names];
			} else {
				return FMSeries::default_tab_names();
			}
		}
		
		// Otherwise
		return FMSeries::default_tab_names();
	}


	// Save extra taxonomy fields.
	public function save_extra_fields( $term_id ) {
	    if ( isset( $_POST['term_meta'] ) ) {
	        $t_id = $term_id;
	        $term_meta = get_option( "taxonomy_$t_id" );
	        $cat_keys = array_keys( $_POST['term_meta'] );
	        foreach ( $cat_keys as $key ) {
	            if ( isset( $_POST['term_meta'][$key] ) ) {
	                $term_meta[$key] = $_POST['term_meta'][$key];
	            }
	            // magic_quotes screwing up the tinymce content for some reason
	        	if($key = 'my_content'){
	        		$term_meta[$key] = stripslashes($term_meta[$key]);
	        	}
	        	
	        	// if tab name is blank, reset it to default
	        	$default_tab_names = FMSeries::default_tab_names();
	        	if($key = 'tab_names'){
	        		foreach($_POST['term_meta'][$key] as $tab_name => $tab_val){
	        			if(empty($tab_val)){
	        				$term_meta[$key][$tab_name] = $default_tab_names[$tab_name];
	        			}
	        		}
	        	}
	        }
	    }
	    	    
	    if(isset($_POST['sort_person'])){
	    	$term_meta['order'] = $_POST['sort_person'];
	    }
	    
		// Save the option array.
		update_option( "taxonomy_$t_id", $term_meta );
	}  



	//Change Column Names
	function name_columns($gallery_columns) {
		$cols = $gallery_columns;
		unset($cols['description']);
		$cols['post_type'] = 'Type';
		return $cols;
	}


	
	// Custom columns for People
	public function custom_column($something, $column, $term_id){
		$term_meta = get_option( "taxonomy_$term_id" );
		if ("post_type" == $column){
			echo $term_meta['post_type'];
		}  
	}

 

	// Add extra fields to custom taxonomy create form callback functions
	public function display_extra_edit_fields($tag) {
	    // Check for existing taxonomy meta for term ID.
	    $t_id = $tag->term_id;
	    $term_meta = get_option( "taxonomy_$t_id" );
	    $type = esc_attr( $term_meta['post_type'] );
	    $banner = esc_attr( $term_meta['banner']);
	    $splash_img = esc_attr( $term_meta['splash_img']);
	    $options = array('default','numbered');
	    $tab_names = $term_meta['tab_names'];
	    	    
	    // Tab names
	    foreach(FMSeries::default_tab_names() as $tab => $default){
	    	$value = $default;
	    	if(!empty($tab_names[$tab])){
	    		$value = $tab_names[$tab];
	    	}
	    	$tabFieldsHtml .= "<li>\n";
	    	$tabFieldsHtml .= "<input type=\"text\" name=\"term_meta[tab_names][$tab]\" value=\"$value\" style=\"width:240px\" />\n";
	    	$tabFieldsHtml .= "</li>\n";
	    }
	    
	    foreach($options as $o){
	    	$selectedHtml = ($o == $type)? 'SELECTED': '';
	    	$optionsHtml .= "<option $selectedHtml value=\"$o\">$o</option>\n";
	    }
	    echo <<<EOL
		<tr class="form-field">
		    <th scope="row" valign="top"><label for="cat_Image_url">List Style</label></th>
		   	<td>
	        	<select name="term_meta[post_type]" id="term_meta[post_type]">
	        	$optionsHtml
				</select>
	      		<p class="description">Select the list style.</p>
	        </td>
	    </tr>
EOL;

		// Splash Image
	    echo <<<EOL
		<tr class="form-field">
		    <th scope="row" valign="top">
		    	<label for="cat_Image_url">Splash Image</label>
		    </th>
		   	<td>
	        	<input type="text" name="term_meta[splash_img]" value="$splash_img" size="40" />
	      		<p class="description">620px x 280px. Shown on the series index page. Please use a full URL (i.e. http://www.blah.com/images/myimage.jpg)</p>
	        </td>
	    </tr>
EOL;

		// Series Banner
	    echo <<<EOL
		<tr class="form-field">
		    <th scope="row" valign="top"><label for="cat_Image_url">Banner Image</label></th>
		   	<td>
	        	<input type="text" name="term_meta[banner]" value="$banner" size="40" />
	      		<p class="description">620px x 54px. Shown on each post in the series. Please use a full URL (i.e. http://www.blah.com/images/myimage.jpg)</p>
	        </td>
	    </tr>
EOL;

		// Tab Names
	    echo <<<EOL
		<tr class="form-field">
		    <th scope="row" valign="top"><label for="cat_Image_url">Tab Names</label></th>
		    <td>
		    <ul>
		   	$tabFieldsHtml
		   	</ul>
		   	</td>
        </tr>
EOL;

		// Post Order
		wp_enqueue_script('jquery-ui-sortable');
		wp_enqueue_style( 'fmseries-admin');

	    $ordered_post_ids = $term_meta['order'];
	    
		echo <<<EOL
		<script type="text/javascript">
		jQuery(document).ready(function($) {
	    	$(".sortable").sortable({
	    		update: function(event, ui){
	    		}
	    	});
		});
		</script>
		
		<tr class="form-field">
		<th scope="row" valign="top"><label>Posts in this series</label></th>
		<td>
			<p class="description">
			Change the post order by dragging below. Posts will be shown in
			descending order, so the #1 post will be shown last.
			</p>
		
EOL;



		// Get all posts in this series.
		$args = array(
			'post_type' => 'fmp_people',
			'fm_series' => $tag->slug,
			'posts_per_page' => -1,
			'post_status' => array('publish','pending','draft','future','private'),
			//'tax_query' => array(),
		);
		$posts = get_posts($args);
		
		$series_posts = array();
		foreach($posts as $p){
			$series_posts[$p->ID] = $p;
		}
		
		if(count($series_posts)){
			echo "<ul class=\"sortable sort-people\">\n";			
			
			// Display posts which have a specified order
			$i = 1;
			if(is_array($ordered_post_ids)){
				foreach($ordered_post_ids as $id){

					$p = $series_posts[$id];
					if($p){
						echo "<li class=\"order_set\"><span class='index'>$i.</span> {$p->post_title}";
						echo '<input class="sort-person" type="hidden" name="sort_person[]" value="' . $p->ID . '"/>';
						echo "</li>\n";
						unset($series_posts[$id]);
						$i++;
					}
				}
			}
			
			// Display posts which have no specified order last
			foreach($series_posts as $id => $p){
				$pstatus = ($p->post_status == 'publish')? '' : "({$p->post_status})";
				echo "<li class=\"order_not_set\"><span class='index'></span> {$p->post_title} $pstatus";
				echo '<input class="sort-person" type="hidden" name="sort_person[]" value="' . $p->ID . '"/>';

				echo "</li>\n";
				$i++;
			}
			echo "</ul>";
		} else {
			echo '<p class="description">
				There are no posts assigned to this series. You 
				<a href="http://www.filmmakermagazine.com/news/wp-admin/edit.php?post_type=fmp_people">assign posts using Quick Edit here.</a>
				</p>';
		}
		echo "</td>";
		echo "</tr>";		



	}
	


	// Display admin edit form for series
	public function edit_form($taxonomy){
		$id = $taxonomy->term_id;
	    $term_meta = get_option( "taxonomy_$id" );
	   	echo "<p><br/><p/><h3>Content</h3>";
		echo "<p>Add content to display on the series&rsquo; hub page.</p>";
	    FMSeries::build_panel('term_meta[my_content]', $term_meta['my_content']);
	}
		
	
	// Build an admin wysiwyg textbox panel. $name = var name, $value = var value
	function build_panel($name, $value){
		wp_nonce_field( plugin_basename( __FILE__ ), 'fmp_nonce' ); // Use nonce for verification
		$id = str_replace('_','',strtolower($name));
		wp_editor($value, 
			$id, 
			array(
				'textarea_name' => $name, 
				'wpautop'=> false,
			)
		);		
	}	
	
	
	// Returns usefull data for this series (next link, prev link, number in series, etc)
	function get_data($term, $post_id = null){
		$id = $term->term_id;
		//print_r($term);
		$term_meta = get_option( "taxonomy_$id" );
		$order = $term_meta['order'];
		$data['order'] = $order;
		$data['total'] = count($order);
		$data['prev_id'] = null;
		$data['next_id'] = null;
		$data['post_type'] = $term_meta['post_type'];
		$data['banner'] = $term_meta['banner'];
		$data['splash_img'] = $term_meta['splash_img'];
		$data['content'] = $term_meta['my_content'];
		$data['tab_names'] = $term_meta['tab_names'];
		$data['link'] = get_term_link($term);
		
		if($post_id && is_array($order)){
			foreach($order as $i => $e){
				if($post_id == $e){
					if($i > 0){
						$data['next_id'] = $order[$i-1];
					}
					if($i < count($order)){
						$data['prev_id'] = $order[$i+1];
					}
					$data['position'] = $i + 1;
				}
			}
		}
		
		
		return $data;
	}


	// Display banner with series info. For use on a post page, in the Loop. $term is the taxonomy term. $seriesData can be
	// obtained with FMSeries::get_data($term, $post_id).
	function display_fmseries_banner($term, $seriesData, $classes='', $style=''){
		
		$html = '';
		
		if($seriesData['prev_id']){
			$prev_post = get_post($seriesData['prev_id']);
		}
		if($seriesData['next_id']){
			$next_post = get_post($seriesData['next_id']);
		}
		
	   if($term){
	    	$term_link = get_term_link($term);
	    	$term_link_title = str_replace('"','', $term->name);
	    
	   
	    	$html .= '<nav class="fm_paginator"><ul class="fm_paginator-numbers fm_series">';
	    	
if($prev_post){
$html .= '<li class="current fm_prev"><a title="Previous: '.$prev_post->post_title. '" href="' . get_permalink($prev_post) . '" title="1"> <span style="font-size: 0.75em;">&#9664;</span> PREV</a></li>';
}
if($next_post){
$html .= '<li class="current fm_next"><a title="Next: '.$next_post->post_title.'" href="' . get_permalink($next_post) . '"  title="1">NEXT <span style="font-size: 0.75em;">&#9654;</span></a></li>';

}
			$html .= '</ul></nav>'; // nextPrevPosts



			}
			    	return $html;
	}
	
	
	
	// Display all series as list
	function list_all(){
		$args = array(
			'style'=>'list',
			'taxonomy'=>'fm_series'
		);
		return wp_list_categories($args);
	}
	
	
	//Tasks related to accurate rendering of series main list view */
	function create_list_view( $query ) {
		$series = $query->query_vars['fm_series'];
		if ( $series && is_archive()) {
			
			//Populate $series_data with series specific metadata
			global $series_data;
			$series_data = FMSeries::get_data(get_term_by('slug', $query->query_vars['fm_series'], 'fm_series'));
		
				//Setup the Loop			
				$query->set('posts_per_page', 100);
				$query->set('post__in', array_reverse($series_data['order']));
				$query->set('orderby', 'post__in');
			
		}
	}
		




} // End Class FMSeries



?>
