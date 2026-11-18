Ext.define('Tualo.oauth2.lazy.controller.DevicePanel', {
    extend: 'Ext.app.ViewController',
    alias: 'controller.tualo_oauth2_device_panel',

    onBoxReady: function () {
        const user_code = this.getView().getViewModel().get('user_code');
        if (user_code) {
            this.lookupReference('userCodeField').setValue(user_code);
            this.queryDeviceInformation(user_code);
        }
    },

    onUserCodeSpecialKey: function (field, event) {
        if (event.getKey() === event.ENTER) {
            this.onLookup();
        }
    },

    normalizeUserCode: function (value) {
        return (value || '').toUpperCase().replace(/[\s-]+/g, '');
    },

    onLookup: function () {
        const user_code = this.normalizeUserCode(this.lookupReference('userCodeField').getValue());
        if (!user_code) {
            Ext.toast({
                html: 'Bitte geben Sie den auf dem Gerät angezeigten Code ein.',
                title: 'Hinweis',
                align: 't',
                iconCls: 'fa fa-info-circle',
                duration: 3000
            });
            return;
        }
        this.queryDeviceInformation(user_code);
    },

    queryDeviceInformation: async function (user_code) {
        const viewModel = this.getView().getViewModel();
        viewModel.set('infoLoaded', false);
        viewModel.set('decided', false);
        viewModel.getStore('items').setData([]);
        try {
            const response = await fetch(`./oauth2/deviceinformation?user_code=${encodeURIComponent(user_code)}`);
            const data = await response.json();
            if (!response.ok || !data.success) {
                Ext.toast({
                    html: 'Fehler: ' + (data.msg || response.statusText || 'Unbekannter Fehler'),
                    title: 'Fehler',
                    align: 't',
                    iconCls: 'fa fa-warning',
                    duration: 3000
                });
                return;
            }
            viewModel.set('user_code', data.user_code);
            viewModel.set('client_name', data.client_name);
            viewModel.getStore('items').setData(data.requested_access);
            viewModel.set('infoLoaded', true);
        } catch (error) {
            console.error('Error querying device information:', error);
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
        const user_code = viewModel.get('user_code');

        if (!user_code || !viewModel.get('infoLoaded')) {
            return;
        }

        view.setLoading(decision === 'approve' ? 'Freigabe wird übermittelt …' : 'Anfrage wird abgelehnt …');
        try {
            const response = await fetch('./oauth2/device', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    user_code: user_code,
                    decision: decision
                })
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.msg || response.statusText || 'Unbekannter Fehler');
            }
            viewModel.set('decided', true);
            view.setLoading(false);
            Ext.toast({
                html: decision === 'approve'
                    ? 'Freigabe erteilt. Sie können zum Gerät zurückkehren – es erhält den Zugriff in Kürze automatisch.'
                    : 'Anfrage abgelehnt. Das Gerät erhält keinen Zugriff.',
                title: decision === 'approve' ? 'Freigabe erteilt' : 'Anfrage abgelehnt',
                align: 't',
                iconCls: decision === 'approve' ? 'fa fa-check' : 'fa fa-ban',
                duration: 5000
            });
        } catch (error) {
            console.error('Error submitting device decision:', error);
            view.setLoading(false);
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
