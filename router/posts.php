<?php

if( !defined( 'ABSPATH' ) ) exit;

class WP_REST_Posts_Router extends WP_REST_Controller {
    
    public function __construct( ) {
		$this->namespace     = 'mp/v1';
        $this->rest_base     = 'posts';
        $this->meta          = new WP_REST_Post_Meta_Fields( 'post' );
	}

	public function register_routes( ) {

		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params( ),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
			'args' => array(
				'id' => array(
					'description' => __( 'Unique identifier for the object.' ),
					'type'        => 'integer',
				),
			),
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'                => array(
					'context'  => $this->get_context_param( array( 'default' => 'view' ) )
				),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );
		
		$schema = $this->get_item_schema( );
		
	}

	public function get_items_permissions_check( $request ) {

		return true;

	}

	public function get_items( $request ) {

		if( ! empty( $request['orderby'] ) && 'relevance' === $request['orderby'] && empty( $request['search'] ) ) {
			return new WP_Error( 'rest_no_search_term_defined', __( 'You need to define a search term to order by relevance.' ), array( 'status' => 400 ) );
		}

		if( ! empty( $request['orderby'] ) && 'include' === $request['orderby'] && empty( $request['include'] ) ) {
			return new WP_Error( 'rest_orderby_include_missing_include', __( 'You need to define an include parameter to order by include.' ), array( 'status' => 400 ) );
		}

		$registered = $this->get_collection_params( );
		$args = array( );

		$parameter_mappings = array(
			'author'         => 'author__in',
			'author_exclude' => 'author__not_in',
			'exclude'        => 'post__not_in',
			'include'        => 'post__in',
			'offset'         => 'offset',
			'order'          => 'order',
			'orderby'        => 'orderby',
			'page'           => 'paged',
			'parent'         => 'post_parent__in',
			'parent_exclude' => 'post_parent__not_in',
			'search'         => 's',
			'slug'           => 'post_name__in',
			'status'         => 'post_status',
		);

		foreach ( $parameter_mappings as $api_param => $wp_param ) {
			if( isset( $registered[ $api_param ], $request[ $api_param ] ) ) {
				$args[ $wp_param ] = $request[ $api_param ];
			}
		}

		$args['date_query'] = array( );

		if ( isset( $registered['before'], $request['before'] ) ) {
			$args['date_query'][] = array(
				'before' => $request['before'],
				'column' => 'post_date',
			);
		}

		if ( isset( $registered['modified_before'], $request['modified_before'] ) ) {
			$args['date_query'][] = array(
				'before' => $request['modified_before'],
				'column' => 'post_modified',
			);
		}

		if ( isset( $registered['after'], $request['after'] ) ) {
			$args['date_query'][] = array(
				'after'  => $request['after'],
				'column' => 'post_date',
			);
		}

		if ( isset( $registered['modified_after'], $request['modified_after'] ) ) {
			$args['date_query'][] = array(
				'after'  => $request['modified_after'],
				'column' => 'post_modified',
			);
		}

		// Ensure our per_page parameter overrides any provided posts_per_page filter.
		if ( isset( $registered['per_page'] ) ) {
			$args['posts_per_page'] = $request['per_page'];
		}

		if ( isset( $registered['sticky'], $request['sticky'] ) ) {
			$sticky_posts = get_option( 'sticky_posts', array( ) );
			if ( ! is_array( $sticky_posts ) ) {
				$sticky_posts = array( );
			}
			if ( $request['sticky'] ) {
				/*
				 * As post__in will be used to only get sticky posts,
				 * we have to support the case where post__in was already
				 * specified.
				 */
				$args['post__in'] = $args['post__in'] ? array_intersect( $sticky_posts, $args['post__in'] ) : $sticky_posts;

				/*
				 * If we intersected, but there are no post IDs in common,
				 * WP_Query won't return "no posts" for post__in = array( )
				 * so we have to fake it a bit.
				 */
				if ( ! $args['post__in'] ) {
					$args['post__in'] = array( 0 );
				}
			} elseif ( $sticky_posts ) {
				/*
				 * As post___not_in will be used to only get posts that
				 * are not sticky, we have to support the case where post__not_in
				 * was already specified.
				 */
				$args['post__not_in'] = array_merge( $args['post__not_in'], $sticky_posts );
			}
		}

		$args['post_type'] = 'post';

		$args = apply_filters( "rest_post_query", $args, $request );
		$query_args = $this->prepare_items_query( $args, $request );

		$taxonomies = wp_list_filter( get_object_taxonomies( 'post', 'objects' ), array( 'show_in_rest' => true ) );

		if( ! empty( $request['tax_relation'] ) ) {
			$query_args['tax_query'] = array( 'relation' => $request['tax_relation'] );
		}
		
		foreach ( $taxonomies as $taxonomy ) {
			$base = ! empty( $taxonomy->rest_base ) ? $taxonomy->rest_base : $taxonomy->name;
			$tax_exclude = $base . '_exclude';

			if( ! empty( $request[ $base ] ) ) {
				$query_args['tax_query'][] = array(
					'taxonomy'         => $taxonomy->name,
					'field'            => 'term_id',
					'terms'            => $request[ $base ],
					'include_children' => false,
				);
			}

			if( ! empty( $request[ $tax_exclude ] ) ) {
				$query_args['tax_query'][] = array(
					'taxonomy'         => $taxonomy->name,
					'field'            => 'term_id',
					'terms'            => $request[ $tax_exclude ],
					'include_children' => false,
					'operator'         => 'NOT IN',
				);
			}
		}

		$posts_query  = new WP_Query( );
		$query_result = $posts_query->query( $query_args );

		$posts = array( );

		foreach ( $query_result as $post ) {
			if( ! $this->check_read_permission( $post ) ) {
				continue;
			}

			$data    = $this->prepare_item_for_response( $post, $request );
			$posts[] = $this->prepare_response_for_collection( $data );
		}

		$page = isset( $query_args['paged'] ) ? (int) $query_args['paged'] : 1;
		$total_posts = $posts_query->found_posts;

		if( $total_posts < 1 ) {

			unset( $query_args['paged'] );

			$count_query = new WP_Query( );
			$count_query->query( $query_args );
			$total_posts = $count_query->found_posts;
		}

		$per_page_posts = isset($posts_query->query_vars['posts_per_page']) ? (int)$posts_query->query_vars['posts_per_page'] : 10;
		$max_pages = ceil( $total_posts / $per_page_posts );
		
		if ( $page > $max_pages && $total_posts > 0 ) {
			return new WP_Error(
				'rest_post_invalid_page_number',
				__( 'The page number requested is larger than the number of pages available.' ),
				array( 'status' => 400 )
			);
		}

		$response  = rest_ensure_response( $posts );

		$response->header( 'Cache-Control', 'max-age=3600' );
		$response->header( 'X-WP-Total', (int) $total_posts );
		$response->header( 'X-WP-TotalPages', (int) $max_pages );

		$request_params = $request->get_query_params( );
		$base           = add_query_arg( urlencode_deep( $request_params ), rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ) );

		if ( $page > 1 ) {
			$prev_page = $page - 1;

			if ( $prev_page > $max_pages ) {
				$prev_page = $max_pages;
			}

			$prev_link = add_query_arg( 'page', $prev_page, $base );
			$response->link_header( 'prev', $prev_link );
		}
		if ( $max_pages > $page ) {
			$next_page = $page + 1;
			$next_link = add_query_arg( 'page', $next_page, $base );

			$response->link_header( 'next', $next_link );
		}

		return $response;
	}

	protected function get_post( $id ) {
		$error = new WP_Error( 'rest_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );
		if( (int) $id <= 0 ) {
			return $error;
		}

		$post = get_post( (int) $id );
		if( empty( $post ) || empty( $post->ID ) || 'post' !== $post->post_type ) {
			return $error;
		}

		return $post;
	}

	public function get_item_permissions_check( $request ) {
		$post = $this->get_post( $request['id'] );
		if( is_wp_error( $post ) ) {
			return $post;
		}

		if( $post && ! empty( $request['password'] ) ) {
			if( ! hash_equals( $post->post_password, $request['password'] ) ) {
				return new WP_Error( 'rest_post_incorrect_password', __( 'Incorrect post password.' ), array( 'status' => 403 ) );
			}
		}

		if( $post ) {
			return $this->check_read_permission( $post );
		}

		return true;
	}

	public function can_access_password_content( $post, $request ) {
		if( empty( $post->post_password ) ) {
			return false;
		}

		if( empty( $request['password'] ) ) {
			return false;
		}

		return hash_equals( $post->post_password, $request['password'] );
	}

	public function get_item( $request ) {
		$post = $this->get_post( $request['id'] );
		if( is_wp_error( $post ) ) {
			return $post;
		}
		
		if( !update_post_meta( $post->ID, 'views', (int)get_post_meta( $post->ID, "views" ,true ) + 1 ) ) {
			add_post_meta($post->ID, 'views', 1, true);  
		}
			
		$data     = $this->prepare_item_for_response( $post, $request );
		$response_data = $data->get_data( );
		$data->set_data( $response_data );
		$response = rest_ensure_response( $data );

		if( is_post_type_viewable( get_post_type_object( 'post' ) ) ) {
			$response->link_header( 'alternate',  get_permalink( $post->ID ), array( 'type' => 'text/html' ) );
		}

		return $response;
	}

	protected function prepare_items_query( $prepared_args = array( ), $request = null ) {
		$query_args = array( );

		foreach ( $prepared_args as $key => $value ) {
			$query_args[ $key ] = apply_filters( "rest_query_var-{$key}", $value );
		}

		if( ! isset( $query_args['ignore_sticky_posts'] ) ) {
			$query_args['ignore_sticky_posts'] = true;
		}

		if( isset( $query_args['orderby'] ) && isset( $request['orderby'] ) ) {
			$orderby_mappings = array(
				'id'            => 'ID',
				'include'       => 'post__in',
				'slug'          => 'post_name',
				'include_slugs' => 'post_name__in',
			);

			if( isset( $orderby_mappings[ $request['orderby'] ] ) ) {
				$query_args['orderby'] = $orderby_mappings[ $request['orderby'] ];
			}

			if( $request['orderby'] == 'rand' ) {
				$query_args['date_query'] = array( array( 'after' => '1 year ago' ) );
				$query_args['update_post_meta_cache'] = false; 
				$query_args['cache_results'] = false;
			}
		}

		return $query_args;
	}

	protected function prepare_date_response( $date_gmt, $date = null ) {

		if( isset( $date ) ) {
			return mysql_to_rfc3339( $date );
		}

		if( '0000-00-00 00:00:00' === $date_gmt ) {
			return null;
		}

		return mysql_to_rfc3339( $date_gmt );
	}

	protected function prepare_item_for_database( $request ) {
		$prepared_post = new stdClass;

		if( isset( $request['id'] ) ) {
			$existing_post = $this->get_post( $request['id'] );
			if( is_wp_error( $existing_post ) ) {
				return $existing_post;
			}

			$prepared_post->ID = $existing_post->ID;
		}

		$schema = $this->get_item_schema( );

		if( ! empty( $schema['properties']['title'] ) && isset( $request['title'] ) ) {
			if( is_string( $request['title'] ) ) {
				$prepared_post->post_title = $request['title'];
			} elseif( ! empty( $request['title']['raw'] ) ) {
				$prepared_post->post_title = $request['title']['raw'];
			}
		}

		if( ! empty( $schema['properties']['content'] ) && isset( $request['content'] ) ) {
			if( is_string( $request['content'] ) ) {
				$prepared_post->post_content = $request['content'];
			} elseif( isset( $request['content']['raw'] ) ) {
				$prepared_post->post_content = $request['content']['raw'];
			}
		}

		if( ! empty( $schema['properties']['excerpt'] ) && isset( $request['excerpt'] ) ) {
			if( is_string( $request['excerpt'] ) ) {
				$prepared_post->post_excerpt = $request['excerpt'];
			} elseif( isset( $request['excerpt']['raw'] ) ) {
				$prepared_post->post_excerpt = $request['excerpt']['raw'];
			}
		}

		if( empty( $request['id'] ) ) {
			$prepared_post->post_type = 'post';
		} else {
			$prepared_post->post_type = get_post_type( $request['id'] );
		}

		$post_type = get_post_type_object( $prepared_post->post_type );

		if( ! empty( $schema['properties']['status'] ) && isset( $request['status'] ) ) {
			$status = $this->handle_status_param( $request['status'], $post_type );

			if( is_wp_error( $status ) ) {
				return $status;
			}

			$prepared_post->post_status = $status;
		}

		if( ! empty( $schema['properties']['date'] ) && ! empty( $request['date'] ) ) {
			$date_data = rest_get_date_with_gmt( $request['date'] );

			if( ! empty( $date_data ) ) {
				list( $prepared_post->post_date, $prepared_post->post_date_gmt ) = $date_data;
				$prepared_post->edit_date = true;
			}
		} elseif( ! empty( $schema['properties']['date_gmt'] ) && ! empty( $request['date_gmt'] ) ) {
			$date_data = rest_get_date_with_gmt( $request['date_gmt'], true );

			if( ! empty( $date_data ) ) {
				list( $prepared_post->post_date, $prepared_post->post_date_gmt ) = $date_data;
				$prepared_post->edit_date = true;
			}
		}

		if( ! empty( $schema['properties']['slug'] ) && isset( $request['slug'] ) ) {
			$prepared_post->post_name = $request['slug'];
		}

		if( ! empty( $schema['properties']['author'] ) && ! empty( $request['author'] ) ) {
			$post_author = (int) $request['author'];

			if( get_current_user_id( ) !== $post_author ) {
				$user_obj = get_userdata( $post_author );
				if( ! $user_obj ) {
					return new WP_Error( 'rest_invalid_author', __( 'Invalid author ID.' ), array( 'status' => 400 ) );
				}
			}

			$prepared_post->post_author = $post_author;
		}

		if( ! empty( $schema['properties']['password'] ) && isset( $request['password'] ) ) {
			$prepared_post->post_password = $request['password'];

			if( '' !== $request['password'] ) {
				if( ! empty( $schema['properties']['sticky'] ) && ! empty( $request['sticky'] ) ) {
					return new WP_Error( 'rest_invalid_field', __( 'A post can not be sticky and have a password.' ), array( 'status' => 400 ) );
				}

				if( ! empty( $prepared_post->ID ) && is_sticky( $prepared_post->ID ) ) {
					return new WP_Error( 'rest_invalid_field', __( 'A sticky post can not be password protected.' ), array( 'status' => 400 ) );
				}
			}
		}

		if( ! empty( $schema['properties']['sticky'] ) && ! empty( $request['sticky'] ) ) {
			if( ! empty( $prepared_post->ID ) && post_password_required( $prepared_post->ID ) ) {
				return new WP_Error( 'rest_invalid_field', __( 'A password protected post can not be set to sticky.' ), array( 'status' => 400 ) );
			}
		}

		if( ! empty( $schema['properties']['parent'] ) && isset( $request['parent'] ) ) {
			if( 0 === (int) $request['parent'] ) {
				$prepared_post->post_parent = 0;
			} else {
				$parent = get_post( (int) $request['parent'] );
				if( empty( $parent ) ) {
					return new WP_Error( 'rest_post_invalid_id', __( 'Invalid post parent ID.' ), array( 'status' => 400 ) );
				}
				$prepared_post->post_parent = (int) $parent->ID;
			}
		}

		return apply_filters( "rest_pre_insert_post", $prepared_post, $request );

	}

	protected function handle_status_param( $post_status, $post_type ) {

		switch ( $post_status ) {
			case 'draft':
			case 'pending':
				break;
			case 'private':
				if( ! current_user_can( $post_type->cap->publish_posts ) ) {
					return new WP_Error( 'rest_cannot_publish', __( 'Sorry, you are not allowed to create private posts in this post type.' ), array( 'status' => rest_authorization_required_code( ) ) );
				}
				break;
			case 'publish':
			case 'future':
				if( ! current_user_can( $post_type->cap->publish_posts ) ) {
					return new WP_Error( 'rest_cannot_publish', __( 'Sorry, you are not allowed to publish posts in this post type.' ), array( 'status' => rest_authorization_required_code( ) ) );
				}
				break;
			default:
				if( ! get_post_status_object( $post_status ) ) {
					$post_status = 'draft';
				}
				break;
		}

		return $post_status;
	}

	protected function check_is_post_type_allowed( $post_type ) {
		if( ! is_object( $post_type ) ) {
			$post_type = get_post_type_object( $post_type );
		}

		if( ! empty( $post_type ) ) {
			return true;
		}

		return false;
	}

	public function check_read_permission( $post ) {
		$post_type = get_post_type_object( $post->post_type );
		if( ! $this->check_is_post_type_allowed( $post_type ) ) {
			return false;
		}

		if( 'publish' === $post->post_status || current_user_can( $post_type->cap->read_post, $post->ID ) ) {
			return true;
		}

		$post_status_obj = get_post_status_object( $post->post_status );
		if( $post_status_obj && $post_status_obj->public ) {
			return true;
		}

		if( 'inherit' === $post->post_status && $post->post_parent > 0 ) {
			$parent = get_post( $post->post_parent );
			if( $parent ) {
				return $this->check_read_permission( $parent );
			}
		}

		if( 'inherit' === $post->post_status ) {
			return true;
		}

		return false;
	}

	public function prepare_item_for_response( $post, $request ) {

		$GLOBALS['post'] = $post;
		setup_postdata( $post );

		$fields = $this->get_fields_for_response( $request );

		$data = array( );

		if( in_array( 'id', $fields, true ) ) {
			$data['id'] = $post->ID;
		}
		
		if( '0000-00-00 00:00:00' === $post->post_date_gmt ) {
			$post_date_gmt = get_gmt_from_date( $post->post_date );
		} else {
			$post_date_gmt = $post->post_date_gmt;
		}
		$data['date'] = date('Y-m-d', strtotime($post->post_date));
		$data['time'] = date('H:i:s', strtotime($post->post_date));
		$data["week"] = get_wp_post_week( $post->post_date );
		
		if( in_array( 'password', $fields, true ) ) {
			$data['password'] = $post->post_password;
		}

		if( in_array( 'slug', $fields, true ) ) {
			$data['slug'] = $post->post_name;
		}

		if( in_array( 'status', $fields, true ) ) {
			$data['status'] = $post->post_status;
		}

		if( in_array( 'type', $fields, true ) ) {
			$data['type'] = $post->post_type;
		}

		if( in_array( 'link', $fields, true ) ) {
			$data['link'] = get_permalink( $post->ID );
		}

		if( in_array( 'format', $fields, true ) ) {
			$data['format'] = get_post_format( $post->ID );

			if( empty( $data['format'] ) ) {
				$data['format'] = 'standard';
			}
		}

		if( in_array( 'meta', $fields, true ) ) {
			$data['meta']               = $this->meta->get_value( $post->ID, $request );
			$data['meta']["thumbnail"] 	= wanzi_post_thumbnail( $post->ID );
    		$data['meta']["views"] 		= (int)get_post_meta( $post->ID, "views" ,true );
    		$data['meta']["count"] 		= wanzi_text_mb_strlen( wp_strip_all_tags( $post->post_content ) );
    		if( get_post_meta( $post->ID, "source" ,true ) ) {
    			$_data["meta"]["source"] 	= get_post_meta( $post->ID, "source" ,true );
    		}
		}

		if( in_array( 'title', $fields, true ) ) {
			add_filter( 'protected_title_format', array( $this, 'protected_title_format' ) );

			$data['title'] = array(
				'raw'      => $post->post_title,
				'rendered' => html_entity_decode( get_the_title( $post->ID ) ),
			);

			remove_filter( 'protected_title_format', array( $this, 'protected_title_format' ) );
		}

		$user_id = 0;
		if( isset( $request['access_token'] ) ) {
			$access_token = $request['access_token'];
			$users = MP_Auth::login( base64_decode($access_token) );
			if( $users ) {
			    $user_id = (int)$users->ID;
			}
		}

		$data['user_id'] = $user_id;//base64_encode('hnDsLPemo6VKXkW7');
		
    	$author_id = $post->post_author;
    	$author_avatar = get_user_meta( $author_id, 'avatar', true );
    	$description = get_the_author_meta( 'description', $author_id );
    	$data['author'] = array(
    		'id' => ( int )$author_id,
    		'name' => get_the_author_meta( 'nickname', $author_id ),
    		'avatar' => $author_avatar ? $author_avatar : get_avatar_url( $author_id ),
    		'description' => $description ? $description : "这个家伙太懒，连个签名都没有"
    	);
    	
    	$data["comments"] 			= wanzi_count_comment_type( $post->ID, 'comment' );
		$data["isfav"] 				= (bool)wanzi_comment_post_status( $post->ID, $user_id, 'fav' );
		$data["favs"] 				= wanzi_count_comment_type( $post->ID, 'fav' );
		$data["islike"] 			= (bool)wanzi_comment_post_status( $post->ID, $user_id, 'like' );
		$data["likes"] 				= wanzi_count_comment_type( $post->ID, 'like' );
		
		$taxonomies = wp_list_filter( get_object_taxonomies( 'post', 'objects' ), array( 'show_in_rest' => true ) );

		foreach ( $taxonomies as $taxonomy ) {
			$base = ! empty( $taxonomy->rest_base ) ? $taxonomy->rest_base : $taxonomy->name;

			if( in_array( $base, $fields, true ) ) {
				$terms = get_the_terms( $post, $taxonomy->name );
				$data[ $base ] = array( );
				if( $terms ) {
					foreach( $terms as $term ) {
						$data[ $base ][] = array(
							'id' => $term->term_id,
							'name' => $term->name,
							'description' => $term->description,
							'cover' => get_term_meta($term->term_id,'cover',true)
						);
					}
				}
			}
		}
		
		if( wp_miniprogram_option('bd_appkey') && wp_miniprogram_option('bd_secret') ) {
		    $keywords 		= get_post_meta( $post->ID, "keywords", true );
			if( ! $keywords ) {
				$tags = wp_get_post_tags( $post->ID );
				$keywords 	= implode(",", wp_list_pluck( $tags, 'name' ));
			}
    		$data["smartprogram"]["title"] 		    = html_entity_decode( get_the_title( $post->ID ) ) .'-'.get_bloginfo('name');
    		$data["smartprogram"]["keywords"] 		= $keywords;
    		$data["smartprogram"]["description"] 	= wp_strip_all_tags( wp_trim_excerpt( "", $post->ID ), true );
    		$data["smartprogram"]["image"] 		    = wanzi_post_gallery( $post->ID );
    		$data["smartprogram"]["visit"] 		    = array( 'pv' => (int)get_post_meta( $post->ID, "views" ,true ) );
    		$data["smartprogram"]["comments"] 		= wanzi_count_comment_type( $post->ID, 'comment' );
    		$data["smartprogram"]["likes"] 		    = wanzi_count_comment_type( $post->ID, 'like' );
    		$data["smartprogram"]["collects"] 		= wanzi_count_comment_type( $post->ID, 'fav' );
		}

		$has_password_filter = false;

		if( $this->can_access_password_content( $post, $request ) ) {

			add_filter( 'post_password_required', '__return_false' );

			$has_password_filter = true;

		}

		if( in_array( 'excerpt', $fields, true ) ) {

			$the_excerpt = apply_filters( 'the_excerpt', $post->post_excerpt );
			if( ! $the_excerpt ) {
			    $the_excerpt = wp_trim_excerpt( "", $post->ID );
			}
			$data['excerpt'] = array(
				'raw'       => $post->post_excerpt,
				'rendered'  => html_entity_decode( wp_trim_words( wp_strip_all_tags( $the_excerpt, true ), 80 ) ),
				'protected' => (bool) $post->post_password
			);

		}

		if( in_array( 'content', $fields, true ) ) {
			$data['content'] = array(
				'raw'       => $post->post_content,
				'rendered'  => post_password_required( $post ) ? apply_filters( 'the_content', $post->post_excerpt ) : apply_filters( 'the_content', $post->post_content ),
				'protected' => (bool) $post->post_password
			);
		}

		if( $has_password_filter ) {
			remove_filter( 'post_password_required', '__return_false' );
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );
		$response = apply_filters( "wanzi_rest_post", $response, $post, $request );

		return apply_filters( "rest_prepare_post", $response, $post, $request );
	}

	public function protected_title_format( ) {
		return '%s';
	}

	public function get_item_schema( ) {

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'post',
			'type'       => 'object',
			'properties' => array(
				'date'            => array(
					'description' => __( "The date the object was published, in the site's timezone." ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'id'              => array(
					'description' => __( 'Unique identifier for the object.' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'link'            => array(
					'description' => __( 'URL to the object.' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'slug'            => array(
					'description' => __( 'An alphanumeric identifier for the object unique to its type.' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'arg_options' => array(
						'sanitize_callback' => array( $this, 'sanitize_slug' ),
					),
				),
				'status'          => array(
					'description' => __( 'A named status for the object.' ),
					'type'        => 'string',
					'enum'        => array_keys( get_post_stati( array( 'internal' => false ) ) ),
					'context'     => array( 'view', 'edit' ),
				),
				'type'            => array(
					'description' => __( 'Type of Post for the object.' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'format'            => array(
					'description' => __( 'Format of Post for the object.' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', ),
					'readonly'    => true,
				),
				'password'        => array(
					'description' => __( 'A password to protect access to the content and excerpt.' ),
					'type'        => 'string',
					'context'     => array( 'edit' ),
				),
			),
		);

		$schema['properties']['parent'] = array(
			'description' => __( 'The ID for the parent of the object.' ),
			'type'        => 'integer',
			'context'     => array( 'view', 'edit' ),
		);

		$post_type_attributes = array(
			'title',
			'editor',
			'author',
			'excerpt',
			'thumbnail',
			'comments',
			'revisions',
			'page-attributes',
			'post-formats',
			'custom-fields',
		);
		$fixed_schemas = array(
			'post' => array(
				'title',
				'editor',
				'author',
				'excerpt',
				'thumbnail',
				'comments',
				'revisions',
				'post-formats',
				'custom-fields',
			)
		);
		foreach ( $post_type_attributes as $attribute ) {
			if( isset( $fixed_schemas[ 'post' ] ) && ! in_array( $attribute, $fixed_schemas[ 'post' ], true ) ) {
				continue;
			} elseif( ! isset( $fixed_schemas[ 'post' ] ) && ! post_type_supports( 'post', $attribute ) ) {
				continue;
			}

			switch ( $attribute ) {

				case 'title':
					$schema['properties']['title'] = array(
						'description' => __( 'The title for the object.' ),
						'type'        => 'object',
						'context'     => array( 'view', 'edit', 'embed' ),
						'arg_options' => array(
							'sanitize_callback' => null, // Note: sanitization implemented in self::prepare_item_for_database( )
							'validate_callback' => null, // Note: validation implemented in self::prepare_item_for_database( )
						),
						'properties'  => array(
							'raw' => array(
								'description' => __( 'Title for the object, as it exists in the database.' ),
								'type'        => 'string',
								'context'     => array( 'edit' ),
							),
							'rendered' => array(
								'description' => __( 'HTML title for the object, transformed for display.' ),
								'type'        => 'string',
								'context'     => array( 'view', 'edit', 'embed' ),
								'readonly'    => true,
							),
						),
					);
					break;

				case 'editor':
					$schema['properties']['content'] = array(
						'description' => __( 'The content for the object.' ),
						'type'        => 'object',
						'context'     => array( 'view', 'edit' ),
						'arg_options' => array(
							'sanitize_callback' => null, // Note: sanitization implemented in self::prepare_item_for_database( )
							'validate_callback' => null, // Note: validation implemented in self::prepare_item_for_database( )
						),
						'properties'  => array(
							'raw' => array(
								'description' => __( 'Content for the object, as it exists in the database.' ),
								'type'        => 'string',
								'context'     => array( 'edit' ),
							),
							'rendered' => array(
								'description' => __( 'HTML content for the object, transformed for display.' ),
								'type'        => 'string',
								'context'     => array( 'view', 'edit' ),
								'readonly'    => true,
							),
							'protected'       => array(
								'description' => __( 'Whether the content is protected with a password.' ),
								'type'        => 'boolean',
								'context'     => array( 'view', 'edit', 'embed' ),
								'readonly'    => true,
							),
						),
					);
					break;

				case 'author':
					$schema['properties']['author'] = array(
						'description' => __( 'The ID for the author of the object.' ),
						'type'        => 'integer',
						'context'     => array( 'view', 'edit', 'embed' ),
					);
					break;

				case 'excerpt':
					$schema['properties']['excerpt'] = array(
						'description' => __( 'The excerpt for the object.' ),
						'type'        => 'object',
						'context'     => array( 'view', 'edit', 'embed' ),
						'arg_options' => array(
							'sanitize_callback' => null, // Note: sanitization implemented in self::prepare_item_for_database( )
							'validate_callback' => null, // Note: validation implemented in self::prepare_item_for_database( )
						),
						'properties'  => array(
							'raw' => array(
								'description' => __( 'Excerpt for the object, as it exists in the database.' ),
								'type'        => 'string',
								'context'     => array( 'edit' ),
							),
							'rendered' => array(
								'description' => __( 'HTML excerpt for the object, transformed for display.' ),
								'type'        => 'string',
								'context'     => array( 'view', 'edit', 'embed' ),
								'readonly'    => true,
							),
							'protected'       => array(
								'description' => __( 'Whether the excerpt is protected with a password.' ),
								'type'        => 'boolean',
								'context'     => array( 'view', 'edit', 'embed' ),
								'readonly'    => true,
							),
						),
					);
					break;

				case 'page-attributes':
					$schema['properties']['menu_order'] = array(
						'description' => __( 'The order of the object in relation to other object of its type.' ),
						'type'        => 'integer',
						'context'     => array( 'view', 'edit' ),
					);
					break;

				case 'custom-fields':
					$schema['properties']['meta'] = $this->meta->get_field_schema( );
					break;

			}
		}

		$schema['properties']['sticky'] = array(
			'description' => __( 'Whether or not the object should be treated as sticky.' ),
			'type'        => 'boolean',
			'context'     => array( 'view', 'edit' ),
		);

		$taxonomies = wp_list_filter( get_object_taxonomies( 'post', 'objects' ), array( 'show_in_rest' => true ) );
		foreach ( $taxonomies as $taxonomy ) {
			$base = ! empty( $taxonomy->rest_base ) ? $taxonomy->rest_base : $taxonomy->name;
			$schema['properties'][ $base ] = array(
				/* translators: %s: taxonomy name */
				'description' => sprintf( __( 'The terms assigned to the object in the %s taxonomy.' ), $taxonomy->name ),
				'type'        => 'array',
				'items'       => array(
					'type'    => 'integer',
				),
				'context'     => array( 'view', 'edit' ),
			);
		}

		return $this->add_additional_fields_schema( $schema );
	}

	public function get_collection_params( ) {
		$query_params = parent::get_collection_params( );

		$query_params['context']['default'] = 'view';

		$query_params['after'] = array(
			'description'        => __( 'Limit response to posts published after a given ISO8601 compliant date.' ),
			'type'               => 'string',
			'format'             => 'date-time',
		);

		if( post_type_supports( 'post', 'author' ) ) {
			$query_params['author'] = array(
				'description'         => __( 'Limit result set to posts assigned to specific authors.' ),
				'type'                => 'array',
				'items'               => array(
					'type'            => 'integer',
				),
				'default'             => array( ),
			);
			$query_params['author_exclude'] = array(
				'description'         => __( 'Ensure result set excludes posts assigned to specific authors.' ),
				'type'                => 'array',
				'items'               => array(
					'type'            => 'integer',
				),
				'default'             => array( ),
			);
		}

		$query_params['before'] = array(
			'description'        => __( 'Limit response to posts published before a given ISO8601 compliant date.' ),
			'type'               => 'string',
			'format'             => 'date-time',
		);

		$query_params['exclude'] = array(
			'description'        => __( 'Ensure result set excludes specific IDs.' ),
			'type'               => 'array',
			'items'              => array(
				'type'           => 'integer',
			),
			'default'            => array( ),
		);

		$query_params['include'] = array(
			'description'        => __( 'Limit result set to specific IDs.' ),
			'type'               => 'array',
			'items'              => array(
				'type'           => 'integer',
			),
			'default'            => array( ),
		);

		$query_params['offset'] = array(
			'description'        => __( 'Offset the result set by a specific number of items.' ),
			'type'               => 'integer',
		);

		$query_params['order'] = array(
			'description'        => __( 'Order sort attribute ascending or descending.' ),
			'type'               => 'string',
			'default'            => 'desc',
			'enum'               => array( 'asc', 'desc' ),
		);

		$query_params['orderby'] = array(
			'description'        => __( 'Sort collection by object attribute.' ),
			'type'               => 'string',
			'default'            => 'date',
			'enum'               => array(
				'author',
				'date',
				'id',
				'include',
				'modified',
				'parent',
				'relevance',
				'slug',
				'include_slugs',
				'title',
				'rand',
			),
		);

		$post_type = get_post_type_object( 'post' );

		$query_params['slug'] = array(
			'description'       => __( 'Limit result set to posts with one or more specific slugs.' ),
			'type'              => 'array',
			'items'             => array(
				'type'          => 'string',
			),
			'sanitize_callback' => 'wp_parse_slug_list',
		);

		$query_params['status'] = array(
			'default'           => 'publish',
			'description'       => __( 'Limit result set to posts assigned one or more statuses.' ),
			'type'              => 'array',
			'items'             => array(
				'enum'          => array_merge( array_keys( get_post_stati( ) ), array( 'any' ) ),
				'type'          => 'string',
			),
			'sanitize_callback' => array( $this, 'sanitize_post_statuses' ),
		);

		$taxonomies = wp_list_filter( get_object_taxonomies( 'post', 'objects' ), array( 'show_in_rest' => true ) );

		if( ! empty( $taxonomies ) ) {
			$query_params['tax_relation'] = array(
				'description' => __( 'Limit result set based on relationship between multiple taxonomies.' ),
				'type'        => 'string',
				'enum'        => array( 'AND', 'OR' ),
			);
		}
		
		foreach ( $taxonomies as $taxonomy ) {
			$base = ! empty( $taxonomy->rest_base ) ? $taxonomy->rest_base : $taxonomy->name;

			$query_params[ $base ] = array(
				/* translators: %s: taxonomy name */
				'description'       => sprintf( __( 'Limit result set to all items that have the specified term assigned in the %s taxonomy.' ), $base ),
				'type'              => 'array',
				'items'             => array(
					'type'          => 'integer',
				),
				'default'           => array( ),
			);

			$query_params[ $base . '_exclude' ] = array(
				/* translators: %s: taxonomy name */
				'description' => sprintf( __( 'Limit result set to all items except those that have the specified term assigned in the %s taxonomy.' ), $base ),
				'type'        => 'array',
				'items'       => array(
					'type'    => 'integer',
				),
				'default'           => array( ),
			);
		}

		$query_params['sticky'] = array(
			'description'       => __( 'Limit result set to items that are sticky.' ),
			'type'              => 'boolean',
		);

		return apply_filters( "rest_post_collection_params", $query_params, $post_type );
	}

	public function sanitize_post_statuses( $statuses, $request, $parameter ) {
		$statuses = wp_parse_slug_list( $statuses );

		// The default status is different in WP_REST_Attachments_Controller
		$attributes = $request->get_attributes( );
		$default_status = $attributes['args']['status']['default'];

		foreach ( $statuses as $status ) {
			if( $status === $default_status ) {
				continue;
			}

			$post_type_obj = get_post_type_object( 'post' );

			if( current_user_can( $post_type_obj->cap->edit_posts ) ) {
				$result = rest_validate_request_arg( $status, $request, $parameter );
				if( is_wp_error( $result ) ) {
					return $result;
				}
			} else {
				return new WP_Error( 'rest_forbidden_status', __( 'Status is forbidden.' ), array( 'status' => rest_authorization_required_code( ) ) );
			}
		}

		return $statuses;
	}

}