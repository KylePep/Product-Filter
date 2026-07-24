( function () {
    'use strict';

    var instanceCount = 0;

    function enhance( select ) {
        instanceCount += 1;
        var idPrefix = 'ff-dropdown-' + instanceCount;

        var wrapper = document.createElement( 'div' );
        wrapper.className = 'ff-dropdown';
        select.insertAdjacentElement( 'beforebegin', wrapper );
        wrapper.appendChild( select );

        // The trigger/panel become the accessible interface; the select
        // stays in the DOM (for the no-JS fallback and so existing
        // change-event listeners keep working) but is no longer the thing
        // a user or screen reader interacts with directly.
        select.classList.add( 'ff-dropdown__native' );
        select.setAttribute( 'aria-hidden', 'true' );
        select.setAttribute( 'tabindex', '-1' );

        var trigger = document.createElement( 'button' );
        trigger.type = 'button';
        trigger.className = 'ff-dropdown__trigger';
        trigger.setAttribute( 'aria-haspopup', 'listbox' );
        trigger.setAttribute( 'aria-expanded', 'false' );
        trigger.id = idPrefix + '-trigger';

        // Real child nodes, not a ::after pseudo-element on the button --
        // pseudo-elements hosted directly on native form controls like
        // <button> have unreliable cross-browser support (both layout
        // participation and transforms), unlike plain child elements.
        var triggerLabel = document.createElement( 'span' );
        triggerLabel.className = 'ff-dropdown__trigger-label';

        var triggerCaret = document.createElement( 'span' );
        triggerCaret.className = 'ff-dropdown__trigger-caret';
        triggerCaret.setAttribute( 'aria-hidden', 'true' );
        triggerCaret.textContent = '▼';

        trigger.appendChild( triggerLabel );
        trigger.appendChild( triggerCaret );

        var panel = document.createElement( 'ul' );
        panel.className = 'ff-dropdown__panel';
        panel.setAttribute( 'role', 'listbox' );
        panel.setAttribute( 'tabindex', '-1' );
        panel.id = idPrefix + '-panel';
        panel.hidden = true;

        trigger.setAttribute( 'aria-controls', panel.id );

        var options = Array.prototype.map.call( select.options, function ( option, index ) {
            var li = document.createElement( 'li' );
            li.className = 'ff-dropdown__option';
            li.setAttribute( 'role', 'option' );
            li.id = idPrefix + '-option-' + index;
            li.textContent = option.textContent;
            li.dataset.value = option.value;
            li.setAttribute( 'aria-selected', option.selected ? 'true' : 'false' );
            panel.appendChild( li );
            return li;
        } );

        function selectedIndex() {
            var index = select.selectedIndex;
            return index >= 0 ? index : 0;
        }

        function updateTriggerLabel() {
            var option = options[ selectedIndex() ];
            triggerLabel.textContent = option ? option.textContent : '';
        }

        var activeIndex = selectedIndex();

        function setActive( index ) {
            if ( index < 0 || index >= options.length ) {
                return;
            }
            activeIndex = index;
            options.forEach( function ( li, i ) {
                li.classList.toggle( 'ff-dropdown__option--active', i === index );
            } );
            panel.setAttribute( 'aria-activedescendant', options[ index ].id );
            options[ index ].scrollIntoView( { block: 'nearest' } );
        }

        function isOpen() {
            return ! panel.hidden;
        }

        function openPanel() {
            panel.hidden = false;
            trigger.setAttribute( 'aria-expanded', 'true' );
            setActive( selectedIndex() );
            panel.focus();
        }

        function closePanel() {
            panel.hidden = true;
            trigger.setAttribute( 'aria-expanded', 'false' );
        }

        function commit( index ) {
            var option = options[ index ];
            if ( ! option ) {
                return;
            }
            select.value = option.dataset.value;
            options.forEach( function ( li, i ) {
                li.setAttribute( 'aria-selected', i === index ? 'true' : 'false' );
            } );
            updateTriggerLabel();
            select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
        }

        trigger.addEventListener( 'click', function () {
            if ( isOpen() ) {
                closePanel();
            } else {
                openPanel();
            }
        } );

        trigger.addEventListener( 'keydown', function ( event ) {
            if ( isOpen() ) {
                return;
            }
            if ( 'Enter' === event.key || ' ' === event.key || 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
                event.preventDefault();
                openPanel();
            }
        } );

        var typeAhead = '';
        var typeAheadTimer = null;

        function jumpToTypeAhead( char ) {
            typeAhead += char.toLowerCase();

            if ( typeAheadTimer ) {
                clearTimeout( typeAheadTimer );
            }
            typeAheadTimer = setTimeout( function () {
                typeAhead = '';
            }, 600 );

            var count = options.length;
            for ( var offset = 1; offset <= count; offset += 1 ) {
                var index = ( activeIndex + offset ) % count;
                var label = options[ index ].textContent.trim().toLowerCase();
                if ( 0 === label.indexOf( typeAhead ) ) {
                    setActive( index );
                    return;
                }
            }
        }

        panel.addEventListener( 'keydown', function ( event ) {
            switch ( event.key ) {
                case 'ArrowDown':
                    event.preventDefault();
                    setActive( Math.min( activeIndex + 1, options.length - 1 ) );
                    break;
                case 'ArrowUp':
                    event.preventDefault();
                    setActive( Math.max( activeIndex - 1, 0 ) );
                    break;
                case 'Home':
                    event.preventDefault();
                    setActive( 0 );
                    break;
                case 'End':
                    event.preventDefault();
                    setActive( options.length - 1 );
                    break;
                case 'Enter':
                case ' ':
                    event.preventDefault();
                    commit( activeIndex );
                    closePanel();
                    trigger.focus();
                    break;
                case 'Escape':
                    event.preventDefault();
                    closePanel();
                    trigger.focus();
                    break;
                case 'Tab':
                    closePanel();
                    break;
                default:
                    if ( 1 === event.key.length ) {
                        jumpToTypeAhead( event.key );
                    }
                    break;
            }
        } );

        panel.addEventListener( 'click', function ( event ) {
            var li = event.target.closest( '.ff-dropdown__option' );
            if ( ! li ) {
                return;
            }
            commit( options.indexOf( li ) );
            closePanel();
            trigger.focus();
        } );

        document.addEventListener( 'click', function ( event ) {
            // Compare against this instance's own wrapper (not just "is
            // there any .ff-dropdown ancestor") so opening a second
            // dropdown while this one is open still closes this one.
            if ( isOpen() && event.target.closest( '.ff-dropdown' ) !== wrapper ) {
                closePanel();
            }
        } );

        wrapper.appendChild( trigger );
        wrapper.appendChild( panel );

        updateTriggerLabel();
    }

    document.addEventListener( 'DOMContentLoaded', function () {
        document.querySelectorAll( 'select.ff-filter--dropdown, select.ff-price--buckets-dropdown' ).forEach( enhance );
    } );
}() );
