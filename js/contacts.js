//Everything will be for working with contacts
var baseUrl = OC.generateUrl('/apps/sciencemesh');
$('#test_error').hide(); 
$.ajax({
    url: baseUrl + '/contacts/users',
    type: 'GET',
    contentType: 'application/json',
}).done(function (response) {
    if(response == '' || response === false) {
        var element = document.getElementById("show_result");
        element.innerHTML= `
                            <tr class="app-content-list-item">
                                <th style="border-radius:100%">
                                    No Sciencemesh Connection
                                </th>
                            </tr>`;
        $('#show_result').show(); 
    } else {
    let token = JSON.parse(response);

    for(tokenData in token) {
        if(token.hasOwnProperty(tokenData)) {
            console.log(tokenData);
            if(tokenData === 'accepted_users') {
                let accepted_users = token.accepted_users
                var result = ''; 
                for(accept in accepted_users) {
                    const displayName = accepted_users[accept].display_name;
                    const username = accepted_users[accept].id.opaque_id;
                    const idp = accepted_users[accept].id.idp;
                    const provider = new URL(idp).host;
                    result += `
                            <tr>
                                <td style="border-radius:100%">
                                    <p class="icon-contacts-dark contacts-profile-img"></p>
                                </td>
                                <td class="app-content-list-item-line-one contact-item">
                                    <p class="displayname">${displayName}</p>
                                </td>  
                                <td>
                                    <p class="username-provider">${username}</p>
                                </td>
                            </tr>
                    `;
                }
                
                var element = document.getElementById("show_result");
                element.innerHTML = result;
            
                $('#show_result').show();
            }else{
                const result = `
                        <tr>
                            <td>
                                <p class="username-provider">There are no contacts!</p>
                            </td>
                        </tr>`;                  
                var element = document.getElementById("show_result");
                element.innerHTML = result;
                $('#show_result').show();

            }
        } 
    }
}
}).fail(function (response, code) {
    console.log(response)
    //alert('The token is invalid')
});
document.getElementById('token-generator').onclick = function () {
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
            var element = document.getElementById("invitation-details");
            element.innerHTML = `<div class="token-generator"><i class="fa-thin fa-square-check"></i><h4 class="message-token">New Token Generated!</h4><input type="text" value="${response}" onclick="get_token()" readonly name="meshtoken" class="generated-token-link"><span class="icon-share svg" onclick="get_token()"></span><a class="token-btn-verification" href="${response}">View Token</a></div>`;
            $('#test').show();
        }
    }).fail(function (response, code) {
        alert('The token is invalid')
    });
}
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