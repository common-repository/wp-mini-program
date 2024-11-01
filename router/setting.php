<?php

if ( !defined( 'ABSPATH' ) ) exit;

class WP_REST_Setting_Router extends WP_REST_Controller {

	public function __construct( ) {
		$this->namespace     = 'mp/v1';
        $this->resource_name = 'setting';
	}

	public function register_routes() {
		
		register_rest_route( $this->namespace, '/'.$this->resource_name, array(
			array(
				'methods'             	=> WP_REST_Server::READABLE,
				'callback'            	=> array( $this, 'get_wp_setting_info' ),
				'permission_callback' 	=> array( $this, 'wp_setting_permissions_check' ),
				'args'                	=> array(
					'context' => $this->get_context_param( array( 'default' => 'view' ) )
				)
			)
		));

	}

	public function wp_setting_permissions_check( $request ) {
		return true;
	}

	public function get_wp_setting_info( $request ) {
	    $data = array( 
			'name'          => get_bloginfo('name'), 
			'description'   => get_bloginfo('description'),
			'version'       => get_bloginfo('version'),
			'cover'	        => wp_miniprogram_option('thumbnail')
		);
		if( wp_miniprogram_option('appname') ) {
		    $data['name'] = wp_miniprogram_option('appname');
		}
		if( wp_miniprogram_option('appdesc') ) {
		    $data['description'] = wp_miniprogram_option('appdesc');
		}
		if( wp_miniprogram_option('version') ) {
		    $data['version'] = wp_miniprogram_option('version');
		}
		if( wp_miniprogram_option('appcover') ) {
		    $data['cover'] = wp_miniprogram_option('appcover');
		}
		$data = apply_filters( "mp_bloginfo_hooks", $data );
		$response = rest_ensure_response( $data );
		return $response;
	}
	
}