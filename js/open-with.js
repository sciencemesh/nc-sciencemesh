$(document).ready(function () {
    var actionsOpenWith = {
        init: function () {
            var self = this;
            OCA.Files.fileActions.registerAction({
                name: 'openwith-codimd',
                displayName: 'Open with CodiMD',
                mime: 'text/plain',
                permissions: OC.PERMISSION_READ,
                type: OCA.Files.FileActions.TYPE_DROPDOWN,
                iconClass: 'icon-open-with',
                actionHandler: function (filename, context) {
                    alert("open with CodiMD Clicked!")
                }
            });

            OCA.Files.fileActions.registerAction({
                name: 'openwith-source-codimd',
                displayName:  'Open at source with CodiMD',
                mime: 'text/plain',
                permissions: OC.PERMISSION_READ,
                type: OCA.Files.FileActions.TYPE_DROPDOWN,
                iconClass: 'icon-open-with',
                actionHandler: function (filename, context) {
                    alert("open at source with CodiMD Clicked!")
                }
            });
        },
    }
    actionsOpenWith.init();
});

