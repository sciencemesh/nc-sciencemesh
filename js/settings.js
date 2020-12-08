(function ($, OC) {

    $(document).ready(function () {
        OCA.ScienceMesh = _.extend({
                AppName: "sciencemesh"
            }, OCA.ScienceMesh)


        $("#sciencemeshSave").click(function () {
            $(".section-sciencemesh").addClass("icon-loading");
            var iopUrl = $("#sciencemeshIopUrl").val().trim();

            if (!iopUrl.length) {
                $("#sciencemeshApiKey").val("");
            }
            
	    var apiKey = $("#sciencemeshApiKey").val().trim();

            $.ajax({
                method: "PUT",
                url: OC.generateUrl("apps/" + OCA.ScienceMesh.AppName + "/ajax/settings/address"),
                data: {
                    iopurl: iopUrl,
                    apikey: apiKey,
                },
                success: function onSuccess(response) {
                    $(".section-sciencemesh").removeClass("icon-loading");
                    if (response && (response.iopUrl != null)) {
                        $("#sciencemeshIopUrl").val(response.iopurl);
                        $("#sciencemeshApiKey").val(response.apikey);

                        var message =
                            response.error
                                ? (t(OCA.ScienceMesh.AppName, "Error when trying to connect") + " (" + response.error + ")")
                                : t(OCA.ScienceMesh.AppName, "Settings have been successfully updated");

                        var versionMessage = response.version ? (" (" + t(OCA.ScienceMesh.AppName, "version") + " " + response.version + ")") : "";

                        OC.Notification.show(message + versionMessage, {
                            type: response.error ? "error" : null,
                            timeout: 3
                        });
                    }
                }
            });
        });

        $(".section-sciencemesh input").keypress(function (e) {
            var code = e.keyCode || e.which;
            if (code === 13) {
                $("#sciencemeshSave").click();
            }
        });
    });

})(jQuery, OC);
