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
						<div class="app-content-list-item-line-one token-holder" id="dialog" style="display:none">
							<input id="token-input" type="hidden">
							<div>Do you want to accept this invitation from <span id="providerDomain">(unknown)</span>?</div>
							<input type="button" class="check-token" id="elem" value="Accept Token">
							<div>The inviter will be able to start sharing files and collaborating with you.</div>
							<div><a href="contacts">Decline</a></div>
						</div>
					</div>
					<div id="loading">(loading...)</div>
				</div>
				<div class="app-content-detail">
					<div class="section">
						<!--<p>To invite someone to collaborate on ScienceMesh, generate a new invite token and send it to them.</p>
						<p>If you have received an invitation, you can enter that in 'Contacts' to confirm the collaboration.</p>-->
						<p id="test_error" style="display:none"></p>
					</div>
				</div>
		</div>
	</div>
</div>