<?php
/**
 * @package   Admin Settings
 * Dashicons： https://developer.wordpress.org/resource/dashicons/
 */
if( !defined( 'ABSPATH' ) ) exit;

include( MINI_PROGRAM_REST_API. 'admin/options.php' );
include( MINI_PROGRAM_REST_API. 'admin/pages/about.php' );
include( MINI_PROGRAM_REST_API. 'admin/pages/subscribe.php' );
if( ! defined('IMAHUI_REST_API_PLUGIN') ) {
    include( MINI_PROGRAM_REST_API. 'admin/core/menu.php');
    include( MINI_PROGRAM_REST_API. 'admin/core/meta.php');
    include( MINI_PROGRAM_REST_API. 'admin/core/terms.php' );
    include( MINI_PROGRAM_REST_API. 'admin/core/framework.php' );
    include( MINI_PROGRAM_REST_API. 'admin/core/interface.php' );
    include( MINI_PROGRAM_REST_API. 'admin/core/sanitization.php' );
	add_action( 'init', 'creat_miniprogram_terms_meta_box' );
	add_action( 'admin_enqueue_scripts', function ( ) {
		wp_enqueue_style( 'miniprogram', MINI_PROGRAM_API_URL.'admin/static/style.css', array( ), get_bloginfo('version') );
		wp_enqueue_script( 'script', MINI_PROGRAM_API_URL.'admin/static/script.js', array( 'jquery' ), get_bloginfo('version') );
		wp_enqueue_script( 'advert', MINI_PROGRAM_API_URL.'admin/static/mini.adv.js', array( 'jquery' ), get_bloginfo('version') );
		if( function_exists( 'wp_enqueue_media' ) ) {
			wp_enqueue_media( );
		}
	} );
	add_action('admin_footer', function ( ) {
		echo '<script type="text/html" id="tmpl-mp-del-item">
			<a href="javascript:;" class="button del-item">删除</a> <span class="dashicons dashicons-menu"></span>
		</script>';
		if( ! in_array( 'wp-rest-cache/wp-rest-cache.php', apply_filters( 'active_plugins', get_option('active_plugins') ) ) ) {
		    echo '<script>jQuery(document).ready(function($) {$("input#rest_cache").attr("disabled","disabled");});</script>';
		}
	});
}

add_filter( 'mp_admin_menu', function( $admin_menu ) {
	$submenu = array();
	$submenu[] = ['page_title' => '小程序设置','menu_title' => '基本设置', 'option_name' => 'miniprogram', 'option_field' => 'minapp', 'slug' => 'miniprogram', 'function' => 'miniprogram_setting_options'];
	$submenu[] = ['page_title' => '小程序订阅消息统计','menu_title' => '订阅统计', 'option_name' => 'miniprogram','slug' => 'subscribe', 'function' => 'miniprogram_subscribe_message_count'];
	$submenu[] = ['page_title' => '小程序历史推送任务','menu_title' => '任务列表', 'option_name' => 'miniprogram','slug' => 'task', 'function' => 'miniprogram_subscribe_message_task_table'];
	$submenu[] = ['page_title' => 'Mini Program API 使用指南','menu_title' => '使用指南', 'option_name' => 'miniprogram','slug' => 'guide', 'function' => 'miniprogram_api_guide'];
	$admin_menu[] = [
		'menu' => [
			'page_title' => '小程序设置','menu_title' => '小程序', 'option_name' => 'miniprogram', 'option_field' => 'minapp', 'function' => 'miniprogram_setting_options', 'icon' => 'dashicons-editor-code', 'position' => 2
		],
		'submenu'	=> $submenu
	];
	
	return $admin_menu;
} );

add_action('wp_default_styles', function( $styles ) {
	$default_dirs = [
		'/wp-includes/js/thickbox/', 
		'/wp-includes/js/mediaelement/', 
		'/wp-includes/js/imgareaselect/'
	];
	$styles->default_dirs = array_merge($styles->default_dirs, $default_dirs);
});