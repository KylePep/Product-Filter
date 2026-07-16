<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface FF_Option_Provider {

    /**
     * @param array $context Provider-specific context (e.g. taxonomy name, meta key).
     * @return array<int, array{value: string, label: string}>
     */
    public function get_options( array $context ): array;
}
