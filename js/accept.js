document.addEventListener("DOMContentLoaded", function(event) {
    document.getElementById('accept-button').onclick = function () {
        console.log('clicked');
        var full = document.getElementById('token-input').value
        var parts = full.split('@')
        var token = parts[0]
        var providerDomain = parts[1]
        var data = 'providerDomain=' + encodeURIComponent(providerDomain) +
            '&token=' + encodeURIComponent(token);
  
        var baseUrl = OC.generateUrl('/apps/sciencemesh');
        $.ajax({
            url: baseUrl + '/contacts/accept',
            type: 'POST',
            contentType: 'application/x-www-form-urlencoded',
            data: data
        }).done(function (response) {
            var element = document.getElementById("test_error");
            $("#test_error").show();
            if (response === '' || response === false) {
                element.innerHTML = 'Something goes wrong: No Sciencemesh Connection';
                jQuery(element).addClass('text-error');
            } else if(response.startsWith('Accepted invite from')){
                document.getElementById('token').value = '';
                element.innerHTML = 'Invitation has successfully accepted!';
                jQuery(element).addClass('text-error');
            } else {
                let result = JSON.parse(response);
                if (result.hasOwnProperty('message')) {
                    let test = result.message;
                    element.innerHTML = test || 'Success';
                    jQuery(element).addClass('text-error');
                    $('#provider').hide();
                    $('#display_name').hide();
                } else {
                    console.log(result)
                }
            }
            
            
            setTimeout(() => {$("#test_error").hide()},5000);
            
        }).fail(function (response, code) {
            console.log(response)
            //alert('The token is invalid')
        });
    };
    function checkQueryString() {
        const params = new Proxy(new URLSearchParams(window.location.search), {
          get: (searchParams, prop) => searchParams.get(prop),
        });
        if ((typeof params.token == 'string') && (params.token.length > 0) &&
          (typeof params.providerDomain == 'string') && (params.providerDomain.length > 0)) {
            document.getElementById('token-input').value = `${params.token}@${params.providerDomain}`;
            document.getElementById('providerDomain').innerHTML = params.providerDomain;
            $("#dialog").show();
        } else {
            console.log("checkQueryString fail!");
            $("#test_error").addClass('text-error');
            $("#test_error").show();
            $("#test_error").html('No token in the URL');
        }
    }
    checkQueryString();
  });
