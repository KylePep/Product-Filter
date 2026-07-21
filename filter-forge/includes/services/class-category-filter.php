<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FF_Category_Filter {

    /**
     * Non-attribute taxonomies with a dedicated, non-prefixed query param
     * instead of the generic ff_tax_{taxonomy} scheme -- product_cat is
     * WooCommerce's core taxonomy and gets the plain, WooCommerce-native
     * "category" param that themes/nav links already expect.
     */
    private const NATIVE_PARAM_ALIASES = array(
        'product_cat' => 'category',
    );

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
            if ( '' === $value ) {
                continue;
            }

            $taxonomy = self::taxonomy_for_param( $param );
            if ( null === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
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

        if ( isset( self::NATIVE_PARAM_ALIASES[ $taxonomy ] ) ) {
            return self::NATIVE_PARAM_ALIASES[ $taxonomy ];
        }

        return 'ff_tax_' . $taxonomy;
    }

    private static function taxonomy_for_param( string $param ): ?string {
        $aliased_taxonomy = array_search( $param, self::NATIVE_PARAM_ALIASES, true );
        if ( false !== $aliased_taxonomy ) {
            return $aliased_taxonomy;
        }

        if ( 0 === strpos( $param, 'ff_tax_' ) ) {
            return substr( $param, strlen( 'ff_tax_' ) );
        }

        return null;
    }
}
