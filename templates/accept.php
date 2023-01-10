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
						<div class="app-content-list-item-line-one token-holder" >
							<input id="token-input" type="text" placeholder="token@sciencemesh.site">
							<input type="button" class="check-token" id="elem" value="Accept Token">
						</div>
					</div>
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