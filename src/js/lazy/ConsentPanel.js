Ext.define('Tualo.oauth2.lazy.ConsentPanel', {
    /*** */
    extend: 'Ext.panel.Panel',
    alias: 'widget.tualo_oauth2_consent_panel',

    requires: [
        'Tualo.oauth2.lazy.controller.ConsentPanel',
        'Tualo.oauth2.lazy.models.ConsentPanel'
    ],
    layout: {
        type: 'card',
        // align: 'stretch'
    },

    config: {
        request_id: null
    },
    bind: {
        title: '{title}'
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
            text: 'Zurück',
            handler: function () {
                Ext.History.back();
            }
        },
        {
            text: 'Akzeptieren',
            handler: function () {
                Ext.History.back();
            }
        }
    ],
    items: [

        {
            xtype: 'panel',
            html: 'Consent Panel Content'
        },
    ]
});
