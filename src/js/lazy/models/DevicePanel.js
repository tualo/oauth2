Ext.define('Tualo.oauth2.lazy.models.DevicePanel', {
    extend: 'Ext.app.ViewModel',
    alias: 'viewmodel.tualo_oauth2_device_panel',
    data: {
        user_code: null,
        client_name: null,
        infoLoaded: false,
        decided: false
    },
    formulas: {
        title: function (get) {
            return 'Gerätefreigabe';
        },
        canDecide: function (get) {
            return get('infoLoaded') && !get('decided');
        },
        mainDescription: function (get) {
            let data = [
                '<div style="margin-bottom: 10px; padding: 10px; border: 1px solid #ccc; border-radius: 5px; min-height: 50px;max-width: 100%; box-sizing: border-box;" class="item-wrap">',
                '<b>Wichtig: Prüfen Sie diese Anfrage sorgfältig!</b><br/>',
                'Ein Gerät oder eine Anwendung ohne eigenen Browser fordert Zugriff auf Ihr Konto an. ',
                'Geben Sie den Code ein, der auf dem Gerät angezeigt wird, und prüfen Sie die ',
                'angeforderten Berechtigungen, bevor Sie zustimmen.<br/><br/>',
                'Bestätigen Sie die Anfrage nur, wenn Sie den Vorgang auf dem Gerät selbst gestartet haben ',
                'und der angezeigte Code übereinstimmt. ',
                'Die genaue Gültigkeitsdauer ist bei jeder Berechtigung angegeben.<br/><br/>',
                '<b>Wenn Sie keinen Code angefordert haben, lehnen Sie die Anfrage ab.</b>',
                '</div>'
            ];
            return data.join('');
        }
    },
    stores: {
        items: {

            type: 'json',
            fields: ['type', 'id', 'name', 'description', 'duration'],
            data: [
            ]
        }
    }
});
