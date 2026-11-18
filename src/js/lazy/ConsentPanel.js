Ext.define('Tualo.oauth2.lazy.ConsentPanel', {
    /*** */
    extend: 'Ext.panel.Panel',
    alias: 'widget.tualo_oauth2_consent_panel',

    requires: [
        'Tualo.oauth2.lazy.controller.ConsentPanel',
        'Tualo.oauth2.lazy.models.ConsentPanel'
    ],
    layout: {
        type: 'vbox',
        align: 'stretch'
    },

    config: {
        request_id: null
    },
    bind: {
        title: '{title}'
    },
    iconCls: 'fa fa-lock',


    getWindowTitle: function () {
        return this.getViewModel().get('title');
    },
    getWindowIcon: function () {
        return 'fa fa-lock';
    },

    applyRequest_id: function (request_id) {
        this.getViewModel().set('request_id', request_id);
    },
    controller: 'tualo_oauth2_consent_panel',
    viewModel: {
        type: 'tualo_oauth2_consent_panel'
    },

    listeners: {
        boxready: 'onBoxReady'
    },

    bbar: [
        '->',
        {
            text: 'Ablehnen',
            handler: 'onDeny'
        },
        {
            text: 'Akzeptieren',
            iconCls: 'fa fa-check',
            handler: 'onAccept'
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

                        '<tpl if="id == \'offline_access\'">',
                        '<b><i class="fa-solid fa-circle-exclamation"></i> {name}</b>',
                        '</tpl>',

                        '<tpl if="id != \'offline_access\'">',
                        '<b>{name}</b>',
                        '</tpl>',

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
                    emptyText: 'No data available',
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
