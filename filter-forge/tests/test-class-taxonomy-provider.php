<?php

class Test_FF_Taxonomy_Provider extends WP_UnitTestCase {

    public function test_get_options_returns_terms_for_taxonomy() {
        $term = self::factory()->term->create_and_get(
            array(
                'taxonomy' => 'product_cat',
                'name'     => 'Airsoft Guns',
            )
        );

        $provider = new FF_Taxonomy_Provider();
        $options  = $provider->get_options( array( 'taxonomy' => 'product_cat' ) );

        $this->assertContains(
            array(
                'value' => $term->slug,
                'label' => 'Airsoft Guns',
            ),
            $options
        );
    }

    public function test_get_options_returns_empty_array_for_unknown_taxonomy() {
        $provider = new FF_Taxonomy_Provider();
        $this->assertSame( array(), $provider->get_options( array( 'taxonomy' => 'not_a_real_taxonomy' ) ) );
    }

    public function test_get_options_excludes_current_archive_term() {
        $parent = self::factory()->term->create_and_get( array( 'taxonomy' => 'product_cat', 'name' => 'Airsoft Guns' ) );
        $child  = self::factory()->term->create_and_get(
            array(
                'taxonomy' => 'product_cat',
                'name'     => 'Pistols',
                'parent'   => $parent->term_id,
            )
        );

        $this->go_to( get_term_link( $parent ) );

        $provider = new FF_Taxonomy_Provider();
        $values   = wp_list_pluck( $provider->get_options( array( 'taxonomy' => 'product_cat' ) ), 'value' );

        $this->assertNotContains( $parent->slug, $values );
        $this->assertContains( $child->slug, $values );
    }

    public function test_get_options_excludes_ancestors_of_current_archive_term() {
        $grandparent = self::factory()->term->create_and_get( array( 'taxonomy' => 'product_cat', 'name' => 'Airsoft Guns' ) );
        $parent      = self::factory()->term->create_and_get(
            array(
                'taxonomy' => 'product_cat',
                'name'     => 'Rifles',
                'parent'   => $grandparent->term_id,
            )
        );
        $child       = self::factory()->term->create_and_get(
            array(
                'taxonomy' => 'product_cat',
                'name'     => 'Bolt Action',
                'parent'   => $parent->term_id,
            )
        );

        $this->go_to( get_term_link( $parent ) );

        $provider = new FF_Taxonomy_Provider();
        $values   = wp_list_pluck( $provider->get_options( array( 'taxonomy' => 'product_cat' ) ), 'value' );

        $this->assertNotContains( $grandparent->slug, $values );
        $this->assertNotContains( $parent->slug, $values );
        $this->assertContains( $child->slug, $values );
    }

    public function test_get_options_does_not_exclude_terms_on_unrelated_archive() {
        $term = self::factory()->term->create_and_get( array( 'taxonomy' => 'product_cat', 'name' => 'Airsoft Guns' ) );

        $this->go_to( home_url( '/shop/' ) );

        $provider = new FF_Taxonomy_Provider();
        $values   = wp_list_pluck( $provider->get_options( array( 'taxonomy' => 'product_cat' ) ), 'value' );

        $this->assertContains( $term->slug, $values );
    }
}
