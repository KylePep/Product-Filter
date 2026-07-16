<?php

class Test_FF_Relationship_Resolver extends WP_UnitTestCase {

    public function test_should_render_true_when_no_parent_configured() {
        $resolver = new FF_Relationship_Resolver();
        $state    = new FF_Filter_State( array() );

        $this->assertTrue( $resolver->should_render( array(), $state ) );
    }

    public function test_should_render_false_when_hidden_until_parent_selected_and_parent_is_empty() {
        $resolver = new FF_Relationship_Resolver();
        $state    = new FF_Filter_State( array() );
        $config   = array( 'parent_key' => 'category', 'hide_until_selected' => true );

        $this->assertFalse( $resolver->should_render( $config, $state ) );
    }

    public function test_should_render_true_when_hidden_until_parent_selected_and_parent_has_a_value() {
        $resolver = new FF_Relationship_Resolver();
        $state    = new FF_Filter_State( array( 'category' => 'pistols' ) );
        $config   = array( 'parent_key' => 'category', 'hide_until_selected' => true );

        $this->assertTrue( $resolver->should_render( $config, $state ) );
    }

    public function test_should_render_true_when_parent_configured_but_hide_until_selected_is_false() {
        $resolver = new FF_Relationship_Resolver();
        $state    = new FF_Filter_State( array() );
        $config   = array( 'parent_key' => 'category', 'hide_until_selected' => false );

        $this->assertTrue( $resolver->should_render( $config, $state ) );
    }
}
