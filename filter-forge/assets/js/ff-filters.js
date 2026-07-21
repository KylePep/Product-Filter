function ffApplyPriceRange( wrapper ) {
    const minInput = wrapper.querySelector( '[data-ff-price-role="min"]' );
    const maxInput = wrapper.querySelector( '[data-ff-price-role="max"]' );

    let params = FFUrl.set( 'min_price', minInput ? minInput.value : '' );
    params     = FFUrl.set( 'max_price', maxInput ? maxInput.value : '', params );

    FFUrl.navigate( params );
}

document.addEventListener( 'change', function ( event ) {
    const bucketSelect = event.target.closest( '.ff-price--buckets-dropdown' );
    if ( bucketSelect ) {
        const [ min, max ] = bucketSelect.value.split( '|' );
        let params = FFUrl.set( 'min_price', min || '' );
        params     = FFUrl.set( 'max_price', max || '', params );

        FFUrl.navigate( params );
        return;
    }

    const input = event.target.closest( '[data-ff-param]' );
    if ( ! input ) {
        return;
    }

    const list  = input.closest( '.ff-filter' );
    const param = input.getAttribute( 'data-ff-param' );
    let params  = new URLSearchParams( window.location.search );

    if ( input.type === 'checkbox' ) {
        const existing = ( params.get( param ) || '' ).split( ',' ).filter( Boolean );
        const value    = input.value;
        const next     = input.checked
            ? existing.concat( [ value ] )
            : existing.filter( function ( v ) { return v !== value; } );

        params = FFUrl.set( param, next.join( ',' ) );
    } else if ( input.type === 'radio' || input.tagName === 'SELECT' ) {
        params = FFUrl.set( param, input.value );
    }

    if ( list ) {
        const filterKey = list.getAttribute( 'data-ff-filter-key' );
        document.querySelectorAll( '[data-ff-parent-key="' + filterKey + '"][data-ff-reset-on-change="yes"]' ).forEach(
            function ( child ) {
                params = FFUrl.remove( child.getAttribute( 'data-ff-param' ), params );
            }
        );
    }

    FFUrl.navigate( params );
} );

document.addEventListener( 'click', function ( event ) {
    const applyBtn = event.target.closest( '.ff-price__apply' );
    if ( applyBtn ) {
        const wrapper = applyBtn.closest( '.ff-price--input' );
        if ( wrapper ) {
            ffApplyPriceRange( wrapper );
        }
        return;
    }

    const clearBtn = event.target.closest( '.ff-filter__clear, .ff-price__clear' );
    if ( ! clearBtn ) {
        return;
    }

    let params = FFUrl.remove( clearBtn.getAttribute( 'data-ff-param' ) );

    const secondary = clearBtn.getAttribute( 'data-ff-param-secondary' );
    if ( secondary ) {
        params = FFUrl.remove( secondary, params );
    }

    FFUrl.navigate( params );
} );

document.addEventListener( 'keydown', function ( event ) {
    if ( 'Enter' !== event.key ) {
        return;
    }

    const wrapper = event.target.closest( '.ff-price--input' );
    if ( ! wrapper ) {
        return;
    }

    event.preventDefault();
    ffApplyPriceRange( wrapper );
} );
