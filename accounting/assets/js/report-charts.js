/*
 * Report Chart Pack
 * Provides color palettes and helper functions to render financial KPIs consistently.
 * Requires Chart.js v4+. Load after Chart.js and before invoking render helpers.
 */
( function ( global )
{
    'use strict';

    const palette = {
        slate: '#0f172a',
        navy: '#1d3b6f',
        cobalt: '#2563eb',
        teal: '#14b8a6',
        emerald: '#10b981',
        gold: '#fbbf24',
        coral: '#fb7185',
        neutral: '#94a3b8'
    };

    const gradients = new Map();

    function getCtx ( canvas )
    {
        const node = typeof canvas === 'string' ? document.getElementById( canvas ) : canvas;
        if ( !node )
        {
            console.warn( '[ReportChartPack] Canvas not found:', canvas );
            return null;
        }
        return node.getContext( '2d' );
    }

    function buildGradient ( ctx, colors, key )
    {
        if ( !ctx ) return null;
        const cacheKey = key || colors.join( '-' );
        if ( gradients.has( cacheKey ) )
        {
            return gradients.get( cacheKey );
        }
        const gradient = ctx.createLinearGradient( 0, 0, 0, ctx.canvas.height );
        const stops = colors.length - 1;
        colors.forEach( ( color, index ) =>
        {
            gradient.addColorStop( index / stops, color );
        } );
        gradients.set( cacheKey, gradient );
        return gradient;
    }

    function ensureChartJs ()
    {
        if ( !global.Chart )
        {
            console.warn( '[ReportChartPack] Chart.js not detected. Include it before using the helper.' );
            return false;
        }
        return true;
    }

    function mergeConfig ( base, overrides )
    {
        return Object.assign( {}, base, overrides || {} );
    }

    function baseOptions ()
    {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
                    labels: {
                        color: '#475569',
                        usePointStyle: true
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(15,23,42,0.95)',
                    titleColor: '#fff',
                    bodyColor: '#e2e8f0',
                    padding: 12,
                    cornerRadius: 8,
                    intersect: false,
                    mode: 'index'
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#475569'
                    }
                },
                y: {
                    grid: {
                        color: 'rgba(148,163,184,0.25)'
                    },
                    ticks: {
                        color: '#475569',
                        callback: val => typeof val === 'number' ? '$' + val.toLocaleString() : val
                    }
                }
            }
        };
    }

    function renderLine ( canvas, labels, series, options = {} )
    {
        if ( !ensureChartJs() ) return null;
        const ctx = getCtx( canvas );
        if ( !ctx ) return null;
        const datasets = series.map( ( s, idx ) =>
        {
            const color = s.color || Object.values( palette )[ idx % Object.keys( palette ).length ];
            return {
                label: s.label || `Series ${ idx + 1 }`,
                data: s.data || [],
                tension: 0.35,
                borderWidth: 3,
                borderColor: color,
                pointBackgroundColor: '#fff',
                pointBorderColor: color,
                pointRadius: 4,
                fill: options.fill ?? false,
                backgroundColor: options.fill ? buildGradient( ctx, [ color + '55', color + '00' ], `${ color }-fill` ) : color
            };
        } );
        return new Chart( ctx, {
            type: 'line',
            data: { labels, datasets },
            options: mergeConfig( baseOptions(), options.chartOptions )
        } );
    }

    function renderArea ( canvas, labels, data, options = {} )
    {
        return renderLine( canvas, labels, [ { data, color: palette.cobalt } ], Object.assign( { fill: true }, options ) );
    }

    function renderDoughnut ( canvas, labels, data, options = {} )
    {
        if ( !ensureChartJs() ) return null;
        const ctx = getCtx( canvas );
        if ( !ctx ) return null;
        return new Chart( ctx, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [ {
                    data,
                    backgroundColor: [ palette.cobalt, palette.emerald, palette.gold, palette.coral, palette.neutral ],
                    borderWidth: 0,
                } ]
            },
            options: mergeConfig( {
                cutout: '70%',
                plugins: {
                    legend: { position: 'right' }
                }
            }, options.chartOptions )
        } );
    }

    function renderBar ( canvas, labels, series, options = {} )
    {
        if ( !ensureChartJs() ) return null;
        const ctx = getCtx( canvas );
        if ( !ctx ) return null;
        const datasets = series.map( ( s, idx ) =>
        {
            const color = s.color || Object.values( palette )[ idx % Object.keys( palette ).length ];
            return {
                label: s.label || `Series ${ idx + 1 }`,
                data: s.data || [],
                backgroundColor: color,
                borderRadius: 6,
                barThickness: options.barThickness || undefined
            };
        } );
        return new Chart( ctx, {
            type: 'bar',
            data: { labels, datasets },
            options: mergeConfig( baseOptions(), mergeConfig( {
                plugins: {
                    legend: { display: series.length > 1 }
                },
                scales: {
                    x: {
                        stacked: options.stacked || false,
                        ticks: { color: '#475569' },
                        grid: { display: false }
                    },
                    y: {
                        stacked: options.stacked || false,
                        ticks: baseOptions().scales.y.ticks,
                        grid: baseOptions().scales.y.grid
                    }
                }
            }, options.chartOptions ) )
        } );
    }

    function renderHealthGauge ( canvas, value, options = {} )
    {
        if ( !ensureChartJs() ) return null;
        const ctx = getCtx( canvas );
        if ( !ctx ) return null;
        const clamped = Math.max( 0, Math.min( 100, value ) );
        return new Chart( ctx, {
            type: 'doughnut',
            data: {
                labels: [ 'Health', 'Remaining' ],
                datasets: [ {
                    data: [ clamped, 100 - clamped ],
                    backgroundColor: [ palette.emerald, 'rgba(226,232,240,0.6)' ],
                    borderWidth: 0
                } ]
            },
            options: {
                cutout: '78%',
                rotation: -90,
                circumference: 180,
                plugins: {
                    tooltip: { enabled: false },
                    legend: { display: false }
                }
            },
            plugins: [ {
                id: 'health-label',
                afterDraw ( chart )
                {
                    const { ctx } = chart;
                    ctx.save();
                    ctx.font = '600 20px "Inter", "Roboto", sans-serif';
                    ctx.fillStyle = palette.slate;
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillText( clamped + '%', chart.width / 2, chart.height / 1.15 );
                    ctx.restore();
                }
            } ]
        } );
    }

    function formatCurrencySeries ( raw )
    {
        return ( raw || [] ).map( value =>
        {
            if ( value === null || value === undefined ) return 0;
            return Number( value );
        } );
    }

    global.ReportChartPack = {
        palette,
        renderLine,
        renderArea,
        renderBar,
        renderDoughnut,
        renderHealthGauge,
        formatCurrencySeries
    };
} )( window );
