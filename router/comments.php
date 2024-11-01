<?php

if ( !defined( 'ABSPATH' ) ) exit;

class WP_REST_Comments_Router extends WP_REST_Controller {

	protected $meta;

	public function __construct( ) {
		$this->namespace     = 'mp/v1';
        $this->rest_base = 'comments';
		$this->meta = new WP_REST_Comment_Meta_Fields( 'comment' );
	}

	public function register_routes( ) {
		
		register_rest_route( $this->namespace,  '/' . $this->rest_base, array(
			array(
				'methods'             	=> WP_REST_Server::READABLE,
				'callback'            	=> array( $this, 'get_items' ),
				'permission_callback' 	=> array( $this, 'get_items_permissions_check' ),
				'args'                	=> array(
					'context'	=> $this->get_context_param( array( 'default' => 'view' ) )
				)
			),
			array(
				'methods'             	=> WP_REST_Server::CREATABLE,
				'callback'            	=> array( $this, 'creat_item' ),
				'permission_callback' 	=> array( $this, 'get_items_permissions_check' ),
				'args'                	=> array(
					'context'	=> $this->get_context_param( array( 'default' => 'view' ) )
				)
			)
		) );

		register_rest_route( $this->namespace,  '/' . $this->rest_base . '/mark', array(
			array(
				'methods'             	=> WP_REST_Server::CREATABLE,
				'callback'            	=> array( $this, 'edit_item' ),
				'permission_callback' 	=> array( $this, 'get_items_permissions_check' ),
				'args'                	=> array(
					'context'	=> $this->get_context_param( array( 'default' => 'view' ) )
				)
			)
		) );
		
	}

	public function get_items_permissions_check( $request ) {
		return true;
	}

	public function get_items( $request ) {
		$uid = 0;
		$post_id = isset($request["id"]) ? (int)$request["id"] : 0;
		if( ! $post_id ) {
		    return new WP_Error( 'post_id_error', __( '文章 ID 有误，不能为空.' ), array( 'status' => 400 ) );
		}
		$page = isset($request["page"]) ? (int)$request["page"] : 1;
		$type = isset($request["type"]) ? trim($request["type"]) : 'comment';
		$number = isset($request["per_page"]) ? (int)$request["per_page"] : 10;
		$offset = ( $page * $number ) - $number;
		$args = array(
			"post_id" => $post_id,
			"type" => $type, 
			"status" => 'approve',
			"number" => $number,
			"offset" => $offset,
			"parent" => 0,
			"orderby" => 'comment_date',
			"order" => 'DESC'
		);
		if( isset($request['access_token']) ) {
			$access_token = base64_decode($request['access_token']);
			$users = MP_Auth::login( $access_token );
			if( $users ) {
			    $uid = (int)$users->ID;
			}
		}
		$comments = get_comments( $args );
		$data = array( );
		foreach ($comments as $comment) {
			$comment_id = $comment->comment_ID;
			$user_id = $comment->user_id;
			$user_name = $comment->comment_author;
			$date = $comment->comment_date;
			$content = $comment->comment_content;
			$parent = $comment->comment_parent;
			if( $parent == 0 ) {
				$avatar = get_user_meta( $user_id, 'avatar', true );
				$_data["id"] = $comment_id;
				$_data["author"]["id"] = $user_id;
				$_data["author"]["name"] = ucfirst($user_name);
				if ($avatar) {
					$_data["author"]["avatar"] = $avatar;
				} else {
					$_data["author"]["avatar"] = get_avatar_url($user_id);
				}
				$_data["date"] = datetime_before($date);
				$_data["content"] = $content;
				$_data["parent"] = $parent;
				$_data["likes"] = (int)get_comment_meta( $comment_id, 'likes', true );
				$_data["islike"] = (bool)get_comment_meta( $comment_id, '_like_comment_u_'.$uid, true );
				$_data["reply_to"] = ucfirst( $user_name );
				$_data["reply"] = wanzi_get_reply_comments( $post_id, $user_name, $comment_id );
				$data[] = apply_filters( "wanzi_rest_comment", $_data, $comment, $uid );
			}		
		}
		$response  = rest_ensure_response( $data );
		return $response;
	}
	
	public function creat_item( $request ) {
		$approved = get_option('comment_moderation');
		$post_id = isset($request['id']) ? (int)$request['id'] : 0;
		if( ! $post_id ) {
		    return new WP_Error( 'post_id_error', __( '文章 ID 有误，不能为空.' ), array( 'status' => 400 ) );
		}
		$type = isset($request['type']) ? trim($request['type']) : 'comment';
		$content = isset($request['content']) ? trim($request['content']) : '';
		$parent_id = isset($request['parent']) ? (int)$request['parent'] : 0;
		$formId = isset($request['formid']) ? trim($request['formid']) : '';
		$session = base64_decode( $request['access_token'] );
		$users = MP_Auth::login( $session );
		if ( !$users ) {
			return new WP_Error( 'error', '授权信息有误' , array( 'status' => 403 ) );
		}
		$user_id = (int)$users->ID;
		$user = get_user_by( 'ID', $user_id );
		$user_name = $user->display_name;
		$user_email = $user->user_email;
		$user_url = $user->user_url;
		$post_title = get_the_title( $post_id );
		if( $type == 'comment' ) {
			if( $content == null || $content == "") {
				return new WP_Error( 'error', '内容不能为空', array( 'status' => 403 ) );
			}
			if( wp_miniprogram_option('security') ) {
				$msgCheck = wanzi_weixin_security_msg_check( $content );
				if( isset($msgCheck->errcode) && $msgCheck->errcode == 87014 ) {
					return new WP_Error( 'error', '内容含有违规关键词' , array( 'status' => 403 ) );
				}
			}
		} else {
			$comment_action = wanzi_comment_type_lable( $type );
			$comment_posts = wanzi_comment_post_lable( $post_id );
			$content = $comment_action."《".$post_title."》".$comment_posts;
		}
		if( $type == 'comment' ) {
			$commentarr = array(
				'comment_post_ID' => $post_id,
				'comment_author' => ucfirst($user_name),
				'comment_author_email' => $user_email,
				'comment_author_url' => $user_url,
				'comment_content' => $content,
				'comment_author_IP' => '',
				'comment_type' => '',
				'comment_parent' => $parent_id,
				'comment_approved' => $approved ? 0 : 1,
				'user_id' => $user_id
			);
			$comment_id = wp_insert_comment( $commentarr );
			if( $comment_id ) {
				if( !$approved ) {
					$push = bd_miniprogram_comment_reply_message( get_comment( $comment_id ) );
					$result["notice"] = $push;
				}
				$flag = false;
				if( $formId != '' && $formId != 'the formId is a mock one' ) {
					$flag = add_comment_meta($comment_id, 'formId', $formId, true); 
				}
				$likes = (int)get_comment_meta( $comment_id, 'likes', true );
				if( !update_comment_meta($comment_id, 'likes', $likes) ) {
					add_comment_meta($comment_id, 'likes', $likes, true);
				}
				$result["status"] = 200;
				$result["id"] = $comment_id;
				$result["code"] = "success";
				$result["formid"] = $flag;
				$result["message"] = $approved ? "提交成功, 待人工审核通过" : "发布完成, 刷新页面查看评论"; 			
			} else {
				$result["code"] = "success";
				$result["message"] = "评论发布失败, 请检查";
				$result["status"] = 400;                   
			}
		} else {
			$message = wanzi_comment_type_lable( $type );
			$eliminate = wanzi_comment_type_in( $type );
			if( $eliminate ) {
				if( $parent_id != 0 ) {
					return new WP_Error( 'error', '父类 ID 错误, ID 必须为 0', array( 'status' => 403 ) );
				}
				$args = array(
				    'post_id' => $post_id,
				    'type__in' => array( $type ),
				    'user_id' => $user_id,
				    'parent' => 0,
				    'status' => 'approve',
				    'orderby' => 'comment_date',
				    'order' => 'DESC'
				);
				$custom_comment = get_comments( $args );
				if( $custom_comment ) {
					foreach ( $custom_comment as $comment ) {
						$comment_id = $comment->comment_ID;
					}
					$comment_status = wp_delete_comment($comment_id, true);
					if ($comment_status) {
						$result["code"] = "success";
						$result["message"] = "取消".$message."成功";
						$result["status"] = 202; 
					} else {
						$result["code"] = "success";
						$result["message"] = "取消".$message."失败";
						$result["status"] = 400; 
					}
				} else {
					$customarr = array(
						'comment_post_ID' => $post_id,
						'comment_author' => ucfirst($user_name),
						'comment_author_email' => $user_email,
						'comment_author_url' => $user_url,
						'comment_content' => $content,
						'comment_author_IP' => '',
						'comment_type' => $type,
						'comment_parent' => $parent_id,
						'comment_approved' => 1,
						'user_id' => $user_id
					);
					$comment_id = wp_insert_comment( $customarr );
					if($comment_id) {
						$result["code"] = "success";
						$result["message"] = $message."成功";
						$result["status"] = 200;
					} else {
						$result["code"] = "success";
						$result["message"] = $message."失败";
						$result["status"] = 400;
					}
				}
			} else {
				$args = array(
				    'post_id' => $post_id,
				    'type__in' => array( $type ),
				    'user_id' => $user_id,
				    'parent' => $parent_id,
				    'status' => 'approve',
				    'orderby' => 'comment_date',
				    'order' => 'DESC'
				);
				$custom_comment = get_comments( $args );
				if( $custom_comment ) {
					foreach ( $custom_comment as $comment ) {
						$parent_id = (int)$comment->comment_ID;
					}
				}
				$customarr = array(
					'comment_post_ID' => $post_id,
					'comment_author' => ucfirst($user_name),
					'comment_author_email' => $user_email,
					'comment_author_url' => $user_url,
					'comment_content' => $content,
					'comment_author_IP' => '',
					'comment_type' => $type,
					'comment_parent' => $parent_id,
					'comment_approved' => 1,
					'user_id' => $user_id
				);
				$comment_id = wp_insert_comment( $customarr );
				if($comment_id) {
					$result["code"] = "success";
					$result["message"] = $message."成功";
					$result["status"] = 200;
				} else {
					$result["code"] = "success";
					$result["message"] = $message."失败";
					$result["status"] = 400;
				}
			}
		}
		$response  = rest_ensure_response( $result );
		return $response;
	}

	public function edit_item( $request ) {
		$comment_id = $request['id'];
		$access_token = $request['access_token'];
		$users = MP_Auth::login( base64_decode( $access_token ) );
		if ( !$users ) {
			return new WP_Error( 'error', '授权信息有误,无法查询用户' , array( 'status' => 403 ) );
		}
		$user_id 	= $users->ID;
		$openid		= get_user_meta( $user_id, 'openid', true );
		$meta_key	= '_like_comment_u_'.$user_id;
		$meta_value = $openid ? $openid : $users->user_login;
		$likes 		= (int)get_comment_meta( $comment_id, 'likes', true );
		$islike 	= get_comment_meta( $comment_id, $meta_key, true );
		if( $islike ) {
			$status = delete_comment_meta( $comment_id, $meta_key );
			if( $status ) {
				$like_count = $likes ? $likes - 1 : 0;
				if( !update_comment_meta($comment_id, 'likes', $like_count) ) {
					add_comment_meta($comment_id, 'likes', $like_count, true);
				}
				$result = array( "status" => 202, "code" => "success", "success" => "取消点赞" );
			} else {
				$result = array( "status" => 400, "code" => "fail", "success" => "取消失败" );
			}
		} else {
			$status = add_comment_meta($comment_id, $meta_key, $meta_value, true);
			if( $status ) {
				$like_count = $likes ? $likes + 1 : 1;
				if( !update_comment_meta($comment_id, 'likes', $like_count) ) {
					add_comment_meta($comment_id, 'likes', $like_count, true);
				}
				$result = array( "status" => 200, "code" => "success", "success" => "点赞成功" );
			} else {
				$result = array( "status" => 400, "code" => "fail", "success" => "点赞失败" );
			}
		}
		$response  = rest_ensure_response( $result );
		return $response;
	}
	
}