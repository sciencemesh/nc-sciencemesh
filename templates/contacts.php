<?php
script('sciencemesh', 'contacts');
style('sciencemesh', 'style');
?>
<div id="app">
		<div id="app-content">
			<div id="app-content-wrapper" class="viewcontainer">
				<div class="app-content-list">
					<div id="contact-toolbar" class="files-controls">
						<button id="token-generator" class=" app-content-list-item-icon">
							<span class="icon-public button-icon"></span>
							<span>Invite ScienceMesh user</span>
						</button>
						<input type="email" name="recipient" id="recipient" placeholder="Please input the recipient email...">
						<div id="invitation-details"></div>
					</div>
					<small class="contact-title-desc">* List of your ScienceMesh contacts.</small>
					<input type="search" id="contact-search-input" placeholder="Search...">
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