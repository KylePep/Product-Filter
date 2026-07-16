<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FF_Meta_Provider implements FF_Option_Provider {

    public function get_options( array $context ): array {
        global $wpdb;

        $meta_key = $context['meta_key'] ?? '';
        if ( '' === $meta_key ) {
            return array();
        }

        $values = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT pm.meta_value
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE pm.meta_key = %s
                AND p.post_type = 'product'
                AND p.post_status = 'publish'
                AND pm.meta_value != ''
                ORDER BY pm.meta_value ASC",
                $meta_key
            )
        );

        return array_map(
            static function ( string $value ): array {
                return array(
                    'value' => $value,
                    'label' => $value,
                );
            },
            $values
        );
    }
}
