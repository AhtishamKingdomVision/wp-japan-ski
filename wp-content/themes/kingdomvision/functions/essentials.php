<?php
// Version: 1.0.2

if (!function_exists('pre')) {
    function pre($data, $die = 0) {
        if( !empty( $data ) ){
            echo '<pre>' . print_r($data, 1) . '</pre>';
            if ($die)
                die;
        }
    }
}

if (!function_exists('cf_log')) {
    function cf_log($data, $filename = 'cronjob', $fileext = 'txt', $log = true, $time = false ){
        $directory = dirname(__FILE__) . '/logs/';
        $log_txt = $log === true ? 'log_' :'';
        $filePath = $directory. $log_txt . $filename.'.'.$fileext;
        $data = print_r($data, true);
        if( $time === true ){
            $data .= ' -- -- -- ' . current_time('mysql');
        }
        $data .= '<br>';
        ob_start();
        pre( $data );
        $data = ob_get_clean();

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true); // 0777 for full permissions, true for recursive
        }
        $myfile = file_put_contents($filePath, (string) $data . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

function hz_get_user_field( $field = '', $user_id = 0 ){

    if( $user_id > 0 ){
        
        $userdata = get_userdata( $user_id );
        if( !empty( $userdata ) && !empty( $field ) ){
            return isset( $userdata->$field ) ? $userdata->$field : '' ;
        }
        else{
            return '';
        }
    }
    else{
        return 'Invalid user id';
    }

}
