$(document).ready(function() {
	$('#sciencemesh-api-key').change(function(el) {
		OCP.AppConfig.setValue('sciencemesh','apiKey',this.value);
	});
	$('#sciencemesh-site-name').change(function(el) {
		OCP.AppConfig.setValue('sciencemesh','siteName',this.value);
	});
	$('#sciencemesh-site-url').change(function(el) {
		OCP.AppConfig.setValue('sciencemesh','siteUrl',this.value);
	});
	$('#sciencemesh-site-id').change(function(el) {
		OCP.AppConfig.setValue('sciencemesh','siteId',this.value);
	});
	$('#sciencemesh-country').change(function(el) {
		OCP.AppConfig.setValue('sciencemesh','country',this.value);
	});
	$('#sciencemesh-iop-url').change(function(el) {
		OCP.AppConfig.setValue('sciencemesh','iopUrl',this.value);
	});
	$('#sciencemesh-num-users').change(function(el) {
		OCP.AppConfig.setValue('sciencemesh','numUsers',this.value);
	});
	$('#sciencemesh-num-files').change(function(el) {
		OCP.AppConfig.setValue('sciencemesh','numFiles',this.value);
	});
	$('#sciencemesh-num-storage').change(function(el) {
		OCP.AppConfig.setValue('sciencemesh','numStorage',this.value);
	});
});