<?php
script('sciencemesh', 'generate');
style('sciencemesh', 'style');
?>
<div id="app">
	<div id="app-content">
		<div id="app-content-wrapper" class="viewcontainer">
			<div class="app-content-list" style="display: flex;" id="elem">
				<div href="#" class="app-content-list-item token-generator">
					<div class="app-content-list-item-icon" style="background-color: rgb(31, 72, 96);">+</div>
				</div>
				<div>
					<div class="app-content-list-item-line-one">Generate a new token</div>
					<div class="app-content-list-item-line-two">Tokens are valid for 24 hours</div>					
				</div>
			</div>

			<div id="test" href="#" class="app-content-list-item-token">
				<div class="app-content-list-item-token-div" id="show_result"></div>
			</div>
		</div> 
	</div>
</div>
