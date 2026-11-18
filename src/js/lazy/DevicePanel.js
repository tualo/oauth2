Ext.define('Tualo.oauth2.lazy.DevicePanel', {
    extend: 'Ext.panel.Panel',
    alias: 'widget.tualo_oauth2_device_panel',

    requires: [
        'Tualo.oauth2.lazy.controller.DevicePanel',
        'Tualo.oauth2.lazy.models.DevicePanel'
    ],
    layout: {
        type: 'vbox',
        align: 'stretch'
    },

    config: {
        user_code: null
    },
    bind: {
        title: '{title}'
    },
    iconCls: 'fa fa-mobile',

    getWindowTitle: function () {
        return this.getViewModel().get('title');
    },
    getWindowIcon: function () {
        return 'fa fa-mobile';
    },

    applyUser_code: function (user_code) {
        this.getViewModel().set('user_code', user_code || null);
    },
    controller: 'tualo_oauth2_device_panel',
    viewModel: {
        type: 'tualo_oauth2_device_panel'
    },

    listeners: {
        boxready: 'onBoxReady'
    },

    tbar: [
        {
            xtype: 'textfield',
            reference: 'userCodeField',
            fieldLabel: 'Gerätecode',
            labelWidth: 90,
            emptyText: 'z. B. BCDF-GHJK',
            flex: 1,
            maxLength: 16,
            enableKeyEvents: true,
            fieldStyle: 'text-transform: uppercase; font-family: monospace;',
            listeners: {
                specialkey: 'onUserCodeSpecialKey'
            }
        },
        {
            text: 'Prüfen',
            iconCls: 'fa fa-search',
            handler: 'onLookup'
        }
    ],

    bbar: [
        '->',
        {
            text: 'Ablehnen',
            handler: 'onDeny',
            bind: {
                disabled: '{!canDecide}'
            }
        },
        {
            text: 'Bestätigen',
            iconCls: 'fa fa-check',
            handler: 'onAccept',
            bind: {
                disabled: '{!canDecide}'
            }
        }
    ],
    items: [

        {
            xtype: 'panel',
            layout: 'fit',
            bodyPadding: 25,
            flex: 1,
            items: [
                {
                    xtype: 'dataview',
                    itemTpl: [
                        '<tpl for=".">',
                        '<div style="margin-bottom: 10px; padding: 10px; border: 1px solid #ccc; border-radius: 5px; min-height: 50px;max-width: 100%; box-sizing: border-box;" class="item-wrap">',
                        '<b>{name}</b>',
                        '<br/>',
                        '<span>{description}</span>',
                        '<tpl if="duration">',
                        '<br/>',
                        '<i style="color:#666;">{duration}</i>',
                        '</tpl>',
                        '</div>',
                        '</tpl>'
                    ],
                    bind: {
                        store: '{items}'
                    },

                    itemSelector: 'div.item-wrap',
                    emptyText: 'Geben Sie den Gerätecode ein und wählen Sie „Prüfen“.',
                    height: 200,
                }
            ]
        },
        {
            xtype: 'panel',
            layout: 'fit',
            bodyPadding: 25,
            bind: {
                html: '{mainDescription}'
            }
        }
    ]
});
