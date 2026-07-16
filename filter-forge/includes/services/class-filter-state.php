<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FF_Filter_State {

    private array $values = array();

    public function __construct( ?array $source = null ) {
        $source = $source ?? $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        foreach ( $source as $key => $value ) {
            if ( ! is_string( $key ) || ! is_scalar( $value ) ) {
                continue;
            }

            $this->values[ sanitize_key( $key ) ] = sanitize_text_field( wp_unslash( (string) $value ) );
        }
    }

    public function get( string $key ): ?string {
        return $this->values[ $key ] ?? null;
    }

    public function get_list( string $key ): array {
        $value = $this->get( $key );
        if ( null === $value || '' === $value ) {
            return array();
        }

        return array_values( array_filter( array_map( 'trim', explode( ',', $value ) ) ) );
    }

    public function has( string $key ): bool {
        return isset( $this->values[ $key ] ) && '' !== $this->values[ $key ];
    }

    public function all(): array {
        return $this->values;
    }

    public function with_override( string $key, ?string $value ): FF_Filter_State {
        $values = $this->values;

        if ( null === $value ) {
            unset( $values[ $key ] );
        } else {
            $values[ $key ] = $value;
        }

        return new self( $values );
    }
}
