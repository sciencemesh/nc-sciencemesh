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
					<div id="contact-toolbar" class="files-controls">
						<button id="token-generator" class=" app-content-list-item-icon">
							<span class="icon-public-white button-icon"></span>Invite ScienceMesh user
						</button>
						<div id="invitation-details"></div>
					</div>
					<small class="contact-title-desc">* List of your ScienceMesh contacts.</small>
					<table class=" contacts-table" id="contact-table">
						<thead>
							<tr>
								<td colspan="2">Name</td>
								<td>Open Cloud Mesh Address</td>
							</tr>
						</thead>
						<tbody id="show_result">
						</tbody>
				</table>
			</div>
		</div>
	</div>
</div>