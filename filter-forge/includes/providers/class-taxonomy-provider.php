<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FF_Taxonomy_Provider implements FF_Option_Provider {

    public function get_options( array $context ): array {
        $taxonomy = $context['taxonomy'] ?? '';

        if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
            return array();
        }

        $terms = get_terms(
            array(
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
            )
        );

        if ( is_wp_error( $terms ) ) {
            return array();
        }

        return array_map(
            static function ( WP_Term $term ): array {
                return array(
                    'value' => $term->slug,
                    'label' => $term->name,
                );
            },
            $terms
        );
    }
}
