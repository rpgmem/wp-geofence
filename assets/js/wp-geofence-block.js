( function( wp ) {
    const { registerBlockType } = wp.blocks;
    const { __ } = wp.i18n;
    const { InspectorControls, useBlockProps, RichText } = wp.blockEditor || wp.editor;
    const { PanelBody, TextControl, SelectControl } = wp.components;
    const { Fragment } = wp.element;

    const strictOptions = [
        { label: __('Yes (hide content until validated)', 'wp-geofence'), value: 'yes' },
        { label: __('No (show content before validation)', 'wp-geofence'), value: 'no' },
    ];
    const yesNoOptions = [
        { label: __('Disabled', 'wp-geofence'), value: 'no' },
        { label: __('Enabled', 'wp-geofence'), value: 'yes' },
    ];
    const accuracyOptions = [
        { label: __('0 - No requirement','wp-geofence'), value: '0' }, 
        { label: __('50 m','wp-geofence'), value: '50' }, 
        { label: __('25 m','wp-geofence'), value: '25' }
    ];

    registerBlockType('wp-geofence/block', {
        title: __('WP Geofence', 'wp-geofence'),
        icon: 'location',
        category: 'widgets',

        attributes: {
            areas: { type: 'string', default: 'lat:-23.5505; lng:-46.6333; radius:50' },
            msg_out: { type: 'string', default: '' },
            msg_error: { type: 'string', default: '' },
            redirect: { type: 'string', default: '' },
            accuracy: { type: 'string', default: '0' },
            strict: { type: 'string', default: 'yes' },
            lazyload: { type: 'string', default: 'no' },
            cache: { type: 'string', default: 'no' },
            content: { type: 'string', default: '' }
        },

        edit: function( props ) {
            const { attributes, setAttributes } = props;
            const blockProps = useBlockProps();

            return (
                wp.element.createElement( Fragment, null,

                    wp.element.createElement( InspectorControls, null,
                        wp.element.createElement( PanelBody, { title: __('Geofence Settings', 'wp-geofence') },

                            wp.element.createElement( TextControl, { 
                                label: __('Areas (new format: lat:..; lng:..; radius:..)', 'wp-geofence'), 
                                value: attributes.areas, 
                                onChange: (v) => setAttributes({ areas: v }),
                                help: __('Example (multiple areas separated by an extra semicolon): lat:-23.5505; lng:-46.6333; radius:50 ; lat:-23.56; lng:-46.63; radius:20', 'wp-geofence')
                            }),

                            wp.element.createElement( TextControl, { 
                                label: __('Message when outside', 'wp-geofence'), 
                                value: attributes.msg_out, 
                                onChange: (v) => setAttributes({ msg_out: v })
                            }),

                            wp.element.createElement( TextControl, { 
                                label: __('GPS error message', 'wp-geofence'), 
                                value: attributes.msg_error, 
                                onChange: (v) => setAttributes({ msg_error: v })
                            }),

                            wp.element.createElement( TextControl, { 
                                label: __('Default redirect URL', 'wp-geofence'), 
                                value: attributes.redirect, 
                                onChange: (v) => setAttributes({ redirect: v })
                            }),

                            wp.element.createElement( SelectControl, { 
                                label: __('Minimum accuracy (m)', 'wp-geofence'), 
                                value: attributes.accuracy, 
                                onChange: (v) => setAttributes({ accuracy: v }),
                                options: accuracyOptions
                            }),

                            wp.element.createElement( SelectControl, { 
                                label: __('Strict mode', 'wp-geofence'), 
                                value: attributes.strict, 
                                onChange: (v) => setAttributes({ strict: v }),
                                options: strictOptions
                            }),

                            wp.element.createElement( SelectControl, { 
                                label: __('Lazy load', 'wp-geofence'), 
                                value: attributes.lazyload, 
                                onChange: (v) => setAttributes({ lazyload: v }),
                                options: yesNoOptions
                            }),

                            wp.element.createElement( SelectControl, { 
                                label: __('Intelligent cache', 'wp-geofence'), 
                                value: attributes.cache, 
                                onChange: (v) => setAttributes({ cache: v }),
                                options: yesNoOptions
                            })
                        )
                    ),

                    wp.element.createElement('div', blockProps,
                        wp.element.createElement('div', { className: 'wp-geofence-editor-notice' },
                            wp.element.createElement('p', null, __('Geofence Block Active.', 'wp-geofence'))
                        ),

                        wp.element.createElement( RichText, {
                            tagName: 'div',
                            placeholder: __('Protected geofence content…', 'wp-geofence'),
                            value: attributes.content,
                            onChange: (v) => setAttributes({ content: v })
                        })
                    )
                )
            );
        },

        save: function() {
            return null;
        }
    });

})( window.wp );