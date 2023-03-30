
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
            <input type="text" style="width: 65%;" name="sciencemesh_iop_url" id="sciencemesh_iop_url" value="<?php echo $this->vars['sciencemeshIopUrl']; ?>">
            <button type="button" id="check_connection_sciencemesh_iop_url">Connection test</button>
        </div>
        <div class="sciencemesh-settings-row">
            <label for="sciencemesh_shared_secret" name="sciencemesh_shared_secret">Shared Secret</label>
            <input type="text" style="width: 77%;"id="sciencemesh_shared_secret" value="<?php echo $this->vars['sciencemeshRevaSharedSecret']; ?>">
        </div>
        <hr style="opacity: 0.1;">
        <input type="button" name="sciencemesh_setting_submit_btn" id="sciencemesh_setting_submit_btn" value="Save settings">
        </div>
    </div>
</section>
