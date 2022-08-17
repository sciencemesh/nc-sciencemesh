<?php
script('sciencemesh', 'contacts');
style('sciencemesh', 'style');
?>
<div id="app">
	<div id="app-navigation">
		<?php print_unescaped($this->inc('navigation/index')); ?>
	</div>
	<div id="app-content"> 
		<div id="app-content-wrapper" class="viewcontainer">
			<div class="app-content-list">
			<div href="#" class="app-content-list-item" id="test">
					<div class="app-content-list-item-icon" style="background-color: rgb(151, 72, 96); font-size:200%; padding: 6px;" id="message"></div>
					<div class="app-content-list-item-line-one" id="show_result" style="font-size: 200%;">	
				</div>
			</div>
		</div>
	</div>
</div>
