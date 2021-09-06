<?php
	script('sciencemesh', 'settings-admin');
	style('sciencemesh', 'settings-admin');
?>

<div id="sciencemesh-admin" class="section">
	<h2 class="inlineblock"><?php p($l->t('ScienceMesh Settings')); ?></h2>
	<p>
		<label>
			<span><?php p($l->t('Private Key')); ?></span>
			<textarea id="sciencemesh-private-key" type="text"><?php p($_['privateKey']); ?></textarea>
		</label>

		<label>
			<span><?php p($l->t('Encryption Key')); ?></span>
			<textarea id="sciencemesh-encryption-key" type="text"><?php p($_['encryptionKey']); ?></textarea>
		</label>
	</p>
</div>