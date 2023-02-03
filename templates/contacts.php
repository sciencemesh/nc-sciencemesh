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
				<small class="contact-title-desc">* List of your ScienceMesh contacts.</small>
				<a class="icon-public" style="padding-left:34px" href="generate">Create invite link
					<button id="token-generator" class="icon-add-white app-content-list-item-icon" style="background-color: rgb(31, 72, 96);"></button>
				</a>
				<table class=" contacts-table" id="contact-table">
					<thead>
						<tr>
							<td colspan="2">Name</td>
							<td>Open Cloud Mesh Address</td>
						</tr>
					</thead>
					<tbody id="show_result" >

					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
