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