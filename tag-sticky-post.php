<?php
/**
 * Tag Sticky Post
 *
 * Mark a post to be placed at the top of a specified category archive. It's sticky posts specifically for tags.
 *
 * @package   Tag_Sticky_Post
 * @author    Tom McFarlin <tom@tommcfarlin.com>
 * @license   GPL-2.0+
 * @link      http://tommcfarlin.com/tag-sticky-post/
 * @copyright 2013 - 2015 Tom McFarlin
 *
 * @wordpress-plugin
 * Plugin Name: Tag Sticky Post
 * Plugin URI: 	http://tommcfarlin.com/tag-sticky-post/
 * Description: Mark a post to be placed at the top of a specified category archive. It's sticky posts specifically for categories.
 * Version:     2.2.0
 * Author:      Tom McFarlin
 * Author URI:  http://tommcfarlin.com
 * Text Domain: tag-sticky-post
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path: /lang
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
} // end if

require_once( plugin_dir_path( __FILE__ ) . 'class-tag-sticky-post.php' );
Tag_Sticky_Post::get_instance();