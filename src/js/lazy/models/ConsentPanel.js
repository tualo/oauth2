Ext.define('Tualo.oauth2.lazy.models.ConsentPanel', {
    extend: 'Ext.app.ViewModel',
    alias: 'viewmodel.tualo_oauth2_consent_panel',
    data: {
        request_id: null
    },
    formulas: {
        title: function (get) {
            return 'OAuth2 Consent Panel';
        }
    },
    stores: {

    }
});