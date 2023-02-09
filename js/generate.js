//I just seperate logic my module I mean if you are just use one file JS when you are go to another file it will
//give some error you need for every PHP file seperate JS logic
document.addEventListener("DOMContentLoaded", function(event) {
    $('#test').hide();
    document.getElementById('elem').onclick = function () {
        var baseUrl = OC.generateUrl('/apps/sciencemesh');
        $.ajax({
            url: baseUrl + '/invitations/generate',
            type: 'GET',
            contentType: 'application/json',
            //data: JSON.stringify(note)
        }).done(function (response) {
            if (response === '' || response === false) {
                var element = document.getElementById("test_1");
                element.innerHTML = 'No Sciencemesh Connection';
            } else {
                var element = document.getElementById("show_result");
                element.innerHTML = `<div class="token-generator"><i class="fa-thin fa-square-check"></i><h4 class="message-token">New Token Generated!</h4><input type="text" value="${response}" onclick="get_token()" readonly name="meshtoken" class="generated-token-link"><span class="icon-share svg" onclick="get_token()"></span><a class="token-btn-verification" href="${response}">View Token</a></div>`;
                $('#test').show();
            }
        }).fail(function (response, code) {
            alert('The token is invalid')
        });
    };
    function get_token(){
        var copyText = document.getElementsByName("meshtoken");
    
        // Select the text field
        copyText.select();
        copyText.setSelectionRange(0, 99999); // For mobile devices
      
         // Copy the text inside the text field
        navigator.clipboard.writeText(copyText.value);
    }
    function secondsToDhms(seconds) {
        seconds = Number(seconds);
        var d = Math.floor(seconds / (3600 * 24));
        var h = Math.floor(seconds % (3600 * 24) / 3600);
        var m = Math.floor(seconds % 3600 / 60);
        var s = Math.floor(seconds % 60);

        var dDisplay = d > 0 ? d + (d == 1 ? " day, " : " days, ") : "";
        var hDisplay = h > 0 ? h + (h == 1 ? " hour, " : " hours, ") : "";
        var mDisplay = m > 0 ? m + (m == 1 ? " minute, " : " minutes, ") : "";
        var sDisplay = s > 0 ? s + (s == 1 ? " second" : " seconds") : "";
        return dDisplay + hDisplay + mDisplay + sDisplay;
    }
});