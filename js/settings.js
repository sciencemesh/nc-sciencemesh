(function ($, OC) {

    $(document).ready(function () {
        OCA.ScienceMesh = _.extend({
            AppName: "sciencemesh"
        }, OCA.ScienceMesh)


        $("#sciencemeshSave").click(function () {
            $(".section-sciencemesh").addClass("icon-loading");
            var apiKey = $("#sciencemeshAPIKey").val().trim();
            var sitename = $("#sciencemeshSitename").val().trim();
            var siteurl = $("#sciencemeshSiteurl").val().trim();
            var countryCode = $("#sciencemeshCountryCode").val().trim();
            var iopurl = $("#sciencemeshIopUrl").val().trim();
            var numUsers = $("#sciencemeshNumusers").val().trim();
            var numFiles = $("#sciencemeshNumfiles").val().trim();
            var numStorage = $("#sciencemeshNumstorage").val().trim();

            $.ajax({
                method: "PUT",
                url: OC.generateUrl("apps/" + OCA.ScienceMesh.AppName + "/ajax/settings/address"),
                data: {
                    apikey: apiKey,
                    sitename: sitename,
                    siteurl: siteurl,
                    country: countryCode,
                    iopurl: iopurl,
                    numusers: numUsers,
                    numfiles: numFiles,
                    numstorage: numStorage
                },
                success: function onSuccess(response) {
                    $(".section-sciencemesh").removeClass("icon-loading");
                    if (response) {
                        var message =
                            response.error
                                ? (t(OCA.ScienceMesh.AppName, "Error when trying to update the settings") + " (" + response.error + ")")
                                : t(OCA.ScienceMesh.AppName, "Settings have been successfully updated");

                        var versionMessage = response.version ? (" (" + t(OCA.ScienceMesh.AppName, "version") + " " + response.version + ")") : "";

                        OC.Notification.show(message + versionMessage, {
                            type: response.error ? "error" : "info",
                            timeout: 10
                        });
                    }
                }
            });
        });

        $(".section-sciencemesh input").keypress(function (e) {
            var code = e.keyCode || e.which;
            if (code === 10 || code === 13) {
                $("#sciencemeshSave").click();
            }
        });

        $('#sciencemesh_setting_submit_btn').on('click',function(){
            var sciencemesh_iop_url = $('#sciencemesh_iop_url').val().trim();
            var sciencemesh_shared_secret = $("#sciencemesh_shared_secret").val().trim();

            $(".section-sciencemesh").addClass("icon-loading");
            var baseUrl = OC.generateUrl('/apps/sciencemesh');
    

            $.ajax({
                method: "GET",
                url: baseUrl + "/ajax/sciencemesh_settings/save",
                contentType: 'application/json',
                data: {
                    sciencemesh_shared_secret: sciencemesh_shared_secret,
                    sciencemesh_iop_url: sciencemesh_iop_url
                },
                success: function onSuccess(response) {
                    $(".section-sciencemesh").removeClass("icon-loading");
                    if (response) {
                        var message =
                            response.error
                                ? (t(OCA.ScienceMesh.AppName, "Error when trying to update the settings") + " (" + response.error + ")")
                                : t(OCA.ScienceMesh.AppName, "Settings have been successfully updated");

                        var versionMessage = response.version ? (" (" + t(OCA.ScienceMesh.AppName, "version") + " " + response.version + ")") : "";

                        OC.Notification.show(message + versionMessage, {
                            type: response.error ? "error" : "info",
                            timeout: 10
                        });
                    }
                }
            });

        });

    $('#check_connection_sciencemesh_iop_url').on('click',function(){
        var sciencemesh_iop_url = $("#sciencemesh_iop_url").val().trim();

        $(".section-sciencemesh").addClass("icon-loading");
        var baseUrl = OC.generateUrl('/apps/sciencemesh');

        $.ajax({
            method: "GET",
            url: baseUrl + "/ajax/check_connection_settings",
            contentType: 'application/json',
            data: {
                sciencemesh_iop_url: sciencemesh_iop_url
            },
            success: function onSuccess(res) {
                $(".section-sciencemesh").removeClass("icon-loading");
                if(res){
                    if (res.enabled) {
                        var message = t(OCA.ScienceMesh.AppName, "Connection is available");
                    }else{
                        var message = t(OCA.ScienceMesh.AppName, "Connection is not available");
                    }

                    OC.Notification.show(message, {
                        type: "error",
                        timeout: 10
                    });

                }else{
                    var message = t(OCA.ScienceMesh.AppName, "Connection is not available");
                    OC.Notification.show(message, {
                        type: "error",
                        timeout: 10
                    });
                }

            }
        });
    });
});

})(jQuery, OC);
