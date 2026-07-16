<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FF_Count_Service implements FF_Count_Provider {

    public function get_count( array $query_args ): int {
        $cache_key = 'ff_count_' . md5( wp_json_encode( $query_args ) );
        $cached    = wp_cache_get( $cache_key, 'filter-forge' );

        if ( false !== $cached ) {
            return (int) $cached;
        }

        $query_args['fields']         = 'ids';
        $query_args['posts_per_page'] = -1;
        $query_args['no_found_rows']  = false;

        $query = new WP_Query( $query_args );
        $count = (int) $query->found_posts;

        wp_cache_set( $cache_key, $count, 'filter-forge' );

        return $count;
    }
}
