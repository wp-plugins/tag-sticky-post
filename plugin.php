<?php
/*
Plugin Name: Tag Sticky Post
Plugin URI: http://tommcfarlin.com/tag-sticky-post/
Description: Mark a post to be placed at the top of a specified tag archive. It's sticky posts specifically for tags.
Version: 1.1.2
Author: Tom McFarlin
Author URI: http://tommcfarlin.com
Author Email: tom@tommcfarlin.com
License:

  Copyright 2012 Tom McFarlin (tom@tommcfarlin.com)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as 
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class Tag_Sticky_Post {

	/*--------------------------------------------*
	 * Constructor
	 *--------------------------------------------*/
	
	/**
	 * Initializes the plugin by setting localization, admin styles, and content filters.
	 */
	function __construct() {

		/* Setup the activation hook specifically for checking for the custom.css file
		 * I'm calling the same function using the activation hook - which is when the user activates the plugin,
		 * and during upgrade plugin event. This ensures that the custom.css file can also be managed
		 * when the plugin is updated.
		 *
		 * TODO: Restore this plugin when I've resolved the transient functionality properly.
		 */
		//register_activation_hook( __FILE__, array( $this, 'activate' ) );
		//add_action( 'pre_set_site_transient_update_plugins', array( $this, 'activate' ) );

		// Tag Meta Box actions
		add_action( 'add_meta_boxes', array( $this, 'add_tag_sticky_post_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_tag_sticky_post_data' ) );
		add_action( 'wp_ajax_is_tag_sticky_post', array( $this, 'is_tag_sticky_post' ) );
				
		// Filters for displaying the sticky tag posts
		add_filter( 'post_class', array( $this, 'set_tag_sticky_class' ) );
		add_filter( 'the_posts', array( $this, 'reorder_tag_posts' ) );
		
		// Stylesheets
		add_action( 'admin_enqueue_scripts', array( $this, 'add_admin_styles_and_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_styles' ) );

	} // end constructor
	
	/*---------------------------------------------*
	 * Action Functions
	 *---------------------------------------------*/
	
	 /**
	  * Checks to see if a custom.css file exists. If not, creates it; otherwise, does nothing. This will
	  * prevent customizations from being overwritten in future upgrades.
	  */
	 function activate() {
		 
		 // The path where the custom.css should be stored.
		 $str_custom_path =  dirname( __FILE__ ) . '/css/custom.css';
		 
		 // If the custom.css file doesn't exist, then we create it
		 if( is_writable( $str_custom_path ) && ! file_exists( $str_custom_path ) ) {
			 file_put_contents( $str_custom_path, '' );
		 } // end if
		 
	 } // end activate
	
	/**
	 * Renders the meta box for allowing the user to select a tag in which to stick a given post.
	 */
	function add_tag_sticky_post_meta_box() {
		
		add_meta_box(
			'post_is_tag_sticky',
			__( 'Tag Sticky', 'tag-sticky-post' ),
			array( $this, 'tag_sticky_post_display' ),
			'post',
			'side',
			'low'
		);
		
	} // end add_tag_sticky_post_meta_box
	
	/**
	 * Renders the select box that allows users to choose the tag into which to stick the 
	 * specified post.
	 *
	 * @param	$post	The post to be marked as sticky for the specified tag.
	 */
	function tag_sticky_post_display( $post ) {
		
		// Set the nonce for security
		wp_nonce_field( plugin_basename( __FILE__ ), 'tag_sticky_post_nonce' );

		// First, read all the categories
		$tags = get_tags();

		// Build the HTML that will display the select box
		$html = '<select id="tag_sticky_post" name="tag_sticky_post">';
			$html .= '<option value="0">' . __( 'Select a tag...', 'tag-sticky-post' ) . '</option>';
			foreach( $tags as $tag ) {
				$html .= '<option value="' . $tag->term_id . '" ' . selected( get_post_meta( $post->ID, 'tag_sticky_post', true ), $tag->term_id, false ) . ( $this->tag_has_sticky_post( $tag->term_id ) ? ' disabled ' : '' ) . '>';
					$html .= $tag->name;
				$html .= '</option>';	
			} // end foreach
		$html .= '</select>';
		
		echo $html;
		
	} // end tag_sticky_post_display
	
	/**
	 * Set the custom post meta for marking a post as sticky.
	 *
	 * @param	$post_id	The ID of the post to which we're saving the post meta
	 */
	function save_tag_sticky_post_data( $post_id ) {
	
		if( isset( $_POST['tag_sticky_post_nonce'] ) && isset( $_POST['post_type'] ) ) {
		
			// Don't save if the user hasn't submitted the changes
			if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			} // end if
			
			// Verify that the input is coming from the proper form
			if( ! wp_verify_nonce( $_POST['tag_sticky_post_nonce'], plugin_basename( __FILE__ ) ) ) {
				return;
			} // end if
			
			// Make sure the user has permissions to post
			if( 'post' == $_POST['post_type']) {
				if( ! current_user_can( 'edit_post', $post_id ) ) {
					return;
				} // end if
			} // end if/else
		
			// Read the ID of the category to which we're going to stick this post
			$tag_id = '';
			if( isset( $_POST['tag_sticky_post'] ) ) {
				$tag_id = esc_attr( $_POST['tag_sticky_post'] );
			} // end if

			// If the value exists, delete it first. I don't want to write extra rows into the table.
			if ( 0 == count( get_post_meta( $post_id, 'tag_sticky_post' ) ) ) {
				delete_post_meta( $post_id, 'tag_sticky_post' );
			} // end if
	
			// Update it for this post.
			update_post_meta( $post_id, 'tag_sticky_post', $tag_id );
	
		} // end if
	
	} // end save_tag_sticky_post_data
	
	/**
	 * Register and enqueue the stylesheets and JavaScript dependencies for styling the sticky post.
	 */
	function add_admin_styles_and_scripts() {
	
		// Only register the stylesheet for the post page
		$screen = get_current_screen();
		if( 'post' == $screen->id ) { 
	
			// admin stylesheet
			wp_register_style( 'tag-sticky-post', plugins_url( '/tag-sticky-post/css/admin.css' ) );
			wp_enqueue_style( 'tag-sticky-post' );

			// post editor javascript
			wp_register_script( 'tag-sticky-post-editor', plugins_url( '/tag-sticky-post/js/editor.min.js' ), array( 'jquery' ) );
			wp_enqueue_script( 'tag-sticky-post-editor' );
		
		// And only register the JavaScript for the post listing page
		} elseif( 'edit-post' == $screen->id ) {
		
			// posts display javascript
			wp_register_script( 'tag-sticky-post', plugins_url( '/tag-sticky-post/js/admin.min.js' ), array( 'jquery' ) );
			wp_enqueue_script( 'tag-sticky-post' );
		
		} // end if
		
	} // end add_admin_styles_and_scripts
	
	/**
	 * Register and enqueue the stylesheets for styling the sticky post.
	 */
	function add_styles() {
	
		// Only render the stylesheet if we're on an archive page
		if( is_archive() ) {
			
			wp_register_style( 'tag-sticky-post', plugins_url( '/tag-sticky-post/css/plugin.css' ) );
			wp_enqueue_style( 'tag-sticky-post' );
			
		} // end if
		
	} // end add_styles
	
	/**
	 * Ajax callback function used to decide if the specified post ID is marked as a category
	 * sticky post.
	 *
	 * TODO: I wanted to do this all server side but couldn't find the proper actions and filters to do it.
	 */
	function is_tag_sticky_post() {
	
		if( isset( $_GET['post_id'] ) ) {
		
			$post_id = trim ( $_GET['post_id'] );
			if( 0 == get_post_meta( $post_id, 'tag_sticky_post', true ) ) {
				die( '0' );
			} else {
				die( _e( ' - Tag Sticky Post', 'tag-sticky-post' ) );
			} // end if/else
		
		} // end if
		
	} // end is_tag_sticky_post
	
	/*---------------------------------------------*
	 * Filter Functions
	 *---------------------------------------------*/
	 
	 /**
	  * Adds a CSS class to make it easy to style the sticky post.
	  * 
	  * @param	$classes	The array of classes being applied to the given post
	  * @return				The updated array of classes for our posts
	  */
	 function set_tag_sticky_class( $classes ) {

		 // Determine which tag archive we're on
		 if( null != ( $tag = get_term_by( 'slug', get_query_var( 'tag' ), 'post_tag' ) ) ) {
			
			 // If we're on an archive and the current category ID matches the category of the given post, add the class name
			 if( is_archive() && 0 == get_query_var( 'paged' ) && $tag->term_id == get_post_meta( get_the_ID(), 'tag_sticky_post', true ) ) {
				 $classes[] = 'tag-sticky';
			 } // end if
			 
		 } // end if
		 
		 return $classes;
		 
	 } // end set_tag_sticky_class
	 
	 /**
	  * Places the sticky post at the top of the list of posts for the tag that is being displayed.
	  *
	  * @param	$posts	The lists of posts to be displayed for the given tag
	  * @return			The updated list of posts with the sticky post set as the first titem
	  */
	 function reorder_tag_posts( $posts ) {

	 	// We only care to do this for the first page of the archives
	 	if( is_archive() &&  0 == get_query_var( 'paged' ) ) {
	 
		 	// Read the current tag to find the sticky post
		 	if( null != ( $tag = get_term_by( 'slug', get_query_var( 'tag' ), 'post_tag' ) ) ) {

			 	// Query for the ID of the post
			 	$sticky_query = new WP_Query(
			 		array(
				 		'fields'			=>	'ids',
				 		'post_type'			=>	'post',
				 		'posts_per_page'	=>	'1',
				 		'tax_query'			=> array(
				 			'terms'				=> 	null,
				 			'include_children'	=>	false
				 		),
				 		'meta_query'		=>	array(
				 			array(
					 			'key'		=>	'tag_sticky_post',
					 			'value'		=>	$tag->term_id,
					 		)
				 		)
			 		)
			 	);
			 	
			 	// If there's a post, then set the post ID
			 	$post_id = ( ! isset ( $sticky_query->posts[0] ) ) ? -1 : $sticky_query->posts[0];
			 	wp_reset_postdata();
	
	
			 	// If the query returns an actual post ID, then let's update the posts
			 	if( -1 < $post_id ) {
			 	
			 		// Store the sticky post in an array
				 	$new_posts = array( get_post( $post_id ) );
			 	
				 	// Look to see if the post exists in the current list of posts.
				 	foreach( $posts as $post_index => $post ) {
				 	
				 		// If so, then remove it so we don't duplicate its display
				 		if( $post_id == $posts[ $post_index ]->ID ) {
					 		unset( $posts[ $post_index ] );
				 		} // end if
					 	
				 	} // end foreach
				 	
				 	// Merge the existing array (with the sticky post first and the original posts second)
				 	$posts = array_merge( $new_posts, $posts );
				 	
			 	} // end if
			 	
		 	} // end if
	 	
	 	} // end if
	 	
	 	return $posts;
	 	
	 } // end reorder_tag_posts
	 
	/*---------------------------------------------*
	 * Helper Functions
	 *---------------------------------------------*/
	
	/**
	 * Determines if the given tag already has a sticky post.
	 * 
	 * @param	$tag_id	The ID of the category to check
	 * @return			Whether or not the tag has a sticky post
	 */
	private function tag_has_sticky_post( $tag_id ) {
	
		$has_sticky_post = false;
		
		$q = new WP_Query( 'meta_key=tag_sticky_post&meta_value=' . $tag_id );	
		$has_sticky_post = $q->have_posts();
		wp_reset_query();
		
		return $has_sticky_post;

	} // end tag_has_sticky_post
	
} // end class

new Tag_Sticky_Post();
?>