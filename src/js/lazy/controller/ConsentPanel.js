Ext.define('Tualo.oauth2.lazy.controller.ConsentPanel', {
    extend: 'Ext.app.ViewController',
    alias: 'controller.tualo_oauth2_consent_panel',

    onBoxReady: function () {
        console.log('Tualo.oauth2.lazy.controller.ConsentPanel', 'onBoxReady');
        const request_id = this.getView().getViewModel().get('request_id');
        this.queryConsentInformation(request_id);
    },

    queryConsentInformation: async function (request_id) {
        try {
            const response = await fetch(`./consentinformation?request_id=${encodeURIComponent(request_id)}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error querying consent information:', error);
            throw error;
        }
    }


});