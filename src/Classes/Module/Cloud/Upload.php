<?php
namespace Climage\Module\Cloud;

use Climage\Exception as E;
use Climage\Module\Connection\CloudGoogle;

class Upload
{
    private $module_cloud = null;

    protected function getModuleCloud()
    {
        if (!$this->module_cloud) {
            $this->module_cloud = new CloudGoogle();
        }

        return $this->module_cloud;
    }

    public function run($filename)
    {
        $this->getModuleCloud()->upload($filename);
        
        return $this;
    }
}