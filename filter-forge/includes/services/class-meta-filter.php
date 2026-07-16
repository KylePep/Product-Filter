<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FF_Meta_Filter {

    private FF_Filter_State $filter_state;

    public function __construct( FF_Filter_State $filter_state ) {
        $this->filter_state = $filter_state;
    }

    public function apply( WP_Query $query ): void {
        $meta_query = $query->get( 'meta_query' );
        if ( ! is_array( $meta_query ) ) {
            $meta_query = array();
        }

        foreach ( $this->filter_state->all() as $param => $value ) {
            if ( '' === $value || 0 !== strpos( $param, 'ff_' ) || 0 === strpos( $param, 'ff_tax_' ) ) {
                continue;
            }

            $meta_key     = substr( $param, strlen( 'ff_' ) );
            $meta_query[] = array(
                'key'     => $meta_key,
                'value'   => $this->filter_state->get_list( $param ),
                'compare' => 'IN',
            );
        }

        if ( ! empty( $meta_query ) ) {
            $query->set( 'meta_query', $meta_query );
        }
    }
}
