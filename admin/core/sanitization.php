<?php

if ( !defined( 'ABSPATH' ) ) exit;

// 后续更新废弃
add_filter( 'setting_sanitize_text', 'sanitize_text_field' );
add_filter( 'setting_sanitize_password', 'sanitize_text_field' );
add_filter( 'setting_sanitize_select', 'wanzi_sanitize_enum', 10, 2 );
add_filter( 'setting_sanitize_radio', 'swanzi_sanitize_enum', 10, 2 );
add_filter( 'setting_sanitize_images', 'swanzi_sanitize_enum', 10, 2 );
add_filter( 'setting_sanitize_textarea', 'wanzi_sanitize_textarea' );
add_filter( 'setting_sanitize_checkbox', 'wanzi_sanitize_checkbox' );
add_filter( 'setting_sanitize_mu-check', 'wanzi_sanitize_multi_check', 10, 2 );
add_filter( 'setting_sanitize_mu-text', 'wanzi_sanitize_multi_text', 10, 2 );
add_filter( 'setting_sanitize_upload', 'wanzi_sanitize_upload' );
add_filter( 'setting_sanitize_editor', 'wanzi_sanitize_editor' );

// 数据过滤器
add_filter( 'wanzi_sanitize_text', 'sanitize_text_field' );
add_filter( 'wanzi_sanitize_number', 'sanitize_text_field' );
add_filter( 'wanzi_sanitize_password', 'sanitize_text_field' );
add_filter( 'wanzi_sanitize_editor', 'wanzi_sanitize_editor' );
add_filter( 'wanzi_sanitize_upload', 'wanzi_sanitize_upload' );
add_filter( 'wanzi_sanitize_textarea', 'wanzi_sanitize_textarea' );
add_filter( 'wanzi_sanitize_checkbox', 'wanzi_sanitize_checkbox' );
add_filter( 'wanzi_sanitize_select', 'wanzi_sanitize_enum', 10, 2 );
add_filter( 'wanzi_sanitize_radio', 'swanzi_sanitize_enum', 10, 2 );
add_filter( 'wanzi_sanitize_images', 'swanzi_sanitize_enum', 10, 2 );
add_filter( 'wanzi_sanitize_multi_field', 'wanzi_sanitize_multi_field' );
add_filter( 'wanzi_sanitize_mu-text', 'wanzi_sanitize_multi_text', 10, 2 );
add_filter( 'wanzi_sanitize_mu-check', 'wanzi_sanitize_multi_check', 10, 2 );

function wanzi_sanitize_editor( $input ) {
	global $allowedtags;
	$output = wpautop( wp_kses( $input, $allowedtags ) );
	return $output;
}

function wanzi_sanitize_upload( $input ) {
	$output = '';
	$filetype = wp_check_filetype( $input );
	if ( $filetype["ext"] ) {
		$output = esc_url( $input );
	}
	return $output;
}

function wanzi_sanitize_textarea( $input ) {
	global $allowedposttags;
	$output = wp_kses( $input, $allowedposttags );
	return $output;
}

function wanzi_sanitize_checkbox( $input ) {
	if ( $input ) {
		return '1';
	}
	return false;
}

function wanzi_sanitize_enum( $input, $option ) {
	$output = '';
	if ( array_key_exists( $input, $option['options'] ) ) {
		$output = $input;
	}
	return $output;
}

function wanzi_sanitize_multi_text( $input, $option ) {
	$output = array();
	if ( is_array( $input ) ) {
		foreach( $input as $value ) {
			$output[] = apply_filters( 'sanitize_text_field', $value );
		}
	}
	return array_filter( $output );
}

function wanzi_sanitize_multi_check( $input, $option ) {
	$output = array();
	if ( is_array( $input ) ) {
		foreach( $option['options'] as $key => $value ) {
			$output[$key] = false;
		}
		foreach( $input as $key => $value ) {
			if ( array_key_exists( $key, $option['options'] ) && $value ) {
				$output[$key] = $value;
			}
		}
	}
	return $output;
}


function wanzi_sanitize_multi_field( $data ) {
    if ( is_object( $data ) ) {
        $data = (array)$data;
    }
    if ( is_array( $data ) ) {
        $output = array( );
        $fields = array_filter( $data );
        if ( count( $fields ) == 0 ) {
            return $output;
        }
        if ( count( $data ) == count( $data, COUNT_RECURSIVE ) ) {
            return array_filter( $data );
        }
        $data = array_merge( $data );
        foreach( $data as $id => $field ) {
            if ( is_array( $field ) ) {
                $_fields = array_filter( $field );
                if ( count( $_fields ) == 0 ) {
                    continue;
                } else {
                    $output[$id] = wanzi_sanitize_multi_field( $field );
                }
            } else {
                $output[$id] = sanitize_text_field( $field );
            }
        }
        return array_filter( $output );
    }
    return sanitize_text_field( $data );
}