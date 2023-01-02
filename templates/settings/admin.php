<?php
script('sciencemesh', 'settings');
style('sciencemesh', 'style');
?>
<section>
    <div class="viewcontainer section-sciencemesh">
        <div>
            <label for="sciencemesh_iop_url" name="sciencemesh_iop_url">IOP URL:</label>
            <input type="text" name="sciencemesh_iop_url" id="sciencemesh_iop_url">
        </div>
        <div>
            <label for="sciencemesh_shared_secret" name="sciencemesh_shared_secret">Shared Secret:</label>
            <input type="text" name="sciencemesh_shared_secret" id="sciencemesh_shared_secret">
        </div>
        <div>
            <label for="sciencemesh_loopback_shared_secret" name="sciencemesh_iop_url">Loopback Shared Secret:</label>
            <input type="text" name="sciencemesh_loopback_shared_secret" id="sciencemesh_loopback_shared_secret">
        <div>
            <input type="button" name="sciencemesh_setting_submit_btn" id="sciencemesh_setting_submit_btn" value="Save settings">
        </div>
    </div>
</section>
