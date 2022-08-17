document.addEventListener("DOMContentLoaded", function(event) {
//Everything will be for working with contacts
var baseUrl = OC.generateUrl('/apps/sciencemesh');
$('#test').hide(); 
$.ajax({
    url: baseUrl + '/contacts/users',
    type: 'GET',
    contentType: 'application/json',
}).done(function (response) {
    var headerElement = document.getElementById("message");
    let token = JSON.parse(response);
    if(response === '' || response === false || token['accepted_users'] === undefined) {
        headerElement.innerHTML= 'No Reva Contact';
        //$('#test').show(); 
    } else {
        headerElement.innerHTML= 'Reva Contacts';

    for(tokenData in token) {
        if(token.hasOwnProperty(tokenData)) {
            if(tokenData === 'accepted_users') {
                let accepted_users = token.accepted_users
                console.log('Accepted users', accepted_users)
                for(accept in accepted_users) {
                    const displayName = accepted_users[accept].display_name;
                    const username = accepted_users[accept].id.opaque_id;
                    const idp = accepted_users[accept].id.idp;
                    const provider = new URL(idp).host;
                    const div = document.createElement("div");
                    div.style = "padding:6px";
                    div.id = `${username}@${provider}`;
                    div.textContent = `${displayName} (${username}@${provider})`;
                    var element = document.getElementById("show_result");
                    element.appendChild(div);
                    $('#test').show();
                }
            }
        } 
    }
}
}).fail(function (response, code) {
    console.log(response)
    //alert('The token is invalid')
});
});
