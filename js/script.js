$('#test').hide();
document.getElementById('elem').onclick = function () { 
    var baseUrl = OC.generateUrl('/apps/sciencemesh');
    $.ajax({
        url: baseUrl + '/invitations/generate',
        type: 'GET',
        contentType: 'application/json',
        //data: JSON.stringify(note)
    }).done(function (response) {
        let token = JSON.parse(response);
        let invite_token = token.invite_token.token
        var element = document.getElementById("show_result");
        element.innerHTML=invite_token;
        $('#test').show();

        let timestamp = secondsToDhms(token.invite_token.expiration.seconds);
        var element_timestamp = document.getElementById("timestamp_invalid");
        element_timestamp.innerHTML=timestamp;
        $('#timestamp_invalid').show();

    }).fail(function (response, code) {
        alert('The token is invalid')
    });
};
function secondsToDhms(seconds) {
    seconds = Number(seconds);
    var d = Math.floor(seconds / (3600*24));
    var h = Math.floor(seconds % (3600*24) / 3600);
    var m = Math.floor(seconds % 3600 / 60);
    var s = Math.floor(seconds % 60);
    
    var dDisplay = d > 0 ? d + (d == 1 ? " day, " : " days, ") : "";
    var hDisplay = h > 0 ? h + (h == 1 ? " hour, " : " hours, ") : "";
    var mDisplay = m > 0 ? m + (m == 1 ? " minute, " : " minutes, ") : "";
    var sDisplay = s > 0 ? s + (s == 1 ? " second" : " seconds") : "";
    return dDisplay + hDisplay + mDisplay + sDisplay;
}
