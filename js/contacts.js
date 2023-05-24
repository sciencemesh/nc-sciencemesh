document.addEventListener("DOMContentLoaded", function(event) {
    //Everything will be for working with contacts
    var baseUrl = OC.generateUrl('/apps/sciencemesh');
    $('#show_result').hide(); 
    $.ajax({
        url: baseUrl + '/contacts/users',
        type: 'GET',
        contentType: 'application/json',
    }).done(function (response) {
        if(response === '' || response === false) {
            var element = document.getElementById("show_result");
            element.innerHTML= `
                                <tr class="app-content-list-item">
                                    <th style="border-radius:100%">
                                        No Sciencemesh Connection
                                    </th>
                                </tr>`;
            $('#show_result').show(); 
        } else {
        let acceptedUsers = JSON.parse(response);
        if (acceptedUsers.length == 0) {
            const result = `
            <tr colspan="3" href="#" class="app-content-list-item" >
                <td style="width:40% !important">
                    <p class="username-provider">There are no contacts!</p>
                </td>
            </tr>`;               
            var element = document.getElementById("show_result");
            element.innerHTML = result;
            $('#show_result').show();
        } else {
            let result = '';
            for(i in acceptedUsers) {
                const displayName = acceptedUsers[i].display_name;
                const username = acceptedUsers[i].id.opaque_id;
                const idp = acceptedUsers[i].id.idp;
                const provider =  (idp.startsWith("http") ? new URL(idp).host : idp);
                result += `
                <tr class="app-content-list-item">
                    <td style="border-radius:100%">
                        <p class="icon-contacts-dark contacts-profile-img"></p>
                    </td>
                    <td class="app-content-list-item-line-one contact-item">
                        <p class="displayname">${displayName}</p>
                    </td>  
                    <td>
                        <p class="username-provider">${username}@${provider}</p>
                    </td>
                </tr>
                `;
            }
            var element = document.getElementById("show_result");
            element.innerHTML = result;
            
            $('#show_result').show();
        }
    }
    }).fail(function (response, code) {
        console.log(response)
        //alert('The token is invalid')
    });
    document.getElementById('token-generator').onclick = function () {
        var baseUrl = OC.generateUrl('/apps/sciencemesh');
        var recipient = document.getElementById("recipient");
        $.ajax({
            url: baseUrl + '/invitations/generate',
            type: 'GET',
            contentType: 'application/json',
            data: { 
                email: recipient.value,
            },
        }).done(function (response) {
            if (response === '' || response === false) {
                var element = document.getElementById("invitation-details");
                element.innerHTML = 'No Sciencemesh Connection';
            } else {
                var element = document.getElementById("invitation-details");
                element.innerHTML = `<div class="token-generator"><i class="fa-thin fa-square-check"></i><input type="text" value="${response}" readonly name="meshtoken" class="generated-token-link"><span class="icon-clippy svg" onclick="get_token()" id="share-token-btn"></span><span class="icon-mail svg" id="share-token-btn-email"></span><h4 class="message-token" style="padding:8px 0;">New Token Generated!</h4></div>`;
                $('#test').show();
                var button = document.querySelector("#share-token-btn");
                button.addEventListener("click", function() {
                    copyToClipboard();
                });
                
                var buttonEmail = document.querySelector("#share-token-btn-email");
                buttonEmail.addEventListener("click", function() {
                    OC.dialogs.prompt(
                        '',
                        'Share Token',
                        function (result,input) {
                            var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                            if (input !== null) {
                                if(emailPattern.test(input)){
                                    $.ajax({
                                        url: 'invitations/emailsend',
                                        method: 'POST',
                                        data: { 
                                            email: input,
                                            token: document.querySelector("input[name='meshtoken']").value
                                        },
                                        success: function (response) {
                                            if(response){
                                                alert('Email sent successfully!');
                                            }else{
                                                alert('Email sent failed! Please check the configuration.');
                                            }
                                        },
                                        error: function (xhr, status, error) {
                                            alert('Email sent failed! Please check the configuration.');
                                        }
                                      });
                                }else{
                                    alert('Please input Email correctly!')
                                }
                            // var email = result.trim();

                            }else{
                                alert('Please input Email address!')
                            }
                        },
                        '',
                        'Please input the recipient email',
                        '',
                        ''
                      );

                });
            }
        }).fail(function (response, code) {
            alert('The token is invalid')
        });
    }
    
    function copyToClipboard() {
        var input = document.querySelector("input[name='meshtoken']");
        input.select();
        document.execCommand("copy");
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