<?php

namespace Espo\Modules\Cloud3\Jobs;

use \Espo\Core\Exceptions;

class AdHocAPI extends \Espo\Core\Jobs\Base
{

    public function run()
    {
        try {
            $updater = $this->getServiceFactory()->create('AdHocAPIUpdater');
            $exporter = $this->getServiceFactory()->create('AdHocAPIExporter');
            if($jobId = $updater->updateAccounts()) {
                $updater->deleteAccounts($jobId);
            }
            if($jobId = $updater->updateProducts()) {
                //$updater->deleteProducts($jobId);
            }
            if($jobId = $updater->updatePrepagati()){
                $updater->deletePrepagati($jobId);
            }
            $exporter->exportRapportini();
        } catch (\Exception $e) {
            $GLOBALS['log']->error('JOB AdHocAPI: ' . $e->getMessage());
        }
        return true;
    }
}
