<?php
/*
Plugin Name: WP Glue Comments
Plugin URI: http://www.orlov.cv.ua
Description: GLue comments from same author
Version: 2017.02.07.16.30
Author: Vitaliy Orlov
Author URI: http://www.orlov.cv.ua
*/


add_action('pre_comment_on_post', function($comment_post_ID){
    global $wpdb;

    $user_id = get_current_user_id();

    if (!$user_id) return; // Skip guests

    $comment_parent = isset($_REQUEST['comment_parent']) ? intval($_REQUEST['comment_parent']) : 0;

    $sql = 'SELECT * FROM '.$wpdb->comments.' WHERE
                comment_post_ID = %d
            AND
                comment_parent = %d
            ORDER BY
                comment_ID DESC
            LIMIT 1
    ';
    $sql_vars = [$comment_post_ID,$comment_parent];
    $query = $wpdb->prepare($sql, $sql_vars);
    $row = $wpdb->get_row($query, ARRAY_A);

    if (!$row) {
        // if no posts with this parent id, try to analyze parent post

        $sql = 'SELECT * FROM '.$wpdb->comments.' WHERE
                    comment_post_ID = %d
                AND
                    comment_ID = %d
                LIMIT 1
        ';
        $sql_vars = [$comment_post_ID, $comment_parent];
        $query = $wpdb->prepare($sql, $sql_vars);
        $row = $wpdb->get_row($query, ARRAY_A);

        if (!$row) {
            return;
        }
    }

    $sameUser = $row['user_id'] == $user_id;
    $outdated = strtotime($row['comment_date']) < (current_time('timestamp') - 1*60*60);

    if (!$sameUser OR $outdated ) {
        // user id doesn't match current user
        // last comment was published more then 1h ago
        return;
    }

    $max_lengths = wp_get_comment_fields_max_lengths();

    if ( '' == trim($_POST['comment']) ) {
        return new WP_Error( 'require_valid_comment', __( '<strong>ERROR</strong>: please type a comment.' ), 200 );
    } elseif ( $max_lengths['comment_content'] < mb_strlen( $_POST['comment'], '8bit' ) ) {
        return new WP_Error( 'comment_content_column_length', __( '<strong>ERROR</strong>: your comment is too long.' ), 200 );
    }

    $minPassed = ceil((current_time('timestamp') - strtotime($row['comment_date']))/60);

    $row['comment_content'] .= "\n\n"
        ."<strong>"
        ."--[added ".$minPassed." min ago]--"
        ."</strong>"
        ."\n\n"
        .trim($_POST['comment'])
    ;

    wp_update_comment($row);

    $url = get_permalink($comment_post_ID).'#comment-'.$row['comment_ID'];

    wp_redirect( $url );

    exit;

});
