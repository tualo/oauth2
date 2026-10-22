Ext.define('Tualo.oauth2.Loader', {
    singleton: true,

    constructor: function () {
        Ext.Loader.setPath('Tualo.oauth2.lazy', './jsoauth2');
    }
});
