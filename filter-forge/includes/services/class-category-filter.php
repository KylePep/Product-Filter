<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FF_Category_Filter {

    private FF_Filter_State $filter_state;

    public function __construct( FF_Filter_State $filter_state ) {
        $this->filter_state = $filter_state;
    }

    public function apply( WP_Query $query ): void {
        $tax_query = $query->get( 'tax_query' );
        if ( ! is_array( $tax_query ) ) {
            $tax_query = array();
        }

        foreach ( $this->filter_state->all() as $param => $value ) {
            if ( '' === $value || 0 !== strpos( $param, 'ff_tax_' ) ) {
                continue;
            }

            $taxonomy = substr( $param, strlen( 'ff_tax_' ) );
            if ( ! taxonomy_exists( $taxonomy ) ) {
                continue;
            }

            $tax_query[] = array(
                'taxonomy' => $taxonomy,
                'field'    => 'slug',
                'terms'    => $this->filter_state->get_list( $param ),
            );
        }

        if ( ! empty( $tax_query ) ) {
            $query->set( 'tax_query', $tax_query );
        }
    }

    public static function resolve_param( string $taxonomy ): string {
        if ( in_array( $taxonomy, wc_get_attribute_taxonomy_names(), true ) ) {
            return 'filter_' . $taxonomy;
        }

        return 'ff_tax_' . $taxonomy;
    }
}
