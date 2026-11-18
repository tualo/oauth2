Ext.define('Tualo.oauth2.lazy.models.ConsentPanel', {
    extend: 'Ext.app.ViewModel',
    alias: 'viewmodel.tualo_oauth2_consent_panel',
    data: {
        request_id: null
    },
    formulas: {
        title: function (get) {
            return 'Authentifizierungsanfrage';
        },
        mainDescription: function (get) {
            let data = [
                '<div style="margin-bottom: 10px; padding: 10px; border: 1px solid #ccc; border-radius: 5px; min-height: 50px;max-width: 100%; box-sizing: border-box;" class="item-wrap">',
                '<b>Wichtig: Prüfen Sie diese Anfrage sorgfältig!</b><br/>',
                'Eine Anwendung fordert Zugriff auf Ihr Konto an. Erteilen Sie die Freigabe nur, ',
                'wenn Sie die Anwendung kennen, ihr vertrauen und die Anfrage selbst ausgelöst haben.<br/><br/>',
                'Die oben aufgeführten Berechtigungen entstehen erst mit Ihrer Zustimmung und sind zeitlich begrenzt: ',
                'Ohne die Berechtigung „Dauerhafter Zugriff“ endet der Zugriff automatisch nach kurzer Zeit. ',
                'Mit „Dauerhafter Zugriff“ kann die Anwendung den Zugriff selbstständig immer wieder erneuern – ',
                'er bleibt bestehen, bis Sie ihn widerrufen. ',
                'Die genaue Gültigkeitsdauer ist bei jeder Berechtigung angegeben.<br/><br/>',
                '<b>Wenn Sie diese Anfrage nicht erwartet haben, lehnen Sie sie ab.</b>',
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