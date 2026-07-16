<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FF_Relationship_Resolver {

    public function should_render( array $config, FF_Filter_State $state ): bool {
        $parent_key          = $config['parent_key'] ?? '';
        $hide_until_selected = ! empty( $config['hide_until_selected'] );

        if ( '' === $parent_key || ! $hide_until_selected ) {
            return true;
        }

        return $state->has( $parent_key );
    }
}
