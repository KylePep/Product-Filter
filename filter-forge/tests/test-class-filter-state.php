<?php

class Test_FF_Filter_State extends WP_UnitTestCase {

    public function test_get_returns_sanitized_value() {
        $state = new FF_Filter_State( array( 'ff_tax_product_cat' => 'pistols' ) );
        $this->assertSame( 'pistols', $state->get( 'ff_tax_product_cat' ) );
    }

    public function test_get_returns_null_for_missing_key() {
        $state = new FF_Filter_State( array() );
        $this->assertNull( $state->get( 'missing' ) );
    }

    public function test_get_list_splits_comma_separated_values() {
        $state = new FF_Filter_State( array( 'ff_tax_product_cat' => 'pistols,rifles' ) );
        $this->assertSame( array( 'pistols', 'rifles' ), $state->get_list( 'ff_tax_product_cat' ) );
    }

    public function test_has_returns_false_for_empty_string() {
        $state = new FF_Filter_State( array( 'ff_brand' => '' ) );
        $this->assertFalse( $state->has( 'ff_brand' ) );
    }

    public function test_ignores_non_scalar_values() {
        $state = new FF_Filter_State( array( 'weird' => array( 'a' ) ) );
        $this->assertNull( $state->get( 'weird' ) );
    }

    public function test_with_override_replaces_a_key_without_mutating_the_original() {
        $state    = new FF_Filter_State( array( 'ff_tax_product_cat' => 'pistols', 'ff_brand' => 'krytac' ) );
        $modified = $state->with_override( 'ff_tax_product_cat', 'rifles' );

        $this->assertSame( 'pistols', $state->get( 'ff_tax_product_cat' ) );
        $this->assertSame( 'rifles', $modified->get( 'ff_tax_product_cat' ) );
        $this->assertSame( 'krytac', $modified->get( 'ff_brand' ) );
    }

    public function test_with_override_removes_key_when_value_is_null() {
        $state    = new FF_Filter_State( array( 'ff_tax_product_cat' => 'pistols' ) );
        $modified = $state->with_override( 'ff_tax_product_cat', null );

        $this->assertFalse( $modified->has( 'ff_tax_product_cat' ) );
    }
}
