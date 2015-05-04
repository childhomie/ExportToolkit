<?php

class ExportToolkit_ExportService {

    /**
     * @var ExportToolkit_ExportService_Worker[]
     */
    protected $workers;

    public function __construct() {

        $exporters = ExportToolkit_Configuration::getList();
        $this->workers = array();
        foreach($exporters as $exporter) {
            $this->workers[$exporter->getName()] = new ExportToolkit_ExportService_Worker($exporter);
        }

    }

    public function setUpExport($objectHook = false, $hookType = "save") {
        foreach($this->workers as $workerName => $worker) {
            if($worker->checkIfToConsider($objectHook, $hookType)) {
                $worker->setUpExport();
            }
        }
    }

    public function deleteFromExport(Object_Abstract $object, $objectHook = false) {
        foreach($this->workers as $workerName => $worker) {
            if($worker->checkIfToConsider($objectHook, "delete")) {
                if($worker->checkClass($object)) {
                    $worker->deleteFromExport($object);
                } else {
                    Logger::info("do not delete from export - object " . $object->getId() . " for " . $workerName . ".");
                }
            }
        }
    }

    public function updateExport(Object_Abstract $object, $objectHook = false, $hookType = "save") {
        foreach($this->workers as $workerName => $worker) {
            if($worker->checkIfToConsider($objectHook, $hookType)) {
                if($worker->checkClass($object)) {
                    $worker->updateExport($object);
                } else {
                    Logger::info("do not update export object " . $object->getId() . " for " . $workerName . ".");
                }
            }
        }
    }

    public function commitData($objectHook = false, $hookType = "save") {
        foreach($this->workers as $workerName => $worker) {
            if($worker->checkIfToConsider($objectHook, $hookType)) {
                $worker->commitData();
            }
        }
    }

    public function executeExport($workerName = null) {

        if($workerName) {
            $worker = $this->workers[$workerName];
            $this->doExecuteExport($worker, $workerName);
        } else {
            foreach($this->workers as $workerName => $worker) {
                $this->doExecuteExport($worker, $workerName);
            }
        }

    }

    protected function doExecuteExport(ExportToolkit_ExportService_Worker $worker, $workerName) {

        Pimcore_Log_Simple::log("export-toolkit-" . $workerName, "");

        $page = 0;
        $pageSize = 100;
        $count = $pageSize;

        $this->setUpExport(false);

        while($count > 0) {
            Pimcore_Log_Simple::log("export-toolkit-" . $workerName, "=========================");
            Pimcore_Log_Simple::log("export-toolkit-" . $workerName, "Page $workerName: $page");
            Pimcore_Log_Simple::log("export-toolkit-" . $workerName, "=========================");

            $objects = $worker->getObjectList();
            $objects->setOffset($page * $pageSize);
            $objects->setLimit($pageSize);

            foreach($objects as $object) {
                Pimcore_Log_Simple::log("export-toolkit-" . $workerName, "Updating product " . $object->getId());
                if($worker->checkClass($object)) {
                    $worker->updateExport($object);
                } else {
                    Pimcore_Log_Simple::log("export-toolkit-" . $workerName, "do not update export object " . $object->getId() . " for " . $workerName . ".");
                }
            }
            $page++;
            $count = count($objects->getObjects());

            Pimcore::collectGarbage();
        }

        $worker->commitData();

    }

}
