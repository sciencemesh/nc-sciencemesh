<?php
    style("sciencemesh", "settings");
    script("sciencemesh", "settings");
?>
<div class="section section-sciencemesh">
    <h2 id="sciencemesh">
        ScienceMesh
    </h2>

    <h3><?php p($l->t("Settings")) ?></h3>

    <div id="sciencemeshAddrSettings">
        <p><?php p($l->t("Site Name")) ?></p>
        <p><input id="sciencemeshSitename" value="<?php p($_["sitename"]) ?>" placeholder="CERN" type="text"></p>
        <p><?php p($l->t("Site URL")) ?></p>
        <p><input id="sciencemeshSiteurl" value="<?php p($_["siteurl"]) ?>" placeholder="http://localhost" type="text"></p>
        <p><?php p($l->t("Country Code")) ?></p>
        <p><input id="sciencemeshCountryCode" value="<?php p($_["country"]) ?>" placeholder="CH" type="text"></p>
        <p><?php p($l->t("Hostname")) ?></p>
        <p><input id="sciencemeshHostname" value="<?php p($_["hostname"]) ?>" placeholder="example.org/xcloud/" type="text"></p>
        <p><?php p($l->t("IOP Service Address")) ?></p>
        <p><input id="sciencemeshIopUrl" value="<?php p($_["iopurl"]) ?>" placeholder="http://<IOP URL>/" type="text"></p>
        <p><?php p($l->t("Number of users")) ?></p>
        <p><input id="sciencemeshNumusers" value="<?php p($_["numusers"]) ?>" placeholder="0" type="number"></p>
        <p><?php p($l->t("Number of files")) ?></p>
        <p><input id="sciencemeshNumfiles" value="<?php p($_["numfiles"]) ?>" placeholder="0" type="number"></p>
        <p><?php p($l->t("Storage volume (in bytes)")) ?></p>
        <p><input id="sciencemeshNumstorage" value="<?php p($_["numstorage"]) ?>" placeholder="0" type="number"></p>
    </div>

    <div>
        <p><button id="sciencemeshSave" class="button"><?php p($l->t("Save")) ?></button></p>
    </div>
</div>
