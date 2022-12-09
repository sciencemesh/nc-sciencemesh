//Everything will be for working with contacts
var baseUrl = OC.generateUrl('/apps/sciencemesh');
$('#test').hide(); 
$.ajax({
    url: baseUrl + '/contacts/users',
    type: 'GET',
    contentType: 'application/json',
}).done(function (response) {
    if(response === '' || response === false) {
        var element = document.getElementById("test_error");
        element.innerHTML= 'No connection with reva';
        //$('#test').show(); 
    } else {
    let token = JSON.parse(response);
    for(tokenData in token) {
        if(token.hasOwnProperty(tokenData)) {
            console.log(tokenData);
            if(tokenData === 'accepted_users') {
                let accepted_users = token.accepted_users
                    for(accept in accepted_users) {
                        const displayName = accepted_users[accept].display_name;
                        const username = accepted_users[accept].id.opaque_id;
                        const idp = accepted_users[accept].id.idp;
                        const provider = new URL(idp).host;
                        const result = `
                                <div href="#" class="app-content-list-item profile-item">
                                    <div class="app-content-list-item-icon" style="">
                                        <img src="https://cdn-icons-png.flaticon.com/512/16/16363.png">
                                    </div>
                                    <div class="app-content-list-item-line-one" id="show_result" >
                                        <p class="displayname">${displayName}</p><p class="username-provider">${username}@${provider}</p>
                                    </div>  
                                </div>`;                  
                        var element = document.getElementById("test");
                        element.innerHTML = result;
                    }

                $('#test').show();
            }else{
                const result = `
                        <div href="#" class="app-content-list-item profile-item" >
                            <div class="app-content-list-item-icon" style="">
                            </div> 
                            <div class="app-content-list-item-line-one" id="show_result" >
                                <p class="username-provider">There're no contacts!</p>
                            </div>  
                        </div>`;                  
                var element = document.getElementById("test");
                element.innerHTML = result;
                $('#test').show();

            }
        } 
    }
}
}).fail(function (response, code) {
    console.log(response)
    //alert('The token is invalid')
});
document.getElementById('elem').onclick = function () { 
    console.log('clicked');
    var parts = document.getElementById('token').value.split('@');
    var token = parts[0];
    var providerDomain = parts[1];

    var data = 'providerDomain=' + encodeURIComponent(providerDomain) +
  '&token=' + encodeURIComponent(token);

    var baseUrl = OC.generateUrl('/apps/sciencemesh');
    $.ajax({
        url: baseUrl + '/contacts/accept',
        type: 'POST',
        contentType: 'application/x-www-form-urlencoded',
        data: data
    }).done(function (response) {
      
       if(response === '' || response === false) {
            var element = document.getElementById("test_error");
            element.innerHTML= 'No connection with reva';
        } else {
            let result = JSON.parse(response);
            if(result.hasOwnProperty('message')) {
                let test = result.message
                var element = document.getElementById("test_error");
                element.innerHTML=test;

                $('#provider').hide();
                $('#display_name').hide();
            } else {
                console.log(result)
            }
        }
     
    }).fail(function (response, code) {
        console.log(response)
        //alert('The token is invalid')
    });
};
