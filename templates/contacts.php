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
