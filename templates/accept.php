<?php
script('sciencemesh', 'accept');
style('sciencemesh', 'style');
?>
<div id="app">
	<div id="app-navigation">
		<?php print_unescaped($this->inc('navigation/index')); ?>
	</div>
	<div id="app-content">
		
		<div id="app-content-wrapper" class="viewcontainer">
				<div class="app-content-list">
				<div href="#" class="app-content-list-item">
				<div class="app-content-list-item-line-one token-holder" style="display:none" id="dialog">
					<input id="token-input" type="hidden">
					<div>Do you want to accept this invitation from <span id="providerDomain">(unknown)</span>?</div>
					<input type="button" class="check-token" id="accept-button" value="Accept">
					<div>The inviter will be able to start sharing files and folders with you.</div>
					<!-- <div><a href="contacts">Decline</a></div> -->
				</div>
				<div id="test_error" style="display:none"></div>
				<div class="app-content-detail">
				</div>
		</div>
	</div>
</div>