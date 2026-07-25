function ffApplyPriceRange( wrapper ) {
    const minInput = wrapper.querySelector( '[data-ff-price-role="min"]' );
    const maxInput = wrapper.querySelector( '[data-ff-price-role="max"]' );

    let params = FFUrl.set( 'min_price', minInput ? minInput.value : '' );
    params     = FFUrl.set( 'max_price', maxInput ? maxInput.value : '', params );

    FFUrl.navigate( params );
}

function ffClampSliderHandles( wrapper, changedRole ) {
    const minInput = wrapper.querySelector( '[data-ff-price-role="min"]' );
    const maxInput = wrapper.querySelector( '[data-ff-price-role="max"]' );

    if ( ! minInput || ! maxInput ) {
        return;
    }

    if ( parseFloat( minInput.value ) > parseFloat( maxInput.value ) ) {
        if ( 'min' === changedRole ) {
            minInput.value = maxInput.value;
        } else {
            maxInput.value = minInput.value;
        }
    }
}

function ffUpdateSliderVisual( wrapper ) {
    const minInput = wrapper.querySelector( '[data-ff-price-role="min"]' );
    const maxInput = wrapper.querySelector( '[data-ff-price-role="max"]' );
    const range    = wrapper.querySelector( '.ff-price__slider-range' );
    const minLabel = wrapper.querySelector( '[data-ff-slider-display="min"]' );
    const maxLabel = wrapper.querySelector( '[data-ff-slider-display="max"]' );

    if ( ! minInput || ! maxInput || ! range ) {
        return;
    }

    const boundMin = parseFloat( minInput.min );
    const boundMax = parseFloat( minInput.max );
    const span     = Math.max( boundMax - boundMin, 1 );

    const minVal = parseFloat( minInput.value );
    const maxVal = parseFloat( maxInput.value );

    range.style.left  = ( ( minVal - boundMin ) / span * 100 ) + '%';
    range.style.width = ( ( maxVal - minVal ) / span * 100 ) + '%';

    if ( minLabel ) {
        minLabel.textContent = '$' + minInput.value;
    }
    if ( maxLabel ) {
        maxLabel.textContent = '$' + maxInput.value;
    }
}

document.addEventListener( 'input', function ( event ) {
    const rangeInput = event.target.closest( '.ff-price__range' );
    if ( ! rangeInput ) {
        return;
    }

    const wrapper = rangeInput.closest( '.ff-price--slider' );
    if ( ! wrapper ) {
        return;
    }

    ffClampSliderHandles( wrapper, rangeInput.getAttribute( 'data-ff-price-role' ) );
    ffUpdateSliderVisual( wrapper );
} );

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
        const wrapper = applyBtn.closest( '.ff-price--input, .ff-price--slider' );
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

    const wrapper = event.target.closest( '.ff-price--input, .ff-price--slider' );
    if ( ! wrapper ) {
        return;
    }

    event.preventDefault();
    ffApplyPriceRange( wrapper );
} );

/**
 * Native grouped radios (shared `name`) select on arrow-key press,
 * changing the value as focus moves -- no confirmation step. Overridden
 * here so arrow keys only move focus; the radio isn't selected until
 * Space (already native) or Enter (added below).
 */
document.addEventListener( 'keydown', function ( event ) {
    const radio = event.target.closest( 'input[type="radio"][data-ff-param]' );
    if ( ! radio ) {
        return;
    }

    if ( 'Enter' === event.key ) {
        if ( ! radio.checked ) {
            radio.checked = true;
            radio.dispatchEvent( new Event( 'change', { bubbles: true } ) );
        }
        return;
    }

    const isNext = 'ArrowDown' === event.key || 'ArrowRight' === event.key;
    const isPrev = 'ArrowUp' === event.key || 'ArrowLeft' === event.key;
    if ( ! isNext && ! isPrev ) {
        return;
    }

    event.preventDefault();

    const group = Array.prototype.filter.call(
        document.getElementsByName( radio.name ),
        function ( el ) { return 'radio' === el.type; }
    );
    const index = group.indexOf( radio );
    if ( -1 === index ) {
        return;
    }

    const nextIndex = isNext
        ? ( index + 1 ) % group.length
        : ( index - 1 + group.length ) % group.length;

    group[ nextIndex ].focus();
} );
