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
            const response = await fetch(`./oauth2/consentinformation?request_id=${encodeURIComponent(request_id)}`);
            if (!response.ok) {

                const json = await response.json();
                Ext.toast({
                    html: 'Fehler: ' + (json.msg || response.statusText || 'Unbekannter Fehler'),
                    title: 'Fehler',
                    align: 't',
                    iconCls: 'fa fa-warning',
                    duration: 3000
                });

                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();
            console.log('Consent information:', data.requested_access);
            this.getView().getViewModel().set('csrf_token', data.csrf_token);
            this.getView().getViewModel().getStore('items').setData(data.requested_access);
            return data;
        } catch (error) {
            console.error('Error querying consent information:', error);
            throw error;
        }
    },

    onAccept: function () {
        this.submitDecision('approve');
    },

    onDeny: function () {
        this.submitDecision('deny');
    },

    submitDecision: async function (decision) {
        const view = this.getView();
        const viewModel = view.getViewModel();
        const request_id = viewModel.get('request_id');
        const csrf_token = viewModel.get('csrf_token');

        if (!request_id || !csrf_token) {
            Ext.toast({
                html: 'Die Anfrage ist nicht mehr gültig. Bitte starten Sie die Anmeldung erneut.',
                title: 'Fehler',
                align: 't',
                iconCls: 'fa fa-warning',
                duration: 3000
            });
            return;
        }

        view.setDisabled(true);
        view.setLoading(decision === 'approve' ? 'Einwilligung wird übermittelt …' : 'Anfrage wird abgelehnt …');
        try {
            const response = await fetch('./oauth2/consent', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    request_id: request_id,
                    csrf_token: csrf_token,
                    decision: decision
                })
            });
            const data = await response.json();
            if (!response.ok || !data.success || !data.redirect_uri) {
                throw new Error(data.msg || response.statusText || 'Unbekannter Fehler');
            }
            // Both approval and denial redirect back to the requesting application.
            window.location.assign(data.redirect_uri);
        } catch (error) {
            console.error('Error submitting consent decision:', error);
            view.setLoading(false);
            view.setDisabled(false);
            Ext.toast({
                html: 'Fehler: ' + error.message,
                title: 'Fehler',
                align: 't',
                iconCls: 'fa fa-warning',
                duration: 3000
            });
        }
    }


});