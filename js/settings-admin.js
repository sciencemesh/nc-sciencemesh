$(document).ready(function() {
	$('#sciencemesh-private-key').change(function(el) {
		OCP.AppConfig.setValue('sciencemesh','privateKey',this.value);
	});

	$('#sciencemesh-encryption-key').change(function(el) {
		OCP.AppConfig.setValue('sciencemesh','encryptionKey',this.value);
	});

});