Ext.define('Tualo.routes.oauth2.Consent', {
    statics: {
        load: async function () {
            return [
                {
                    name: 'Consent',
                    path: '#oauth2-consent'
                }
            ]
        }
    },
    url: 'oauth2-consent(/:{request_id})',
    handler: {
        action: function (values) {


            Ext.getApplication().addView('Tualo.oauth2.lazy.ConsentPanel', {
                request_id: values.request_id
            });


        },
        before: function (values, action) {
            action.resume();
        }

    }
});