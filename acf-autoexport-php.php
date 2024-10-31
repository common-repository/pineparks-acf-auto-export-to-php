<?php
/**
 * Plugin Name: Pineparks - ACF Auto Export to PHP
 * Plugin URI: 
 * Description: Exports all ACF groups into PHP file when you update any of the group and includes this file on load for better performance.
 * Requires at least: 5.0
 * Tested up to: 6.5
 * Version: 1.0.2
 * Author: Gleb Makarov
 * Author URI: https://www.pineparks.com
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PineparksACFAutoExport{

    public $acf_folder = 'acf-fields-php';
    public $acf_file = 'acf-field-groups';

    public function __construct() {
        add_action('save_post', [$this, 'save_post'], 10, 2);
        add_action('wp_loaded', [$this, 'load_fields'], 100);
    }

    public function get_folder(){
        $folder = apply_filters('pineparks/acfautoexport/folder', $this->acf_folder);
        return $folder;
    }
    public function get_filename(){
        $filename = apply_filters('pineparks/acfautoexport/filename', $this->acf_file);
        return $filename;
    }
    public function get_file_path( $ext = 'php' ){
        $folder = TEMPLATEPATH . '/' . $this->get_folder();
        $filename = $this->get_filename();

        return $folder . '/' . $filename . '.'.$ext;
    }

    public function save_post( $post_id, $post ){
        if( !function_exists('acf_add_local_field_group') ) return;

        /* do not save if this is an auto save routine */
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return $post_id;
        }

        /* bail early if not acf-field-group */
        if ('acf-field-group' !== $post->post_type) {
            return $post_id;
        }

        /* only save once! WordPress save's a revision as well. */
        if (wp_is_post_revision($post_id)) {
            return $post_id;
        }

        // /* verify nonce */
        // if (!acf_verify_nonce('field_group')) {
        //     return $post_id;
        // }

        $folder = TEMPLATEPATH . '/' . $this->get_folder();
        $filename = $this->get_filename();

        if (!file_exists( $folder )) {
            mkdir( $folder, 0775, true);
        }

        $field_groups = $this->get_field_groups_json();

        $str_replace  = array(
            '  '         => "\t",
            "'!!__(!!\'" => "__('",
            "!!\', !!\'" => "', '",
            "!!\')!!'"   => "')",
            'array ('    => 'array(',
        );
        
        $preg_replace = array(
            '/([\t\r\n]+?)array/' => 'array',
            '/[0-9]+ => array/'   => 'array',
        );

        $export = '';

        // loop
        if ( $field_groups ) {
            $export .= "<?php " . "\r\n" . "\r\n";
            $export .= "if( function_exists('acf_add_local_field_group') ):" . "\r\n" . "\r\n";
                foreach ( $field_groups as $field_group ) {
                    // code
                    $code = var_export( $field_group, true );
                    // change double spaces to tabs
                    $code = str_replace( array_keys( $str_replace ), array_values( $str_replace ), $code );
                    // correctly formats "=> array("
                    $code = preg_replace( array_keys( $preg_replace ), array_values( $preg_replace ), $code );
                    // echo
                    $export .= "acf_add_local_field_group({$code});" . "\r\n" . "\r\n";
                }
            $export .= 'endif;';
        }

        if( $export ){ 
            $file_path = $this->get_file_path();
            $json_path = $this->get_file_path('json');

            $this->write_file( $file_path, $export );
            $this->write_file( $json_path, json_encode($field_groups) );
        }

        return $post_id;
    }

    function write_file( $file_path, $export ){
        if (is_file($file_path)) {
            unlink($file_path);
        }

        $fd = fopen($file_path, "wb");
        
        fwrite($fd, $export);
        fclose($fd);
    }


    function get_field_group_keys(){

        // vars
        $choices      = array();
        $field_groups = acf_get_field_groups();

        // loop
        if ( $field_groups ) {
            foreach ( $field_groups as $field_group ) {
                $choices[] = $field_group['key'];
            }
        }

        return $choices;
    }

    function get_field_groups_json() {

        // vars
        $selected = $this->get_field_group_keys();
        $json     = array();

        // bail early if no keys
        if ( ! $selected ) {
            return false;
        }

        // construct JSON
        foreach ( $selected as $key ) {

            // load field group
            $field_group = acf_get_field_group( $key );

            // validate field group
            if ( empty( $field_group ) ) {
                continue;
            }

            // load fields
            $field_group['fields'] = acf_get_fields( $field_group );

            // prepare for export
            $field_group = acf_prepare_field_group_for_export( $field_group );

            // add to json array
            $json[] = $field_group;

        }

        // return
        return $json;

    }

    public function load_fields(){
        $file_path = $this->get_file_path();
        if (is_file($file_path)) require_once( $file_path );
    }
}

new PineparksACFAutoExport();