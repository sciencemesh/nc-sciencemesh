<?php 
/**
 * controller <controller/SettingsController.php>
 * method <>
 * loader <Settings/AdvanceSettingsAdmin.php>
 * method <getForm>
 * menu item <Sections/AdvanceSettingsAdmin.php>
 * routeName <settings#get_advance_settings>
 * url <settings/admin/advance_settings> 
 * author <parhamin2010@gmail.com>
 * */

script('sciencemesh', 'settings');
style('sciencemesh', 'style');
?>
<section>
    <div class="viewcontainer section-sciencemesh">
        <div class="sciencemesh-settings-row">
            <label for="sciencemesh_iop_url" name="sciencemesh_iop_url">IOP URL</label>
            <input type="text" name="sciencemesh_iop_url" id="sciencemesh_iop_url" value="<?php echo $this->vars['sciencemeshIopUrl']; ?>">
        </div>
        <div class="sciencemesh-settings-row">
            <label for="sciencemesh_shared_secret" name="sciencemesh_shared_secret">Shared Secret</label>
            <input type="text" name="sciencemesh_shared_secret" id="sciencemesh_shared_secret" value="<?php echo $this->vars['sciencemeshRevaSharedSecret']; ?>">
        </div>
        <div class="sciencemesh-settings-row">
            <label for="sciencemesh_loopback_shared_secret" name="sciencemesh_iop_url">Loopback Secret</label>
            <input type="text" readonly="true" id="sciencemesh_loopback_shared_secret" value="<?php echo $this->vars['sciencemeshRevaLoopbackSecret']; ?>">
        <div>
            <input type="button" name="sciencemesh_setting_submit_btn" id="sciencemesh_setting_submit_btn" value="Save settings">
        </div>
    </div>
</section>
