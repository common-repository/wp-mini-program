<?php

if( !defined( 'ABSPATH' ) ) exit;

class WP_REST_Users_Router extends WP_REST_Controller {

	public function __construct( ) {
		$this->namespace     = 'mp/v1';
        $this->resource_name = 'user';
	}

	public function register_routes() {
		
		register_rest_route( $this->namespace, '/'.$this->resource_name.'/login', array(
			array(
				'methods'             	=> WP_REST_Server::CREATABLE,
				'callback'            	=> array( $this, 'wp_user_login_by_code' ),
				'permission_callback' 	=> array( $this, 'wp_user_login_permissions_check' ),
				'args'                	=> $this->wp_user_auth_collection_params()
			)
		) );
		
		register_rest_route( $this->namespace, '/'.$this->resource_name.'/openid', array(
			array(
				'methods'             	=> WP_REST_Server::CREATABLE,
				'callback'            	=> array( $this, 'wp_user_openid_by_code' ),
				'permission_callback' 	=> array( $this, 'wp_user_login_permissions_check' ),
				'args'                	=> $this->wp_user_openid_collection_params()
			)
		) );
		
		register_rest_route( $this->namespace, '/'.$this->resource_name.'/mine', array(
			array(
				'methods'             	=> WP_REST_Server::READABLE,
				'callback'            	=> array( $this, 'wp_userdata_by_token' ),
				'permission_callback' 	=> array( $this, 'wp_user_login_permissions_check' ),
				'args'                	=> array(
					'context' => $this->get_context_param( array( 'default' => 'view' ) )
				)
			)
		) );
		
		register_rest_route( $this->namespace, '/'.$this->resource_name.'/update', array(
			array(
				'methods'             	=> WP_REST_Server::CREATABLE,
				'callback'            	=> array( $this, 'wp_user_update_info' ),
				'permission_callback' 	=> array( $this, 'wp_user_login_permissions_check' ),
				'args'                	=> array(
					'context' => $this->get_context_param( array( 'default' => 'view' ) )
				)
			)
		) );
		
		register_rest_route( $this->namespace, '/'.$this->resource_name.'/upload', array(
			array(
				'methods'             	=> WP_REST_Server::CREATABLE,
				'callback'            	=> array( $this, 'wp_user_handle_upload' ),
				'permission_callback' 	=> array( $this, 'wp_user_login_permissions_check' ),
				'args'					=> array(
					'context' => $this->get_context_param( array( 'default' => 'view' ) )
				)
			)
		) );
		
	}

	public function wp_user_login_permissions_check( $request ) {
		return true;
	}

	public function wp_user_login_by_code( $request ) {
		
		date_default_timezone_set( datetime_timezone() );
		
		$params		= $request->get_params();
		$appid 		= wp_miniprogram_option('appid');
		$appsecret 	= wp_miniprogram_option('secretkey');
		$role 		= wp_miniprogram_option('use_role');
		
		if( empty($params['code']) ) {
			return new WP_Error( 'error', '用户登录凭证（有效期五分钟）参数错误', array( 'status' => 403 ) );
		}
		if( empty($params['encryptedData']) && empty($params['iv']) ) {
			return new WP_Error( 'error', '用户登录加密数据和加密算法的初始向量参数错误', array( 'status' => 403 ) );
		}

		$args = array(
			'appid' => $appid,
			'secret' => $appsecret,
			'js_code' => trim($params['code']),
			'grant_type' => 'authorization_code'
		);
		
		$api = 'https://api.weixin.qq.com/sns/jscode2session';
		$url = add_query_arg( $args, $api );
		$remote = wp_remote_get( $url );
		if( is_wp_error( $remote ) || !isset($remote['body']) ) {
			return new WP_Error( 'error', '获取授权 OpenID 和 Session 错误', array( 'status' => 403, 'message' => $remote ) );
		}
		
		$body = stripslashes( $remote['body'] );
		$session = json_decode( stripslashes( $remote['body'] ), true );
		if( $session['errcode'] != 0 ) {
			return new WP_Error( 'error', '获取用户信息错误,请检查设置', array( 'status' => 403, 'message' => $session ) );
		}

		$openId = $session['openid'];
		$unionId = $session['unionid'];
		$session_key = $session['session_key'];
		$nickName = 'U'.time( ).rand(10, 99);
		$avatarUrl = apply_filters( 'wanzi_defalut_gavatar', $gavatar = MINI_PROGRAM_API_URL."logo.png" );

		$user_id = 0;
		$token = MP_Auth::generate_session( );
		$expire = isset($token['expire_in']) ? $token['expire_in'] : date( 'Y-m-d H:i:s', time() + 86400 );
		$token_id = isset($token['session_key']) ? $token['session_key'] : $session_key;
		$user_pass = wp_generate_password( 16, false );

		if( $unionId ) {
			$users = get_userdata_by_meta( 'unionid', $unionId );
			if( isset( $users->user_id ) ) {
				$user = get_user_by( 'ID', $users->user_id );
				$user_id = $users->user_id;
				update_user_meta( $user_id, 'openid', $openId );
				update_user_meta( $user_id, 'unionid', $unionId );
				update_user_meta( $user_id, 'expire_in', $expire );
				update_user_meta( $user_id, 'session_key', $token_id );
				update_user_meta( $user_id, 'platform', 'wechat');
			} else if( username_exists($openId) ) {
				$user = get_user_by( 'login', $openId );
				$user_id = $user->ID;
				update_user_meta( $user_id, 'openid', $openId );
				update_user_meta( $user_id, 'unionid', $unionId );
				update_user_meta( $user_id, 'expire_in', $expire );
				update_user_meta( $user_id, 'session_key', $token_id );
				update_user_meta( $user_id, 'platform', 'wechat');
			} else {
				$users = get_userdata_by_meta( 'openid', $openId );
				if( isset( $users->user_id ) ) {
					$user_id = $users->user_id;
					update_user_meta( $user_id, 'openid', $openId );
					update_user_meta( $user_id, 'unionid', $unionId );
					update_user_meta( $user_id, 'expire_in', $expire );
					update_user_meta( $user_id, 'session_key', $token_id );
					update_user_meta( $user_id, 'platform', 'wechat');
				} else {
				    $auth_code = MP_Auth::decryptData( $appid, $session_key, urldecode($params['encryptedData']), urldecode($params['iv']), $data );
            		/*
            		if( $auth_code != 0 ) {
            			return new WP_Error( 'error', '用户信息解密错误', array( 'status' => 403, 'code' => $auth_code ) );
            		}
            		*/
					$user_data = json_decode( $data, true );
					$userdata = array(
						'user_login' 			=> $openId,
						'nickname' 				=> isset($user_data['nickName']) ? trim($user_data['nickName']) : $nickName,
						'first_name'			=> isset($user_data['nickName']) ? trim($user_data['nickName']) : $nickName,
						'user_nicename' 		=> $openId,
						'display_name' 			=> isset($user_data['nickName']) ? trim($user_data['nickName']) : $nickName,
						'user_email' 			=> date('Ymdhms').'@qq.com',
						'role' 					=> $role,
						'user_pass' 			=> $user_pass,
						'gender'				=> isset($user_data['gender']) ? (int)$user_data['gender'] : 0,
						'openid'				=> $openId,
						'city'					=> isset($user_data['city']) ? $user_data['city'] : '',
						'avatar' 				=> isset($user_data['avatarUrl']) ? $user_data['avatarUrl'] : $avatarUrl,
						'province'				=> isset($user_data['province']) ? $user_data['province'] : '',
						'country'				=> isset($user_data['country']) ? $user_data['country'] : '',
						'language'				=> isset($user_data['language']) ? $user_data['language'] : '',
						'expire_in'				=> $expire
					);
					$user_id = wp_insert_user( $userdata );			
					if( is_wp_error( $user_id ) ) {
						return new WP_Error( 'error', '创建用户失败', array( 'status' => 400, 'error' => $user_id ) );				
					}
					add_user_meta( $user_id, 'unionid', $unionId );
					add_user_meta( $user_id, 'session_key', $token_id );
					add_user_meta( $user_id, 'platform', 'wechat');
				}
			}
		} else if( username_exists($openId) ) {
			$user = get_user_by( 'login', $openId );
			$user_id = $user->ID;
			update_user_meta( $user_id, 'openid', $openId );
			update_user_meta( $user_id, 'unionid', $unionId );
			update_user_meta( $user_id, 'expire_in', $expire );
			update_user_meta( $user_id, 'session_key', $token_id );
			update_user_meta( $user_id, 'platform', 'wechat');
		} else {
			$users = get_userdata_by_meta( 'openid', $openId );
			if( isset( $users->user_id ) ) {
				$user_id = $users->user_id;
				update_user_meta( $user_id, 'openid', $openId );
				update_user_meta( $user_id, 'unionid', $unionId );
				update_user_meta( $user_id, 'expire_in', $expire );
				update_user_meta( $user_id, 'session_key', $token_id );
				update_user_meta( $user_id, 'platform', 'wechat');
			} else {
				$auth_code = MP_Auth::decryptData( $appid, $session_key, urldecode($params['encryptedData']), urldecode($params['iv']), $data );
				/*
            	if( $auth_code != 0 ) {
            		return new WP_Error( 'error', '用户信息解密错误', array( 'status' => 403, 'code' => $auth_code ) );
            	}
            	*/
				$user_data = json_decode( $data, true );
				$userdata = array(
					'user_login' 			=> $openId,
					'nickname' 				=> isset($user_data['nickName']) ? trim($user_data['nickName']) : $nickName,
					'first_name'			=> isset($user_data['nickName']) ? trim($user_data['nickName']) : $nickName,
					'user_nicename' 		=> $openId,
					'display_name' 			=> isset($user_data['nickName']) ? trim($user_data['nickName']) : $nickName,
					'user_email' 			=> date('Ymdhms').'@qq.com',
					'role' 					=> $role,
					'user_pass' 			=> $user_pass,
					'gender'				=> isset($user_data['gender']) ? (int)$user_data['gender'] : 0,
					'openid'				=> $openId,
					'city'					=> isset($user_data['city']) ? $user_data['city'] : '',
					'avatar' 				=> isset($user_data['avatarUrl']) ? $user_data['avatarUrl'] : $avatarUrl,
					'province'				=> isset($user_data['province']) ? $user_data['province'] : '',
					'country'				=> isset($user_data['country']) ? $user_data['country'] : '',
					'language'				=> isset($user_data['language']) ? $user_data['language'] : '',
					'expire_in'				=> $expire
				);
				$user_id = wp_insert_user( $userdata );			
				if( is_wp_error( $user_id ) ) {
					return new WP_Error( 'error', '创建用户失败', array( 'status' => 400, 'error' => $user_id ) );				
				}
				add_user_meta( $user_id, 'unionid', $unionId );
				add_user_meta( $user_id, 'session_key', $token_id );
				add_user_meta( $user_id, 'platform', 'wechat');
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
				"avatarUrl" 	=> $current_user->avatar ? $current_user->avatar : $avatarUrl,
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
	
	public function wp_user_openid_by_code( $request ) {
		
		$appid 		= wp_miniprogram_option('appid');
		$appsecret 	= wp_miniprogram_option('secretkey');
		
		$params = $request->get_params();

		$args = array(
			'appid' => $appid,
			'secret' => $appsecret,
			'js_code' => $params['code'],
			'grant_type' => 'authorization_code'
		);
		
		$api = 'https://api.weixin.qq.com/sns/jscode2session';
		
		$url = add_query_arg( $args, $api );
		
		$remote = wp_remote_get( $url );
		
		if( is_wp_error( $remote ) || !isset($remote['body']) ) {
			return new WP_Error( 'error', '授权 API 错误', array( 'status' => 403, 'message' => $remote ) );
		}

		$body = stripslashes( $remote['body'] );
		
		$response = json_decode( $body, true );
		
		unset($response['session_key']);
		
		return $response;
		
	}
	
	public function wp_userdata_by_token( $request ) {
	    
	    $access_token = $request->get_param('access_token');
		$users = MP_Auth::login( base64_decode( $access_token ) );
		if( !$users ) {
			return new WP_Error( 'error', '授权信息有误' , array( 'status' => 403 ) );
		}
		
		$user_id 	  = (int)$users->ID;
		$current_user = get_user_by( 'ID', $user_id );
		$avatarUrl    = apply_filters( 'wanzi_defalut_gavatar', $gavatar = MINI_PROGRAM_API_URL."logo.png" );
		$expire_in = get_user_meta( $user_id, 'expire_in', true );
		$session_key = get_user_meta( $user_id, 'session_key', true );
		
		if( is_multisite() ) {
			$blog_id = get_current_blog_id();
			$roles = ( array )$current_user->roles[$blog_id];
		} else {
			$roles = ( array )$current_user->roles;
		}
		
		$user         = array(
		    "user" => array(
    			"userId"		=> $user_id,
    			"nickName"		=> $current_user->nickname,
    			"openId"		=> $current_user->openid,
    			"avatarUrl" 	=> $current_user->avatar ? $current_user->avatar : $avatarUrl,
    			"gender"		=> (int)$current_user->gender,
    			"city"			=> $current_user->city,
    			"province"		=> $current_user->province,
    			"country"		=> $current_user->country,
    			"language"		=> $current_user->language,
    			"role"			=> $roles[0],
    			'platform'		=> $current_user->platform,
    			"description"	=> $current_user->description
			),
			"access_token" => base64_encode( $session_key ),
			"expired_in" => strtotime( $expire_in ) * 1000
		);
		
		$user = apply_filters( "wanzi_rest_user_data", $user, $request );
		
		$response = rest_ensure_response( $user );
		return $response;
		
	}
	
	public function wp_user_update_info( $request ) {
	    
	    $access_token = $request->get_param('access_token');
		$users = MP_Auth::login( base64_decode( $access_token ) );
		if( !$users ) {
			return new WP_Error( 'error', '授权信息有误' , array( 'status' => 403 ) );
		}

		$args 		= array( );
		$user_id 	= (int)$users->ID;
		$user 		= get_user_by( 'ID', $user_id );
		$openId 	= get_user_meta( $user_id, 'openid', true );
		$args['ID'] = $user_id;
		
		$session    = MP_Auth::generate_session( );
		if( empty( $session ) ) {
			return new WP_Error( 'fail', 'Session 生成失败', array( 'status' => 403 ) );
		}
		
		$params = array(
			'nickname' 				=> 'nickname',
			'description' 			=> 'description',
			'url' 					=> 'user_url',
			'email' 				=> 'user_email',
			'password' 				=> 'user_pass',
			'gender'				=> 'gender',
			'city'					=> 'city',
			'avatar' 				=> 'avatar',
			'province'				=> 'province',
			'country'				=> 'country',
			'language'				=> 'language'
		);
		foreach ( $params as $api => $param ) {
			if( isset( $request[ $api ] ) ) {
				$args[ $param ] = $request[ $api ];
			}
		}
		
		if( !empty($args['description']) ) {
			$security = wanzi_weixin_security_msg_check( esc_attr($args['description']) );
			if( isset($security->errcode) && $security->errcode == 87014 ) {
				return new WP_Error( 'error', '描述含有违规关键词' , array( 'status' => 403 ) );
			}
		}
		if( !empty($args['nickname']) ) {
			$security = wanzi_weixin_security_msg_check( esc_attr($args['nickname']) );
			if( isset($security->errcode) && $security->errcode == 87014 ) {
				return new WP_Error( 'error', '昵称含有违规关键词' , array( 'status' => 403 ) );
			}
			$args['first_name'] 	= esc_attr($args['nickname']);
			$args['display_name'] 	= esc_attr($args['nickname']);
		}
		
		$update_id  = wp_update_user( $args );
		if( is_wp_error( $update_id ) ) {
			return new WP_Error( 'error', '更新用户信息失败' , array( 'status' => 400, 'error' => $update_id ) );
		}
		
		update_user_meta( $update_id, 'expire_in', $session['expire_in'] );
		update_user_meta( $update_id, 'session_key', $session['session_key'] );
		
		$current_user = get_user_by( 'ID', $update_id );
		if( is_multisite() ) {
			$blog_id = get_current_blog_id();
			$roles = ( array )$current_user->roles[$blog_id];
		} else {
			$roles = ( array )$current_user->roles;
		}

		wp_set_current_user( $update_id, $current_user->user_login );
		wp_set_auth_cookie( $update_id, true );

		$user = array(
			"user"	=> array(
				"userId"		=> $update_id,
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
			"access_token" => base64_encode( $session['session_key'] ),
			"expired_in" => strtotime( $session['expire_in'] ) * 1000
		);
		
		$user = apply_filters( "wanzi_rest_user_info", $user, $request );

		$response = rest_ensure_response( $user );
		return $response;
		
	}
	
	public function wp_user_handle_upload( $request ) {
	    
	    $access_token = $request->get_param('access_token');
		$users = MP_Auth::login( base64_decode( $access_token ) );
		if( !$users ) {
			return new WP_Error( 'error', '授权信息有误' , array( 'status' => 403 ) );
		}
		
		$user_id        = $users->ID;
		
		$data           = array( );
		$wp_upload      = wp_upload_dir( );
		$upload_path    = $wp_upload['basedir'] .'/wanzi-avatar/';
		$upload_urls    = $wp_upload['baseurl'] .'/wanzi-avatar/';
		
		$allowedExts    = array("gif", "jpeg", "jpg", "png", "bmp");
		$filename       = $_FILES["file"]["name"];
		$filetype       = $_FILES["file"]["type"];
		$type           = explode("/", $filetype);
		$extension      = explode(".", $filename);
		$allowedextend  = apply_filters( 'rest_allowed_upload_ext', $allowedExts );
		
		if ( current($type) === 'image' && $_FILES["file"]["size"] < 307200 && in_array(end($extension), $allowedextend) ) {
		    if ( $_FILES["file"]["error"] > 0 ) {
		        return new WP_Error( 'error', $_FILES["file"]["error"] , array( 'status' => 403 ) );
		    } else {
		        $token = MP_Auth::we_miniprogram_access_token( );
		        if( !isset($token['access_token']) || empty($token['access_token']) ) {
		            return new WP_Error( 'error', 'access token 错误' , array( 'status' => 403 ) );
	            }
	            $url = 'https://api.weixin.qq.com/wxa/img_sec_check?access_token='.trim($token['access_token']);
	            $args = array(
                    'timeout'     => 60,
            	    'redirection' => 5,
            	    'blocking'    => true,
            	    'httpversion' => '1.0',
                    'data_format' => 'body',
                    'sslverify'   => false,
            		'headers'     => array(
                        "Content-Type" => "application/x-www-form-urlencoded"
                    ),
            		'body'        => array(
                        'media' => new CURLFile( $_FILES['file']['tmp_name'] )
                    )
                );
                $media = wp_remote_post( $url, $args );
                $media = wp_remote_retrieve_body( $media );
		        $media = json_decode( $media );
		        if( isset($media->errcode) && $media->errcode == 87014 ) {
    				return new WP_Error( 'error', '上传图片含有违规内容' , array( 'status' => 403 ) );
    			}
		        if( !is_dir($upload_path) ) {
        			mkdir($upload_path, 0755);
        		}
        		move_uploaded_file($_FILES["file"]["tmp_name"], $upload_path . $filename);
        		if( !is_file($upload_path . $filename) ) {
        		    $data = array(
        		        "status" => 400,
        		        "mssage" => "上传失败, 请稍候再试"
        		    );
        		} else {
        		    $avatarUrl = $upload_urls . $filename;
        		    update_user_meta( $user_id, 'avatar', $avatarUrl );
        		    //update_user_meta( $user_id, 'avatar_update_time', time() );
        		    $data = array(
        		        "status" => 200,
        		        "mssage" => "上传成功, 头像更新完成"
		            );
        		}
		    }
		} else {
		    $data = array(
		        "status" => 400,
		        "mssage" => "文件不合法或超出限制"
		    );
		}
		
		$response = rest_ensure_response( $data );
		return $response;
		
	}

	public function wp_user_auth_collection_params() {
		$params = array( );
		$params['encryptedData'] = array(
			'default'	=> '',
			'description'	=> "微信授权登录，包括敏感数据在内的完整用户信息的加密数据.",
			'type'	=>	 "string"
		);
		$params['iv'] = array(
			'default'	=> '',
			'description'	=> "微信授权登录，加密算法的初始向量.",
			'type'	=>	 "string"
		);
		$params['code'] = array(
			'required' => true,
			'default'	=> '',
			'description'	=> "用户登录凭证",
			'type'	=>	 "string"
		);
		return $params;
	}

	public function wp_user_openid_collection_params() {
		$params = array( );
		$params['code'] = array(
			'required' => true,
			'default'	=> '',
			'description'	=> "用户登录凭证（有效期五分钟）",
			'type'	=>	 "string"
		);
		return $params;
	}

}