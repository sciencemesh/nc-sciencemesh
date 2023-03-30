<?php
script('sciencemesh', 'contacts');
style('sciencemesh', 'style');
?>
<div id="app">
		<div id="app-content">
			<div id="app-content-wrapper" class="viewcontainer">
				<div class="app-content-list"  style="width: 100%;overflow-x:hidden">
					<div id="contact-toolbar" class="files-controls">
						<button id="token-generator" class=" app-content-list-item-icon">
							<span class="svg icon-public button-icon"></span>Invite ScienceMesh user
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