<?php
if ( !defined( 'ABSPATH' ) ) exit;

function options_nav_menu( $options ) {
	if( !empty($options) ) {
		$menu = '';
		foreach ( $options as $key => $option ) {
			$menu .= '<a id="'.$key. '-tab" class="wp-nav-tab ' .$key.'-tab" title="' . esc_attr( $option['title'] ) . '" href="#'.$key.'">' . esc_html( $option['title'] ) . '</a>';
		}
		echo $menu;
	}
}

function options_container( $option_name, $options ) {
	
	$output = '';
	if( !empty($options) ) {
		foreach ( $options as $key => $option ) {
			$output .= '<div id="'.$key.'" class="options-group">'. "\n" .'<h3>'.$option["summary"].'</h3>'. "\n";
			$output .= form_table_container( $option_name, $option["fields"] );
			$output .= '</div>';
		}
	} else {
		$output = '<div class="wrap">未定义设置选项</div><!-- / .wrap -->';
	}
	echo $output;
	
}

function form_table_container( $option_name, $fields ) {

	$output = '';
	$settings = get_option( $option_name );
	if( $fields ) {
		$output .= '<table class="form-table" cellspacing="0"></tbody>';
		foreach ( $fields as $var => $field ) {

			switch ( $field['type'] ) {
					
				case 'password':
					$rows = isset($field["rows"])?$field["rows"]:4;
					$class = isset($field["class"])?'class="'.$field["class"].'"':'';
					$placeholder = isset($field["placeholder"])?'placeholder="'.$field["placeholder"].'"':'';
					$value = isset($settings[$var])?'value="'. esc_attr( $settings[$var] ).'"':'value=""';
					$output .= '<tr id="'.$var.'_text">
								<th><label for="'.$var.'">'.$field["title"].'</label></th>
								<td>
								<input type="password" id="' . esc_attr( $var ) . '" name="' .esc_attr( $option_name . '[' . $var. ']' ). '" '.$class.' rows="'.$rows.'" '.$placeholder.' '.$value.' />';
								if(!isset($field["class"]) && isset($field['description']) && !empty($field['description'])) { $output .= '<span class="desc description">'.$field['description'].'</span>'; }
								if(isset($field["class"]) && isset($field['description']) && !empty($field['description'])) { $output .= '<p class="description">'.$field['description'].'</p>'; }
								$output .= '</td></tr>';
					break;
						
				case 'textarea':
					$rows = isset($field["rows"])?$field["rows"]:4;
					$cols = isset($field["cols"])?$field["cols"]:20;
					$class = isset($field["class"])?'class="'.$field["class"].'"':'';
					$placeholder = isset($field["placeholder"])?'placeholder="'.$field["placeholder"].'"':'';
					$output .= '<tr id="'.$var.'_textarea">
								<th><label for="'.$var.'">'.$field["title"].'</label></th>
								<td><textarea id="' . esc_attr( $var ) . '" name="' .esc_attr( $option_name . '[' .$var. ']' ). '" '.$class.' rows="'.$rows.'" cols="'.$cols.'" '.$placeholder.'>' . esc_textarea( $settings[$var] ) . '</textarea>';
								if(isset($field['description']) && !empty($field['description'])) { $output .= '<p class="description">'.$field['description'].'</p>'; }
								$output .= '</td></tr>';
					break;
						
				case 'select':
					$value = isset($settings[$var])?$settings[$var]:'';
					$output .= '<tr id="'.$var.'_select">
								<th><label for="'.$var.'">'.$field["title"].'</label></th>
								<td>
								<select name="' .esc_attr( $option_name . '[' . $var. ']' ). '" id="' . esc_attr( $var ) . '">';
								foreach ($field['options'] as $key => $option ) {
									$output .= '<option'. selected( $value, $key, false ) .' value="' . esc_attr( $key ) . '">' . esc_html( $option ) . '</option>';
								}
								$output .= '</select>';
								if(isset($field['description']) && !empty($field['description'])) { $output .= '<span class="desc description">'.$field['description'].'</span>'; }
								$output .= '</td></tr>';
					break;

				case "radio":
					$value = isset($settings[$var])?$settings[$var]:'';
					$output .= '<tr id="'.$var.'_radio">
								<th><label for="'.$var.'">'.$field["title"].'</label></th>
								<td>';
								foreach ($field['options'] as $key => $option ) {
									$output .= '<input type="radio" name="' .esc_attr( $option_name . '[' . $var. ']' ). '" id="' . esc_attr( $var ) . '" value="'. esc_attr( $key ) . '" '. checked( $value, $key, false) .' /><label for="' . esc_attr( $key ) . '">' . esc_html( $option ) . '</label>';
								}
								if(isset($field['description']) && !empty($field['description'])) { $output .= '<p class="description">'.$field['description'].'</p>'; }
								$output .= '</td></tr>';		
					break;
					
				case "radio-i":
					$value = isset($settings[$var])?$settings[$var]:'';
					$output .= '<tr id="'.$var.'_radio">
								<th><label for="'.$var.'">'.$field["title"].'</label></th>
								<td>';
								foreach ($field['options'] as $key => $option ) {
									$output .= '<label class="normal-radio--label">
												<input class="normal-radio--radio" type="radio" name="' .esc_attr( $option_name . '[' . $var. ']' ). '" id="' . esc_attr( $var ) . '" value="'. esc_attr( $key ) . '" '. checked( $value, $key, false ) .' />
												<img class="normal-radio--image" src="'. esc_attr( $option ) . '" />
    											</label>';
								}
								if(isset($field['description']) && !empty($field['description'])) { $output .= '<p class="description">'.$field['description'].'</p>'; }
								$output .= '</td></tr>';		
					break;
						
				case "checkbox":
					$class = isset($field["class"])?'class="'.$field["class"].'"':'';
					$value = isset($settings[$var])?$settings[$var]:'';
					$output .= '<tr id="'.$var.'_checkbox">
								<th><label for="'.$var.'">'.$field["title"].'</label></th>
								<td><input type="checkbox" id="' . esc_attr( $var ) . '" name="' .esc_attr( $option_name . '[' . $var. ']' ). '" '.$class.' '. checked( $value, 1, false) .' value="1">';
								if(isset($field['description']) && !empty($field['description'])) { $output .= '<span class="description">'.$field['description'].'</span>'; }
								$output .= '</td></tr>';
					break;
					
				case "color":
					$value = isset($settings[$var])?'value="'. esc_attr( $settings[$var] ).'"':'value=""';
					$output .= '<tr id="'.$var.'_color">
								<th><label for="'.$var.'">'.$field["title"].'</label></th>
								<td><input type="text" id="' . esc_attr( $var ) . '" class="wp-color-result-text color-button" name="' .esc_attr( $option_name . '[' . $var. ']' ). '" '.$value.' />';
								if(isset($field['description']) && !empty($field['description'])) { $output .= '<span class="regular-color description '.$class.'">'.$field['description'].'</span>'; }
								$output .= '</td></tr>';
					break;
						
				case "upload":
					$class = isset($field["class"])?'class="'.$field["class"].'"':'';
					$placeholder = isset($field["placeholder"])?'placeholder="'.$field["placeholder"].'"':'';
					$value = isset($settings[$var])?'value="'. esc_attr( $settings[$var] ).'"':'value=""';
					$output .= '<tr id="'.$var.'_upload">
								<th><label for="'.$var.'">'.$field["title"].'</label></th>
								<td><input type="text" id="' . esc_attr( $var ) . '" name="' .esc_attr( $option_name . '[' . $var. ']' ). '" '.$class.' '.$placeholder.' '.$value.'>
								<input type="button" id="' . esc_attr( $var ) . '-btn" class="button upload-button" value="选择媒体">';
								if(isset($field['description']) && !empty($field['description'])) { $output .= '<p class="description">'.$field['description'].'</p>'; }
								$output .= '</td></tr>';
					break;
					
				case "editor":
					$output .= '<tr id="'.$var.'_editor">
								<th><label for="'.$var.'">'.$field["title"].'</label></th>
								<td>';
								echo $output;
								$textarea_name = esc_attr( $option_name . '[' . $var. ']' );
								$default_editor_settings = array(
									'textarea_name' => $textarea_name,
									'media_buttons' => false,
									'tinymce' => array( 'plugins' => 'wordpress,wplink' )
								);
								$editor_settings = array();
								if ( isset( $field['rows'] ) ) {
									$editor_settings = array(
										'wpautop' => true, // Default
										'textarea_rows' => $field['rows'],
										'tinymce' => array( 'plugins' => 'wordpress,wplink' )
									);
								}
								$editor_settings = array_merge( $default_editor_settings, $editor_settings );
								wp_editor( $settings[$var], $var, $editor_settings );
								$output = '';
								$output .= '</td></tr>';
					break;
					
				case "info":
					$value = isset($settings[$var])?'value="'. esc_attr( $settings[$var] ).'"':'value=""';
					$output .= '<tr id="'.$var.'_info">
								<th><label for="'.$var.'">'.$field["title"].'</label></th>
								<td>'.$field['description'].'</td>';
								$output .= '</tr>';
					break;

				case "mu-check":
					$multicheck = $settings[$var];
					$output .= '<tr id="'.$var.'_mu_check">
								<th><label for="'.$var.'">'.$field["title"].'</label></th>
								<td>';
								foreach ($field['options'] as $key => $option) {
									$checked = '';
									if( isset($multicheck[$key]) ) {
										$checked = checked($multicheck[$key], 1, false);
									}
									$output .= '<input id="' . esc_attr( $key ) . '" type="checkbox" name="' .esc_attr( $option_name.'['.$var.']['.$key.']' ). '" ' .$checked. ' value="1" /><span class="' . esc_attr( $key ) . ' mu-mar">' . esc_html( $option ) . '</span>';
								}
								if(isset($field['description']) && !empty($field['description'])) { $output .= '<p class="description">'.$field['description'].'</p>'; }
								$output .= '</td></tr>';
					break;

				case "mu-text":
					$multexts = isset($settings[$var])?$settings[$var]:'';
					$class = isset($field["class"])?'class="'.$field["class"].'"':'';
					$placeholder = isset($field["placeholder"])?'placeholder="'.$field["placeholder"].'"':'';
					$output .= '<tr id="'.$var.'_mu_text">
								<th><label for="'.$var.'">'.$field["title"].'</label></th>
								<td>
								<div class="mu-texts sortable ui-sortable">';
								if($multexts) {
									foreach ($multexts as $option) {
										if($option) {
											$output .= '<div class="mu-item">
														<input '.$class.' id="' . esc_attr( $var ) . '" type="text" name="' .esc_attr( $option_name.'['.$var.'][]' ). '" '.$placeholder.' value="' . esc_html( $option ) . '" />
														<a href="javascript:;" class="button del-item">删除</a>
														<span class="dashicons dashicons-menu ui-sortable-handle"></span>
														</div>';
										}
									}
								}
								$output .= '<div class="mu-item">
											<input '.$class.' id="' . esc_attr( $var ) . '" type="text" name="' .esc_attr( $option_name.'['.$var.'][]' ). '" '.$placeholder.' value="" />
											<a class="mp-mu-text button">添加</a>
											</div>';
												
								$output .= '</div></td></tr>';
					break;

				default:
					$class = isset($field["class"])?'class="'.$field["class"].'"':'';
					$placeholder = isset($field["placeholder"])?'placeholder="'.$field["placeholder"].'"':'';
					$value = isset($settings[$var])?'value="'. esc_attr( $settings[$var] ).'"':'value=""';
					$output .= '<tr id="'.$var.'_text">
								<th><label for="'.$var.'">'.$field["title"].'</label></th>
								<td>
								<input type="text" id="' . esc_attr( $var ) . '" name="' .esc_attr( $option_name . '[' . $var. ']' ). '" '.$class.' '.$placeholder.' '.$value.' />';
								if(!isset($field["class"]) && isset($field['description']) && !empty($field['description'])) { $output .= '<span class="desc description">'.$field['description'].'</span>'; }
								if(isset($field["class"]) && isset($field['description']) && !empty($field['description'])) { $output .= '<p class="description">'.$field['description'].'</p>'; }
								$output .= '</td></tr>';
					break;
	
			}
				
		}

		$output .= '</tbody></table>';

	}

	return $output;

}

if( ! function_exists('validate_sanitize_setting_options') ) {
	function validate_sanitize_setting_options( $options, $input ) {

		$clean = array( );

		if( ! empty($options) ) {
			foreach( $options as $key => $option ) {
				$fields = $option["fields"];
				foreach( $fields as $var => $field ) {
					if( ! isset( $var ) ) {
						continue;
					}
					if( ! isset( $field['type'] ) ) {
						continue;
					}
					$id = preg_replace( '/[^a-zA-Z0-9._\-]/', '', strtolower( $var ) );
					if( 'checkbox' == $field['type'] && ! isset( $input[$id] ) ) {
						$input[$id] = false;
					}
					if( 'mu-check' == $field['type'] && ! isset( $input[$id] ) ) {
						foreach ( $field['options'] as $key => $value ) {
							$input[$id][$key] = false;
						}
					}
					if( 'mu-text' == $field['type'] && ! isset( $input[$id] ) ) {
						$input[$id] = false;
					}
					if( has_filter( 'setting_sanitize_' . $field['type'] ) ) {
						$clean[$id] = apply_filters( 'setting_sanitize_' . $field['type'], $input[$id], $field );
					}
				}
			}
		}
		
		return $clean;
		
	}
}

if( ! function_exists('validate_sanitize_defalut_options') ) {
	function validate_sanitize_defalut_options( $options ) {

		$clean = array( );
		foreach( (array) $options as $key => $option ) {
			$fields = $option["fields"];
			foreach( $fields as $var => $field ) {
				if( ! isset( $var ) ) {
					continue;
				}
				if( ! isset( $field['type'] ) ) {
					continue;
				}
				$id = preg_replace( '/[^a-zA-Z0-9._\-]/', '', strtolower( $var ) );
				if( has_filter( 'setting_sanitize_' . $field['type'] ) ) {
					$clean[$id] = apply_filters( 'setting_sanitize_' . $field['type'], null, $field );
				}
			}
		}
		do_action( 'update_validate_defalut_options', $clean );
		return $clean;

	}
}

if( ! function_exists('validate_sanitize_multi_field') ) {
    function validate_sanitize_multi_field( $data ) {
        if( is_object( $data ) ) {
            $data = (array)$data;
        }
        if( is_array( $data ) ) {
            $output = array( );
            $fields = array_filter( $data );
            if( count($fields) == 0 ) {
                return $output;
            }
            if( count( $data ) == count( $data, COUNT_RECURSIVE ) ) {
                return array_filter(array_merge($data));
            }
            foreach( $data as $id => $field ) {
                if( is_array($field) ) {
                    $_fields = array_filter( $field );
                    if( count($_fields) == 0 ) {
                        continue;
                    } else {
                        $output[$id] = validate_sanitize_multi_field( array_merge( $field ) );
                    }
                } else {
                    $output[$id] = sanitize_text_field( $field );
                }
            }
            return array_merge( $output );
        }
        return sanitize_text_field( $data );
    }
}

if( ! function_exists('wp_applets_activty_bulletin') ) {
	function wp_applets_activty_bulletin( ) {
		$bulletin = get_transient( 'wp_applets_bulletin_cache' );
		if( $bulletin === false ) {
			$url = 'https://mp.weitimes.com/wp-json/wp/v2/miniprogram/bulletin';
			$request = wp_remote_get( $url );
			if( !is_wp_error( $request ) ) {
				$bulletin = json_decode( $request['body'], true );
				set_transient( 'wp_applets_bulletin_cache', $bulletin, 24*HOUR_IN_SECONDS );
			}
		}
		if( isset($bulletin["status"]) && $bulletin["status"] == 200 ) {
			echo '<div class="update-nag notice notice-info inline">'.$bulletin["content"].'</div>';
		}
	}
}

add_action( 'admin_notices', function ( ) {
	$screen = get_current_screen( );
	if( isset($_GET['page']) && isset($_REQUEST['settings-updated']) ) {
		if( $screen->id !== 'toplevel_page_'.trim($_GET['page']) ) return;
		if( 'true' === $_GET['settings-updated'] ) {
			$class = 'notice notice-success is-dismissible';
			$message = __( '设置已更新保存!', 'imahui' );
		} else {
			$class = 'notice notice-warning is-dismissible';
			$message = __( '对不起，更新出错啦，请检查!', 'imahui' );
		}
		printf( '<div class="%1$s"><p><strong>%2$s</strong></p></div>', esc_attr( $class ), esc_html( $message ) );
	}
} );