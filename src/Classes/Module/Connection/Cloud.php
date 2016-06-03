<?php
namespace Climage\Module\Connection;

use Climage\Exception as E;

abstract class Cloud 
{
    protected $project_name = 'CLIMAGE';
    protected $chunk = 1048576; //1024^2

    protected $client = null;

    public function __construct()
    {
        if (getenv('PROJECT_NAME') !== false) {
            $this->project_name = (string)getenv('PROJECT_NAME');
        }
    }

    abstract protected function getClient();

    abstract public function upload($filename);
    
    protected function readHandleChunk($handle)
    {
        $byteCount = 0;
        $giantChunk = '';
        while (!feof($handle)) {
            $chunk = fread($handle, 8192);
            $byteCount += strlen($chunk);
            $giantChunk .= $chunk;
            if ($byteCount >= $this->chunk) {
                return $giantChunk;
            }
        }

        return $giantChunk;
    }
}