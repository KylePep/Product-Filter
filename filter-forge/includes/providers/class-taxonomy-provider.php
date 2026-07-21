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
                'exclude'    => $this->current_archive_term_ids( $taxonomy ),
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

    /**
     * The term currently being viewed (and its ancestors) is redundant to
     * offer as a filter option -- the archive itself already narrows to it.
     */
    private function current_archive_term_ids( string $taxonomy ): array {
        $queried_object = get_queried_object();

        if ( ! $queried_object instanceof WP_Term || $taxonomy !== $queried_object->taxonomy ) {
            return array();
        }

        $ancestor_ids = get_ancestors( $queried_object->term_id, $taxonomy, 'taxonomy' );

        return array_merge( array( $queried_object->term_id ), $ancestor_ids );
    }
}
