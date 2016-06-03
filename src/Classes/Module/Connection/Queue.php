<?php
namespace Climage\Module\Connection;
use \Pheanstalk\Pheanstalk;

class Queue
{
    /**
     * @var Pheanstalk|null
     */
    private $connection = null;
    private $tube = null;
    private $host = '127.0.0.1';
    private $port = null;
    

    public function __construct($tube = null, $host = null, $port = null)
    {
        if ($host) {
            $this->host = $host;
        } elseif (getenv('CLIMAGE_QUEUE_BEANSTALK_HOST')) {
            $this->host = getenv('CLIMAGE_QUEUE_BEANSTALK_HOST');
        }

        if ($port) {
            $this->port = $port;
        } elseif (getenv('CLIMAGE_QUEUE_BEANSTALK_PORT')) {
            $this->port = (int) getenv('CLIMAGE_QUEUE_BEANSTALK_PORT');
        }

        if ($tube) {
            $this->tube = $tube;
        }
    }

    /**
     * @return Pheanstalk
     */
    public function getLink()
    {
        if (!$this->connection) {
            if ($this->port) {
                $this->connection = new Pheanstalk(
                    $this->host,
                    $this->port
                );
            } else {
                $this->connection = new Pheanstalk(
                    $this->host
                );
            }
            
            if ($this->tube !== null) {
                $this->connection->useTube($this->tube);
            }
        }
        
        return $this->connection;
    }
}