<?php
/**
 * Plugin Name: 丸子管理菜单
 * Plugin URI: https://www.weitimes.com
 * Description: 丸子小程序团队基于 WordPress 创建管理页面菜单
 * Version: 1.0.0
 * Author: 丸子团队
 * Author URI: https://www.imahui.com
 * requires at least: 4.9.8
 * tested up to: 6.1
 **/
 
 
if ( ! class_exists( 'WanziAdminMenu' ) ) {
	
	class WanziAdminMenu {
	    
	    private static $_instance = null;
		
		public function __construct( ) {
			if( is_admin() ) {
				add_action( 'admin_menu', array( $this, 'wanzi_setup_admin_menu' ) );
				add_action( 'admin_init', array( $this, 'wanzi_setup_admin_init' ) );
			}
		}
		
		public static function instance( ) {
            if( is_null( self::$_instance ) ) {
                self::$_instance = new self( );
            }
            return self::$_instance;
        }
		
		public function wanzi_setup_admin_menu( ) {
			
            $admin_menu = apply_filters( 'mp_admin_menu', $admin_menu = array( ) );

            if( !empty( $admin_menu ) ) {
                foreach ( $admin_menu as $menus ) {
                    //do_action( "wanzi_admin_menus", $menus );
                    foreach ( $menus as $key => $menu ) {
                        switch ( $key ) {
                            case 'menu':
                                $this->add_menu_page( $menu );
                                break;
                            case 'submenu':
                                foreach ( $menu as $submenu ) {
                                    $this->add_submenu_page( $submenu );
                                }
                                break;
                        }
                    }
                }
            }

		}
		
		public function wanzi_setup_admin_init( ) {
		    $admin_menu = apply_filters( 'mp_admin_menu', $admin_menu = array( ) );

            if( !empty( $admin_menu ) ) {
                foreach ( $admin_menu as $menus ) {
                    //do_action( "wanzi_admin_menus", $menus );
                    foreach ( $menus as $key => $menu ) {
                        switch ( $key ) {
                            case 'menu':
                                $this->add_register_setting( $menu );
                                break;
                            case 'submenu':
                                foreach ( $menu as $submenu ) {
                                    $this->add_register_setting( $submenu );
                                }
                                break;
                        }
                    }
                }
            }
		}
				 
		public function add_menu_page( $menu ) {
		    add_menu_page(
                $menu['page_title'],
                $menu['menu_title'],
                isset($menu['capability']) ? $menu['capability'] : 'manage_options',
                $menu['option_name'],
                function_exists($menu['function']) ? $menu['function'] : function( ) use ( $menu ) { $this->wanzi_admin_page( $menu ); },
                isset($menu['icon']) ? $menu['icon'] : null,
                isset($menu['position']) ? $menu['position'] : null
            );
		}

        public function add_submenu_page( $menu ) {
            add_submenu_page(
                $menu['option_name'],
                $menu['page_title'],
                $menu['menu_title'],
                isset($menu['capability']) ? $menu['capability'] : 'manage_options',
                $menu['slug'],
                function_exists($menu['function']) ? $menu['function'] : function( ) use ( $menu ) { $this->wanzi_admin_submenu_page( $menu ); },
                isset($menu['position']) ? $menu['position'] : null
            );
		}
		
		public function add_register_setting( $menu ) {
		    if( ! function_exists($menu['function']) && has_filter($menu['function']) ) {
		        $fields = apply_filters( $menu['function'], $options = array( ) );
		        $option = isset( $menu['option_field'] ) ? str_replace("-", "_", $menu['option_field']) : '';
                if( ! $option ) {
                    $option = isset( $menu['slug'] ) ? 'wanzi_'.str_replace("-", "_", $menu['slug']) : 'wanzi_'.str_replace("-", "_", $menu['option_name']);
                }
                if( class_exists('WanziFramework') ) {
                    $wanzi = new WanziFramework( $fields, $option );
                    register_setting( $option."-group", $option, array( $wanzi, 'wanzi_option_framework_sanitize' ) );
                }
		    }
		}

        public function wanzi_admin_page( $menu ) {
            $fields = apply_filters( $menu['function'], $options = array( ) );
            $option = isset( $menu['option_field'] ) ? str_replace("-", "_", $menu['option_field']) : '';
            if( ! $option ) {
                $option = isset( $menu['slug'] ) ? 'wanzi_'.str_replace("-", "_", $menu['slug']) : 'wanzi_'.str_replace("-", "_", $menu['option_name']);
            }
            if( class_exists('WanziFramework') ) {
                $wanzi = new WanziFramework( $fields, $option );
        	    $wanzi->wanzi_option_framework_container( );
            } else {
                printf( '<p>未定义设置选项</p>' );
            }
        }
        
        public function wanzi_admin_submenu_page( $menu ) {
            if( $menu["option_name"] !== $menu["slug"] ) {
                $this->wanzi_admin_page( $menu );
            }
        }
		
	}

}

if( class_exists( 'WanziAdminMenu' ) ) {
    
	function WANZI_MENU( ) {
		return WanziAdminMenu::instance( );
	}
	
	$GLOBALS['WANZI_MENU'] = WANZI_MENU( );
	
} else {
    add_action( 'admin_menu', function ( ) {
    	$admin_menu = apply_filters( 'mp_admin_menu', $admin_menu = array() );
    	if(is_admin() && !empty($admin_menu)) {
    		foreach ( $admin_menu as $menus ) {
    			foreach ( $menus as $key => $menu ) {
    				switch ( $key ) {
    					case 'menu':
    						add_menu_page(
    						    $menu['page_title'],
    						    $menu['menu_title'],
    						    isset($menu['capability']) ? $menu['capability'] : 'manage_options',
    						    $menu['option_name'],
    						    $menu['function'],
    						    $menu['icon'],
    						    $menu['position']
    						);
    						break;
    					case 'submenu':
    						foreach ( $menu as $submenu ) {
    							add_submenu_page(
    							    $submenu['option_name'],
    							    $submenu['page_title'],
    							    $submenu['menu_title'],
    							    isset($submenu['capability']) ? $submenu['capability'] : 'manage_options',
    							    $submenu['slug'],
    							    $submenu['function'],
    							    isset($submenu['position']) ? $submenu['position'] : null
    							);
    						}
    						break;
    				}
    			}
    		}
    	}
    } );
}
