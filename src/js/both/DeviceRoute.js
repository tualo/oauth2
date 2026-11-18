Ext.define('Tualo.routes.oauth2.Device', {
    statics: {
        load: async function () {
            return [
                {
                    name: 'Device',
                    path: '#oauth2-device'
                }
            ]
        }
    },
    url: 'oauth2-device(/:{user_code})',
    handler: {
        action: function (values) {

            Ext.getApplication().addView('Tualo.oauth2.lazy.DevicePanel', {
                user_code: values.user_code
            });

        },
        before: function (values, action) {
            action.resume();
        }

    }
});
