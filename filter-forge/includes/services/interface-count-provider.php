<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface FF_Count_Provider {

    /**
     * @param array $query_args A WP_Query args array describing exactly which
     *                          products to count.
     */
    public function get_count( array $query_args ): int;
}
