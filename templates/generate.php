<?php
script('sciencemesh', 'generate');
style('sciencemesh', 'style');
?>
<div id="app">
	<div id="app-navigation">
		<?php print_unescaped($this->inc('navigation/index')); ?>
	</div>

	<div id="app-content">
		<div id="app-content-wrapper" class="viewcontainer">
			<div class="app-content-list">
				<div href="#" class="app-content-list-item" id="elem">
					<div class="app-content-list-item-icon" style="background-color: rgb(31, 72, 96);">+</div>
					<div class="app-content-list-item-line-one">Generate a new token</div>
					<div class="app-content-list-item-line-two">Tokens are valid for 24 hours</div>
					<div class="app-content-list-item-menu">
						<div class="icon-add"></div>
					</div>
				</div>
				<div id="test" href="#" class="app-content-list-item">
					<div class="app-content-list-item-line-one" id="show_result" style="font-size:40%;"></div>
				</div>
			</div>
		</div>
	</div>
</div>

