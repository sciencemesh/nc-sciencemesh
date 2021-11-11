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
				<div class="app-content-list">
					<div href="#" class="app-content-list-item">
						<!-- div class="app-content-list-item-star icon-starred"></div -->
						<div class="app-content-list-item-icon" style="background-color: rgb(151, 72, 96);">F</div>
						<div class="app-content-list-item-line-one">f9a25050-a0cf-4717-badb-b3574e3c0963</div>
						<div class="app-content-list-item-menu">
							<div class="icon-clippy"></div>
						</div>
						<span class="app-content-list-item-details">8 hours left</span>
						<div class="app-content-list-item-line-two">Copy to clipboard</div>
					</div>
					<div href="#" class="app-content-list-item">
						<!-- div class="app-content-list-item-star icon-starred"></div -->
						<div class="app-content-list-item-icon" style="background-color: rgb(31, 72, 96);">+</div>
						<div class="app-content-list-item-line-one">Generate a new token</div>
						<div class="app-content-list-item-line-two">Tokens are valid for 24 hours</div>
						<div class="app-content-list-item-menu">
							<div class="icon-add"></div>
						</div>
					</div>
				</div>
				<div class="app-content-detail">
					<div class="section">
						<p>To invite someone to collaborate on ScienceMesh, generate a new invite token and send it to them.</p>
						<p>If you have received an invitation, you can enter that in 'Contacts' to confirm the collaboration.</p>
					</div>
				</div>
		</div>
	</div>
</div>
