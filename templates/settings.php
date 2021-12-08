<?php
style("sciencemesh", "settings");
script("sciencemesh", "settings");
?>
<div class="section section-sciencemesh">
    <h2 id="sciencemesh">
        ScienceMesh
    </h2>

    <h3><?php p($l->t("Site Settings")) ?></h3>
    <div id="sciencemeshSiteSettings">
        <p><?php p($l->t("Site Name")) ?></p>
        <p><input id="sciencemeshSitename" value="<?php p($_["sitename"]) ?>" placeholder="CERN" type="text"><em>The name of this site.</em></p>

        <p><?php p($l->t("Site URL")) ?></p>
        <p><input id="sciencemeshSiteurl" value="<?php p($_["siteurl"]) ?>" placeholder="https://owncloud.example.com" type="text"><em>The URL at which your site can be reached.</em></p>

        <p><?php p($l->t("Country Code")) ?></p>
        <p><input id="sciencemeshCountryCode" value="<?php p($_["country"]) ?>" placeholder="CH" type="text"><em>The 2- or 3-digit code of the site's country. A list of all codes can be found <a href="https://www.nationsonline.org/oneworld/country_code_list.htm" target="_blank">here</a>.</em></p>
    </div>

    <h3><?php p($l->t("IOP Settings")) ?></h3>
    <div id="sciencemeshIOPSettings">
        <p><?php p($l->t("IOP Service Address")) ?></p>
        <p><input id="sciencemeshIopUrl" value="<?php p($_["iopurl"]) ?>" placeholder="https://owncloud.example.com/iop" type="text"><em>The main URL of your IOP service. If the IOP is running on the same host as this ownCloud instance, you can simply use <strong>http://localhost:&#x3C;iop-port&#x3E;</strong> here.</em></p>
    </div>

    <h3><?php p($l->t("Metrics")) ?></h3>
    <div id="sciencemeshMetricsSettings">
        <em><strong>Note: </strong>The following settings need to be provided manually for now, as they are not yet extracted automatically from ownCloud. This will change in the future, though.</em>
        <p><?php p($l->t("Number of users")) ?></p>
        <p><input id="sciencemeshNumusers" value="<?php p($_["numusers"]) ?>" placeholder="0" type="number"></p>
        <p><?php p($l->t("Number of files")) ?></p>
        <p><input id="sciencemeshNumfiles" value="<?php p($_["numfiles"]) ?>" placeholder="0" type="number"></p>
        <p><?php p($l->t("Storage volume (in bytes)")) ?></p>
        <p><input id="sciencemeshNumstorage" value="<?php p($_["numstorage"]) ?>" placeholder="0" type="number"></p>
    </div>

    <h3><?php p($l->t("API Key")) ?></h3>
    <div id="sciencemeshAPIKeySettings">
        <p><?php p($l->t("API Key")) ?></p>
        <p><input id="sciencemeshAPIKey" value="<?php p($_["apikey"]) ?>" placeholder="" type="text"><em>An API key is needed to register your site with ScienceMesh. If you do not have a key yet, you can register for a free ScienceMesh account using <a href="https://iop.sciencemesh.uni-muenster.de/iop/siteacc/register" target="_blank">this link</a>.</em></p>
    </div>

    <div>&nbsp;</div>
    <div>
        <p>
            <button id="sciencemeshSave" class="button"><?php p($l->t("Save")) ?></button>
            <em><strong>Note: </strong>Clicking 'Save' will, if a valid API key has been entered above, register your site with ScienceMesh (or update your existing entry).</em>
        </p>
    </div>
</div>
