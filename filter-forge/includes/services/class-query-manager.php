<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FF_Query_Manager {

    private FF_Category_Filter $category_filter;
    private FF_Meta_Filter $meta_filter;

    public function __construct( FF_Category_Filter $category_filter, FF_Meta_Filter $meta_filter ) {
        $this->category_filter = $category_filter;
        $this->meta_filter     = $meta_filter;
    }

    public function register(): void {
        add_action( 'pre_get_posts', array( $this, 'maybe_apply' ) );
    }

    public function maybe_apply( WP_Query $query ): void {
        if ( is_admin() || ! $query->is_main_query() || ! self::is_supported_archive() ) {
            return;
        }

        $this->category_filter->apply( $query );
        $this->meta_filter->apply( $query );
    }

    public static function is_supported_archive(): bool {
        return is_shop() || is_product_category() || is_product_tag() || is_product_taxonomy();
    }
}
