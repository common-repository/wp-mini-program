<?php

if( !defined( 'ABSPATH' ) ) exit;

class WP_REST_Auth_Router extends WP_REST_Controller {

	public function __construct( ) {
		$this->namespace     = 'mp/v1';
	}

	public function register_routes() {

		register_rest_route( $this->namespace, '/tencent/login', array(
			array(
				'methods'             	=> WP_REST_Server::CREATABLE,
				'callback'            	=> array( $this, 'wp_qq_user_auth_login' ),
				'permission_callback' 	=> array( $this, 'wp_login_permissions_check' ),
				'args'                	=> $this->wp_user_login_collection_params()
			)
		) );

		register_rest_route( $this->namespace, '/baidu/login', array(
			array(
				'methods'             	=> WP_REST_Server::CREATABLE,
				'callback'            	=> array( $this, 'wp_baidu_user_auth_login' ),
				'permission_callback' 	=> array( $this, 'wp_login_permissions_check' ),
				'args'                	=> $this->wp_user_login_collection_params()
			)
		) );

		register_rest_route( $this->namespace, '/toutiao/login', array(
			array(
				'methods'             	=> WP_REST_Server::CREATABLE,
				'callback'            	=> array( $this, 'wp_toutiao_user_auth_login' ),
				'permission_callback' 	=> array( $this, 'wp_login_permissions_check' ),
				'args'                	=> $this->wp_user_login_collection_params()
			)
		) );

		register_rest_route( $this->namespace, '/site/code', array(
			array(
				'methods'             	=> WP_REST_Server::CREATABLE,
				'callback'            	=> array( $this, 'wp_user_login_code' ),
				'permission_callback' 	=> array( $this, 'wp_login_permissions_check' ),
				'args'                	=> array(
					'context' => $this->get_context_param( array( 'default' => 'view' ) )
				)
			)
		) );

		register_rest_route( $this->namespace, '/site/signup', array(
			array(
				'methods'             	=> WP_REST_Server::CREATABLE,
				'callback'            	=> array( $this, 'wp_user_register_account' ),
				'permission_callback' 	=> array( $this, 'wp_login_permissions_check' ),
				'args'                	=> array(
					'context' => $this->get_context_param( array( 'default' => 'view' ) )
				)
			)
		) );

		register_rest_route( $this->namespace, '/site/login', array(
			array(
				'methods'             	=> WP_REST_Server::CREATABLE,
				'callback'            	=> array( $this, 'wp_user_login_by_account' ),
				'permission_callback' 	=> array( $this, 'wp_login_permissions_check' ),
				'args'                	=> array(
					'context' => $this->get_context_param( array( 'default' => 'view' ) )
				)
			)
		) );
		
		register_rest_route( $this->namespace, '/site/lostpass', array(
			array(
				'methods'             	=> WP_REST_Server::CREATABLE,
				'callback'            	=> array( $this, 'wp_user_lostpass_by_account' ),
				'permission_callback' 	=> array( $this, 'wp_login_permissions_check' ),
				'args'                	=> array(
					'context' => $this->get_context_param( array( 'default' => 'view' ) )
				)
			)
		) );

	}

	public function wp_login_permissions_check( $request ) {

		return true;
		
	}

	public function wp_user_login_collection_params() {
		$params = array();
		$params['iv'] = array(
			'required' => true,
			'default'	=> '',
			'description'	=> "授权登录，用户基本信息.",
			'type'	=>	 "string"
		);
		$params['code'] = array(
			'required' => true,
			'default'	=> '',
			'description'	=> "登录凭证（有效期五分钟）",
			'type'	=>	 "string"
		);
		$params['encryptedData'] = array(
			'required' => true,
			'default'	=> '',
			'description'	=> "授权登录，用户基本信息",
			'type'	=>	 "string"
		);
		return $params;
	}

	public function wp_qq_user_auth_login( $request ) {

		date_default_timezone_set( datetime_timezone() );

		$code = $request->get_param('code');
		$iv = $request->get_param('iv');
		$encryptedData = $request->get_param('encryptedData');
		
		if( empty($code) ) {
			return new WP_Error( 'error', '用户登录 code 参数错误', array( 'status' => 403 ) );
		}

		if( empty($iv) ) {
			return new WP_Error( 'error', '缺少加密算法的初始向量', array( 'status' => 403 ) );
		}

		if( empty($encryptedData) ) {
			return new WP_Error( 'error', '缺少用户信息的加密数据', array( 'status' => 403 ) );
		}
		
		$appid 			= wp_miniprogram_option('qq_appid');
		$appsecret 		= wp_miniprogram_option('qq_secret');
		$role 			= wp_miniprogram_option('use_role');

		$args = array(
			'appid' => $appid,
			'secret' => $appsecret,
			'js_code' => $code,
			'grant_type' => 'authorization_code'
		);
		
		$api = 'https://api.q.qq.com/sns/jscode2session';
		$url = add_query_arg( $args, $api );
		$remote = wp_remote_get( $url );
		if( is_wp_error($remote) || !isset($remote['body']) ) {
			return new WP_Error( 'error', '获取授权 OpenID 和 Session 错误', array( 'status' => 403, 'message' => $remote ) );
		}

		$body = stripslashes( $remote['body'] );
		$session = json_decode( $body, true );
		if( $session['errcode'] != 0 ) {
			return new WP_Error( 'error', '获取用户信息错误,请检查设置', array( 'status' => 403, 'message' => $session ) );
		}
		
		$session_key = $session['session_key'];
		$openId = $session['openid'];
		$unionId = $session['unionid'];

		$user_id = 0;
		$token = MP_Auth::generate_session( );
		$expire = isset($token['expire_in']) ? $token['expire_in'] : date( 'Y-m-d H:i:s', time() + 86400 );
		$token_id = isset($token['session_key']) ? $token['session_key'] : $session_key;
		$user_pass = wp_generate_password( 16, false );
		
		if( $unionId ) {
			$users = get_userdata_by_meta( 'unionid', $unionId );
			if( isset($users->user_id) ) {
				$user_id = $users->user_id;
				update_user_meta( $user_id, 'openid', $openId );
				update_user_meta( $user_id, 'unionid', $unionId );
				update_user_meta( $user_id, 'expire_in', $expire );
				update_user_meta( $user_id, 'session_key', $token_id );
				update_user_meta( $user_id, 'platform', 'tencent');
			} else if( username_exists($openId) ) {
				$user = get_user_by( 'login', $openId );
				$user_id = $user->ID;			
				update_user_meta( $user_id, 'openid', $openId );
				update_user_meta( $user_id, 'unionid', $unionId );
				update_user_meta( $user_id, 'expire_in', $expire );
				update_user_meta( $user_id, 'session_key', $token_id );
				add_user_meta( $user_id, 'platform', 'tencent');
			} else {
				$users = get_userdata_by_meta( 'openid', $openId );
				if( isset( $users->user_id ) ) {
					$user_id = $users->user_id;
					update_user_meta( $user_id, 'openid', $openId );
					update_user_meta( $user_id, 'unionid', $unionId );
					update_user_meta( $user_id, 'expire_in', $expire );
					update_user_meta( $user_id, 'session_key', $token_id );
					update_user_meta( $user_id, 'platform', 'tencent');
				} else {
					$auth = MP_Auth::decryptData( $appid, $session_key, urldecode($encryptedData), urldecode($iv), $data );
					if( $auth != 0 ) {
						return new WP_Error( 'error', '用户信息解密错误', array( 'status' => 403, 'errmsg' => $auth ) );
					}
					$user_data = json_decode( $data, true );
					$userdata = array(
						'user_login' 			=> $openId,
						'nickname' 				=> $user_data['nickName'],
						'first_name'			=> $user_data['nickName'],
						'user_nicename' 		=> $openId,
						'display_name' 			=> $user_data['nickName'],
						'user_email' 			=> date('Ymdhms').'@qq.com',
						'role' 					=> $role,
						'user_pass' 			=> $user_pass,
						'gender'				=> $user_data['gender'],
						'openid'				=> $openId,
						'city'					=> $user_data['city'],
						'avatar' 				=> $user_data['avatarUrl'],
						'province'				=> $user_data['province'],
						'country'				=> $user_data['country'],
						'language'				=> $user_data['language'],
						'expire_in'				=> $expire
					);
					$user_id = wp_insert_user( $userdata );			
					if( is_wp_error( $user_id ) ) {
						return new WP_Error( 'error', '创建用户失败', array( 'status' => 400, 'error' => $user_id ) );				
					}
					add_user_meta( $user_id, 'unionid', $unionId );
					add_user_meta( $user_id, 'session_key', $token_id );
					add_user_meta( $user_id, 'platform', 'tencent');
				}
			}
		} else if( username_exists($openId) ) {
			$user = get_user_by( 'login', $openId );
			$user_id = $user->ID;
			update_user_meta( $user_id, 'openid', $openId );
			update_user_meta( $user_id, 'unionid', $unionId );
			update_user_meta( $user_id, 'expire_in', $expire );
			update_user_meta( $user_id, 'session_key', $token_id );
			update_user_meta( $user_id, 'platform', 'tencent');
		} else {
			$users = get_userdata_by_meta( 'openid', $openId );
			if( isset( $users->user_id ) ) {
				$user_id = $users->user_id;
				update_user_meta( $user_id, 'openid', $openId );
				update_user_meta( $user_id, 'unionid', $unionId );
				update_user_meta( $user_id, 'expire_in', $expire );
				update_user_meta( $user_id, 'session_key', $token_id );
				update_user_meta( $user_id, 'platform', 'tencent');
			} else {
				$auth = MP_Auth::decryptData( $appid, $session_key, urldecode($encryptedData), urldecode($iv), $data );
				if( $auth != 0 ) {
					return new WP_Error( 'error', '用户信息解密错误', array( 'status' => 403, 'errmsg' => $auth ) );
				}
				$user_data = json_decode( $data, true );
				$userdata = array(
					'user_login' 			=> $openId,
					'nickname' 				=> $user_data['nickName'],
					'first_name'			=> $user_data['nickName'],
					'user_nicename' 		=> $openId,
					'display_name' 			=> $user_data['nickName'],
					'user_email' 			=> date('Ymdhms').'@qq.com',
					'role' 					=> $role,
					'user_pass' 			=> $user_pass,
					'gender'				=> $user_data['gender'],
					'openid'				=> $openId,
					'city'					=> $user_data['city'],
					'avatar' 				=> $user_data['avatarUrl'],
					'province'				=> $user_data['province'],
					'country'				=> $user_data['country'],
					'language'				=> $user_data['language'],
					'expire_in'				=> $expire
				);
				$user_id = wp_insert_user( $userdata );			
				if( is_wp_error( $user_id ) ) {
					return new WP_Error( 'error', '创建用户失败', array( 'status' => 400, 'error' => $user_id ) );				
				}
				add_user_meta( $user_id, 'unionid', $unionId );
				add_user_meta( $user_id, 'session_key', $token_id );
				add_user_meta( $user_id, 'platform', 'tencent');
			}
		}
		
		$current_user = get_user_by( 'ID', $user_id );
		if( is_multisite() ) {
			$blog_id = get_current_blog_id();
			$roles = ( array )$current_user->roles[$blog_id];
		} else {
			$roles = ( array )$current_user->roles;
		}

		wp_set_current_user( $user_id, $current_user->user_login );
		wp_set_auth_cookie( $user_id, true );
		
		$user = array(
			"user"	=> array(
				"userId"		=> $user_id,
				"nickName"		=> $current_user->nickname,
				"openId"		=> $openId,
				"avatarUrl" 	=> $current_user->avatar,
				"gender"		=> $current_user->gender,
				"city"			=> $current_user->city,
				"province"		=> $current_user->province,
				"country"		=> $current_user->country,
				"language"		=> $current_user->language,
				"role"			=> $roles[0],
				'platform'		=> $current_user->platform,
				"description"	=> $current_user->description
			),
			"access_token" => base64_encode( $token_id ),
			"expired_in" => strtotime( $expire ) * 1000
			
		);
		$user = apply_filters( "wanzi_rest_user_login", $user, $request );
		$response = rest_ensure_response( $user );
		return $response;

	}

	public function wp_baidu_user_auth_login( $request ) {

		date_default_timezone_set( datetime_timezone() );
		
		$iv = $request->get_param('iv');
		$code = $request->get_param('code');
		$encryptedData = $request->get_param('encryptedData');
		
		if( empty($code) ) {
			return new WP_Error( 'error', '用户登录 code 参数错误', array( 'status' => 403 ) );
		}

		if( empty($iv) ) {
			return new WP_Error( 'error', '缺少加密算法的初始向量', array( 'status' => 403 ) );
		}

		if( empty($encryptedData) ) {
			return new WP_Error( 'error', '缺少用户信息的加密数据', array( 'status' => 403 ) );
		}

		$appkey 		= wp_miniprogram_option('bd_appkey');
		$appsecret 		= wp_miniprogram_option('bd_secret');
		$role 			= wp_miniprogram_option('use_role');

		$args = array(
			'client_id' => $appkey,
			'sk' => $appsecret,
			'code' => $code
		);

		$api = 'https://spapi.baidu.com/oauth/jscode2sessionkey';
		$url = add_query_arg( $args, $api );
		$remote = wp_remote_request( $url, array( 'method' => 'POST' ) );
		if( is_wp_error($remote) ) {
			return new WP_Error( 'error', '获取授权 OpenID 和 Session 错误', array( 'status' => 403, 'message' => $remote ) );
		}

		$body = wp_remote_retrieve_body( $remote );
		$session = json_decode( $body, true );
		$session_key = $session['session_key'];
		$openId = $session['openid'];
		
		$user_id = 0;
		$token = MP_Auth::generate_session( );
		$expire = isset($token['expire_in']) ? $token['expire_in'] : date( 'Y-m-d H:i:s', time() + 86400 );
		$token_id = isset($token['session_key']) ? $token['session_key'] : $session_key;
		$user_pass = wp_generate_password( 16, false );
		
		if( username_exists($openId) ) {
			$user = get_user_by( 'login', $openId );
			$user_id = $user->ID;			
			update_user_meta( $user_id, 'openid', $openId );
			update_user_meta( $user_id, 'expire_in', $expire );
			update_user_meta( $user_id, 'session_key', $token_id );
			add_user_meta( $user_id, 'platform', 'baidu');
		} else {
			$users = get_userdata_by_meta( 'openid', $openId );
			if( isset( $users->user_id ) ) {
				$user_id = $users->user_id;
				update_user_meta( $user_id, 'openid', $openId );
				update_user_meta( $user_id, 'expire_in', $expire );
				update_user_meta( $user_id, 'session_key', $token_id );
				update_user_meta( $user_id, 'platform', 'baidu');
			} else {
				$auth = MP_Auth::decrypt(urldecode($encryptedData), urldecode($iv), $appkey, $session_key);
				if( ! $auth ) {
					return new WP_Error( 'error', '用户信息解密错误', array( 'status' => 403, 'errmsg' => $auth ) );
				}
				$user_data = json_decode( $auth, true );
				$userdata = array(
					'user_login' 			=> $openId,
					'nickname' 				=> $user_data['nickname'],
					'first_name'			=> $user_data['nickname'],
					'user_nicename' 		=> $openId,
					'display_name' 			=> $user_data['nickname'],
					'user_email' 			=> date('Ymdhms').'@baidu.com',
					'role' 					=> $role,
					'user_pass' 			=> $user_pass,
					'gender'				=> $user_data['sex'],
					'openid'				=> $openId,
					'avatar' 				=> $user_data['headimgurl'],
					'expire_in'				=> $expire
				);
				$user_id = wp_insert_user( $userdata );			
				if( is_wp_error( $user_id ) ) {
					return new WP_Error( 'error', '创建用户失败', array( 'status' => 400, 'error' => $user_id ) );				
				}
				add_user_meta( $user_id, 'session_key', $token_id );
				add_user_meta( $user_id, 'platform', 'baidu');
			}
		}

		$current_user = get_user_by( 'ID', $user_id );
		if( is_multisite() ) {
			$blog_id = get_current_blog_id();
			$roles = ( array )$current_user->roles[$blog_id];
		} else {
			$roles = ( array )$current_user->roles;
		}

		wp_set_current_user( $user_id, $current_user->user_login );
		wp_set_auth_cookie( $user_id, true );
		
		$user = array(
			"user"	=> array(
				"userId"		=> $user_id,
				"nickName"		=> $current_user->nickname,
				"openId"		=> $openId,
				"avatarUrl" 	=> $current_user->avatar,
				"gender"		=> $current_user->gender,
				"role"			=> $roles[0],
				'platform'		=> $current_user->platform,
				"description"	=> $current_user->description
			),
			"access_token" => base64_encode( $token_id ),
			"expired_in" => strtotime( $expire ) * 1000
		);
		
		$user = apply_filters( "wanzi_rest_user_login", $user, $request );
		
		$response = rest_ensure_response( $user );
		return $response;

	}

	public function wp_toutiao_user_auth_login( $request ) {

		date_default_timezone_set( datetime_timezone() );
		
		$iv = $request->get_param('iv');
		$code = $request->get_param('code');
		$encryptedData = $request->get_param('encryptedData');
		
		if( empty($code) ) {
			return new WP_Error( 'error', '用户登录 code 参数错误', array( 'status' => 403 ) );
		}

		if( empty($iv) ) {
			return new WP_Error( 'error', '缺少加密算法的初始向量', array( 'status' => 403 ) );
		}

		if( empty($encryptedData) ) {
			return new WP_Error( 'error', '缺少用户信息的加密数据', array( 'status' => 403 ) );
		}

		$appid 			= wp_miniprogram_option('tt_appid');
		$secret 		= wp_miniprogram_option('tt_secret');
		$role 			= wp_miniprogram_option('use_role');
		
		$args = array(
			'appid' => $appid,
			'secret' => $secret,
			'code' => $code
		);

		$api = 'https://developer.toutiao.com/api/apps/jscode2session';
		$url = add_query_arg( $args, $api );
		$remote = wp_remote_get( $url );
		if( is_wp_error($remote) ) {
			return new WP_Error( 'error', '获取授权 OpenID 和 Session 错误', array( 'status' => 403, 'message' => $remote ) );
		}

		$body = wp_remote_retrieve_body( $remote );
		$session = json_decode( $body, true );
		$session_key = $session['session_key'];
		$openId = $session['openid'];
		
		$user_id = 0;
		$token = MP_Auth::generate_session( );
		$expire = isset($token['expire_in']) ? $token['expire_in'] : date( 'Y-m-d H:i:s', time() + 86400 );
		$token_id = isset($token['session_key']) ? $token['session_key'] : $session_key;
		$user_pass = wp_generate_password( 16, false );
		
		if( username_exists($openId) ) {
			$user = get_user_by( 'login', $openId );
			$user_id = $user->ID;			
			update_user_meta( $user_id, 'openid', $openId );
			update_user_meta( $user_id, 'expire_in', $expire );
			update_user_meta( $user_id, 'session_key', $token_id );
			add_user_meta( $user_id, 'platform', 'toutiao');
		} else {
			$users = get_userdata_by_meta( 'openid', $openId );
			if( isset( $users->user_id ) ) {
				$user_id = $users->user_id;
				update_user_meta( $user_id, 'openid', $openId );
				update_user_meta( $user_id, 'expire_in', $expire );
				update_user_meta( $user_id, 'session_key', $token_id );
				update_user_meta( $user_id, 'platform', 'toutiao');
			} else {
				$auth = MP_Auth::decryptData($appid, $session_key, urldecode($encryptedData), urldecode($iv), $data);
				if( $auth != 0 ) {
					return new WP_Error( 'error', '用户信息解密错误', array( 'status' => 403, 'errmsg' => $auth ) );
				}
				$user_data = json_decode( $data, true );
				$userdata = array(
					'user_login' 			=> $openId,
					'nickname' 				=> $user_data['nickName'],
					'first_name'			=> $user_data['nickName'],
					'user_nicename' 		=> $openId,
					'display_name' 			=> $user_data['nickName'],
					'user_email' 			=> date('Ymdhms').'@toutiao.com',
					'role' 					=> $role,
					'user_pass' 			=> $user_pass,
					'gender'				=> $user_data['gender'],
					'openid'				=> $openId,
					'city'					=> $user_data['city'],
					'avatar' 				=> $user_data['avatarUrl'],
					'province'				=> $user_data['province'],
					'country'				=> $user_data['country'],
					'language'				=> $user_data['language'],
					'expire_in'				=> $expire
				);
				$user_id = wp_insert_user( $userdata );
				if( is_wp_error( $user_id ) ) {
					return new WP_Error( 'error', '创建用户失败', array( 'status' => 400, 'error' => $user_id ) );				
				}
				add_user_meta( $user_id, 'session_key', $token_id );
				add_user_meta( $user_id, 'platform', 'toutiao');
			}
		}

		$current_user = get_user_by( 'ID', $user_id );
		if( is_multisite() ) {
			$blog_id = get_current_blog_id();
			$roles = ( array )$current_user->roles[$blog_id];
		} else {
			$roles = ( array )$current_user->roles;
		}

		wp_set_current_user( $user_id, $current_user->user_login );
		wp_set_auth_cookie( $user_id, true );
		
		$user = array(
			"user"	=> array(
				"userId"		=> $user_id,
				"nickName"		=> $current_user->nickname,
				"openId"		=> $openId,
				"avatarUrl" 	=> $current_user->avatar,
				"gender"		=> $current_user->gender,
				"city"			=> $current_user->city,
				"province"		=> $current_user->province,
				"country"		=> $current_user->country,
				"language"		=> $current_user->language,
				"role"			=> $roles[0],
				'platform'		=> $current_user->platform,
				"description"	=> $current_user->description
			),
			"access_token" => base64_encode( $token_id ),
			"expired_in" => strtotime( $expire ) * 1000
		);
		
		$user = apply_filters( "wanzi_rest_user_login", $user, $request );
		
		$response = rest_ensure_response( $user );
		return $response;

	}

	public function wp_user_login_code( $request ) {

		date_default_timezone_set( datetime_timezone() );

		$email = $request->get_param('email');
		if ( empty($email) || !filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			return new WP_Error( 'error', '邮箱地址无效', array( 'status' => 403 ) );
		}

		$email_name = strstr( $email, '@', TRUE );
		$validation = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
		set_transient( 'mp_email_'.$email_name.'_validation', $validation, 600 );

		$blogname = get_bloginfo( 'name' );
		$title = "欢迎加入[".$blogname."],请查看验证码";
		$content = $email_name." 您好, 欢迎加入[".$blogname."], 验证码：".$validation." [ 10分钟有效！]";
		$send = wp_mail( $email, $title, $content );

		if( $send && $email_name ) {
			$result["status"] = 200;
			$result["code"] = "success";
			$result["message"] = "验证码发送成功!";
		} else {
			$result["status"] = 400;
			$result["code"] = "fail";
			$result["message"] = "验证码发送失败!";
		}

		$response = rest_ensure_response( $result );
    	return $response;

	}

	public function wp_user_register_account( $request ) {

		date_default_timezone_set( datetime_timezone() );

		$login = $request->get_param('login');
		$password = $request->get_param('password');
		$email = $request->get_param('email');
		$code = $request->get_param('code');

		if( empty($login) || empty($password) ||empty($email) || empty($code) ) {
			return new WP_Error( 'error', '请填写每项注册信息', array( 'status' => 403 ) );
		}

		if( filter_var( $login, FILTER_VALIDATE_EMAIL ) ) {
			return new WP_Error( 'error', '登录名不能是邮箱地址', array( 'status' => 403 ) );
		}

		if( ! ctype_alnum( $login ) ) {
			return new WP_Error( 'error', '登录名只能是字母或数字组合', array( 'status' => 403 ) );
		}

		$user_login = get_user_by( 'login', $login );
		if( $user_login ) {
			return new WP_Error( 'error', '登录名已经注册', array( 'status' => 403 ) );
		}

		if( !filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			return new WP_Error( 'error', '邮箱地址无效', array( 'status' => 403 ) );
		}
		$user_email = get_user_by( 'email', $email );
		if( $user_email ) {
			return new WP_Error( 'error', '邮箱地址已经注册', array( 'status' => 403 ) );
		}
		if( strlen($password) < 8 ) {
			return new WP_Error( 'error', '密码长度不能少于 8 位', array( 'status' => 403 ) );
		}
		if( is_numeric($password) ) {
			return new WP_Error( 'error', '密码不能纯数字', array( 'status' => 403 ) );
		}

		$email_name = strstr( $email, '@', TRUE );
		$validation = get_transient( 'mp_email_'.$email_name.'_validation' );
		$valida_time = get_transient( 'mp_email_'.$email_name.'_valida_time' );
		$remain_times = $valida_time ? $valida_time : 5;
		if( $code != $validation ) {
			$remain_times = $remain_times - 1;
			set_transient( 'mp_email_'.$email_name.'_valida_time', $remain_times, 600 );
			return new WP_Error( 'error', '邮箱验证码错误', array( 'status' => 403 ) );
		}
		if( !$remain_times ) {
			return new WP_Error( 'error', '验证码错误次数太多, 10分钟后再试', array( 'status' => 403 ) );
		}

		$session = MP_Auth::generate_session( );
		$role 	 = wp_miniprogram_option('use_role');

		$userdata = array(
			'user_login' 			=> $login,
			'nickname' 				=> $email_name,
			'first_name'			=> $email_name,
			'user_nicename' 		=> $$email_name,
			'display_name' 			=> $email_name,
			'user_email' 			=> $email,
			'role' 					=> $role,
			'user_pass' 			=> $password,
			'expire_in'				=> $session['expire_in']
		);
		$user_id = wp_insert_user( $userdata );
		if( is_wp_error( $user_id ) ) {
			return new WP_Error( 'error', '创建用户失败', array( 'status' => 400 ) );				
		}

		add_user_meta( $user_id, 'session_key', $session['session_key'] );
		update_user_meta( $user_id, 'platform', 'website');
		wp_set_current_user( $user_id, $login );
		wp_set_auth_cookie( $user_id, true );

		$current_user = get_user_by( 'ID', $user_id );
		if( is_multisite() ) {
			$blog_id = get_current_blog_id();
			$roles = ( array )$current_user->roles[$blog_id];
		} else {
			$roles = ( array )$current_user->roles;
		}
		$avatar = get_user_meta($user_id, 'avatar', true);
		$openid = get_user_meta($user_id, 'openid', true);
		$user = array(
			"user"	=> array(
				"userId"		  => $user_id,
				"nickName"		  => $email_name,
				"openId"		  => $openid ? $openid : $current_user->user_login,
				"avatarUrl" 	  => $avatar ? $avatar : get_avatar_url($user_id),
				"description"	  => $current_user->description
			),
			"access_token" => base64_encode( $session['session_key'] ),
			"expired_in" => strtotime( $session['expire_in'] ) * 1000
		);

		$response = rest_ensure_response( $user );
    	return $response;

	}

	public function wp_user_login_by_account( $request ) {

		date_default_timezone_set( datetime_timezone() );

		$login = $request->get_param('login');
		$password = $request->get_param('password');

		if ( empty($login) || empty($password) ) {
			return new WP_Error( 'error', '登录信息错误, 不能为空', array( 'status' => 403 ) );
		}
		if( filter_var( $login, FILTER_VALIDATE_EMAIL ) ) {
			$user = get_user_by( 'email', $login );
		} else {
			$user = get_user_by( 'login', $login );
		}
		if( !$user ) {
			return new WP_Error( 'error', '登录账户错误, 没有找到用户', array( 'status' => 403 ) );
		}

		$check = wp_check_password($password, $user->user_pass, $user->ID );
		if( !$check ) {
			return new WP_Error( 'error', '登录错误，请检查密码', array( 'status' => 403 ) );
		}

		wp_set_current_user( $user->ID, $user->user_login );
		wp_set_auth_cookie( $user->ID, true );

		if( is_multisite() ) {
			$blog_id = get_current_blog_id();
			$roles = ( array )$user->roles[$blog_id];
		} else {
			$roles = ( array )$user->roles;
		}

		$session = MP_Auth::generate_session( );
		$avatar = get_user_meta($user->ID, 'avatar', true);
		$openid = get_user_meta($user->ID, 'openid', true);
		update_user_meta( $user->ID, 'session_key', $session['session_key'] );
		update_user_meta( $user->ID, 'expired_in', $session['expire_in'] );

		$user = array(
			"user"	=> array(
				"userId"		=> $user->ID,
				"nickName"		=> $user->nickname,
				"openId"		=> $openid ? $openid : $user->user_login,
				"avatarUrl" 	=> $avatar ? $avatar : get_avatar_url( $user_id ),
				"description"	=> $user->description
			),
			"access_token" => base64_encode( $session['session_key'] ),
			"expired_in" => strtotime( $session['expire_in'] ) * 1000
		);

		$response = rest_ensure_response( $user );
		return $response;

	}
	
	public function wp_user_lostpass_by_account( $request ) {

	    global $wpdb, $wp_hasher;
	    	
	    $login = $request->get_param('login');
	    if( strpos( $login, '@' ) ) {
			$user_data = get_user_by( 'email', trim( $login ) );
			if( empty( $user_data ) ) {
				return new WP_Error( 'error', '请检查邮箱是否正确', array( 'status' => 403 ) );
			}
    	} else {
        	$user_data = get_user_by( 'login', trim( $login ) );
        	if( empty( $user_data ) ) {
				return new WP_Error( 'error', '请检查账号是否正确', array( 'status' => 403 ) );
			}
    	}

    	$user_login = $user_data->user_login;
    	$user_email = $user_data->user_email;

    	do_action('retrieve_password', $user_login);

    	$allow = apply_filters('allow_password_reset', true, $user_data->ID);

    	if( ! $allow ) {
			return new WP_Error( 'error', '站点不允许修改密码', array( 'status' => 403 ) );
		} else if( is_wp_error($allow) ) {
			return new WP_Error( 'error', '重置邮箱错误，稍后重试', array( 'status' => 403 ) );
		}

    	$key = wp_generate_password( 20, false );

    	do_action( 'retrieve_password_key', $user_login, $key );

    	if( empty( $wp_hasher ) ) {

        	require_once ABSPATH . 'wp-includes/class-phpass.php';

        	$wp_hasher = new PasswordHash( 8, true );
		}

    	$hashed = time() . ':' . $wp_hasher->HashPassword( $key );
		$wpdb->update( $wpdb->users, array( 'user_activation_key' => $hashed ), array( 'user_login' => $user_login ) ); 

    	$message = __('有人为以下账户请求了密码重置:') . "\r\n\r\n";
		$message .= network_home_url( '/' ) . "\r\n\r\n";
		$message .= sprintf(__('Username: %s'), $user_login) . "\r\n\r\n";
		$message .= __('若这不是您本人要求的，请忽略本邮件，一切如常。') . "\r\n\r\n";
		$message .= __('要重置您的密码，请打开以下链接:') . "\r\n\r\n";
		$message .= network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_login), 'login');

    	if( is_multisite() ) {
			$blogname = $GLOBALS['current_site']->site_name;
		} else {
			$blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
		}

		$title = sprintf( __('[%s] Password Reset'), $blogname );
		$title = apply_filters('retrieve_password_title', $title);
		$message = apply_filters('retrieve_password_message', $message, $key);
		if( $message && !wp_mail($user_email, $title, $message) ) {
			return new WP_Error( 'error', '重置邮箱错误，稍后重试', array( 'status' => 403 ) );
		}
		 
		$result["status"] = 200;
		$result["code"] = "success";
		$result["message"] = "重置密码链接已发送邮箱!";

		$response = rest_ensure_response( $result );
		return $response; 
    
	}

}