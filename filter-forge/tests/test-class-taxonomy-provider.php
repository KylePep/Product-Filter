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
}
