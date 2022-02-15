<?php
script('sciencemesh', 'invitations');
style('sciencemesh', 'style');
script("sciencemesh", "vendor/simplyedit/simply-edit");
script("sciencemesh", "vendor/simplyedit/simply.everything");
?>
<div id="app">
	<div id="app-navigation">
		<?php print_unescaped($this->inc('navigation/index')); ?>
	</div>
	<div id="app-content">
		
		<div href="#" class="app-content-list-item" id="elem">
			<div class="app-content-list-item-icon" style="background-color: rgb(31, 72, 96);">+</div>
			<div class="app-content-list-item-line-one">Generate a new token</div>
			<div class="app-content-list-item-line-two">Tokens are valid for 24 hours</div>
			<div class="app-content-list-item-menu">
				<div class="icon-add"></div>
			</div>
		</div>
		<div id="show_result">
		</div>
	</div>
</div>
