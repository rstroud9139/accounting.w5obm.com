( function ()
{
    'use strict';

    const SELECTOR_ACTION = '[data-report-action]';

    function ready ( callback )
    {
        if ( document.readyState === 'loading' )
        {
            document.addEventListener( 'DOMContentLoaded', callback, { once: true } );
        }
        else
        {
            callback();
        }
    }

    function findTarget ( trigger )
    {
        const selector = trigger.getAttribute( 'data-report-target' ) || '#reportShellBody';
        const node = document.querySelector( selector );
        if ( !node )
        {
            console.warn( '[ReportExport] Target not found for selector', selector );
        }
        return node;
    }

    function sanitizeFilename ( raw )
    {
        return ( raw || 'accounting-report' )
            .toString()
            .trim()
            .replace( /\s+/g, '_' )
            .replace( /[^a-z0-9_\-]+/gi, '' )
            .toLowerCase();
    }

    function exportPdf ( trigger )
    {
        const target = findTarget( trigger );
        if ( !target ) return;

        if ( typeof html2pdf === 'undefined' )
        {
            console.warn( '[ReportExport] html2pdf.js is not loaded. Include it before report-export.js.' );
            return;
        }

        const filename = sanitizeFilename( trigger.getAttribute( 'data-report-filename' ) || document.title ) + '.pdf';
        const orientation = trigger.getAttribute( 'data-report-orientation' ) || 'portrait';
        const format = trigger.getAttribute( 'data-report-format' ) || 'letter';
        const scale = parseFloat( trigger.getAttribute( 'data-report-scale' ) || '2' ) || 2;
        const margin = parseFloat( trigger.getAttribute( 'data-report-margin' ) || '0.5' ) || 0.5;

        const clone = target.cloneNode( true );
        clone.style.background = '#fff';
        clone.style.padding = '1.5rem';

        html2pdf().set( {
            margin,
            filename,
            html2canvas: {
                scale,
                useCORS: true,
                logging: false
            },
            jsPDF: {
                unit: 'in',
                format,
                orientation
            },
            pagebreak: { mode: [ 'css', 'legacy' ] }
        } ).from( clone ).save();
    }

    function handleAction ( event, trigger )
    {
        const action = trigger.getAttribute( 'data-report-action' );
        if ( !action ) return;

        switch ( action )
        {
            case 'print':
                event.preventDefault();
                window.print();
                break;
            case 'pdf':
                event.preventDefault();
                exportPdf( trigger );
                break;
            default:
                break;
        }
    }

    ready( function ()
    {
        document.body.addEventListener( 'click', function ( event )
        {
            const trigger = event.target.closest( SELECTOR_ACTION );
            if ( !trigger ) return;
            handleAction( event, trigger );
        } );
    } );
} )();
