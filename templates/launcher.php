<?php
script('sciencemesh', 'script');
style('sciencemesh', 'style');
script("sciencemesh", "vendor/simplyedit/simply-edit");
script("sciencemesh", "vendor/simplyedit/simply.everything");
?>

<div id="app">
	<div id="app-navigation">
		<?php print_unescaped($this->inc('navigation/index')); ?>
	</div>

	<div id="app-content">
		<div id="app-content-wrapper" class="viewcontainer">
			<main class="sciencemesh-container sciencemesh-launcher">
				Launcher
			</main>
		</div>
	</div>
</div>

