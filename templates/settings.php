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
        <p><?php p($l->t("IOP Service Address")) ?></p>
        <p><input id="sciencemeshIopUrl" value="<?php p($_["iopurl"]) ?>" placeholder="http://<IOP URL>/" type="text"></p>
        <p><?php p($l->t("Site Name")) ?></p>
        <p><input id="sciencemeshSiteName" value="<?php p($_["iopurl"]) ?>" placeholder="CERN" type="text"></p>
        <p><?php p($l->t("Homepage")) ?></p>
        <p><input id="sciencemeshSiteName" value="<?php p($_["iopurl"]) ?>" placeholder="example.org" type="text"></p>
        <p><?php p($l->t("Country Code")) ?></p>
        <p><input id="sciencemeshCountryCode" value="<?php p($_["countrycode"]) ?>" placeholder="CH" type="text"></p>
        <p><?php p($l->t("Hostname")) ?></p>
        <p><input id="sciencemeshHostname" value="<?php p($_["hostname"]) ?>" placeholder="example.org/xcloud/" type="text"></p>
    </div>

    <div>
        <p><button id="sciencemeshSave" class="button"><?php p($l->t("Save")) ?></button></p>
    </div>
</div>
