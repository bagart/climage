<?php
namespace Climage\Proc;
use Climage\Exception as E;
use Climage\Module\Connection;
use Climage\Module\Image;
use Climage\Module\Cloud;

class Queue
{
    const QUEUE_RESIZE = 'resize';
    const QUEUE_UPLOAD = 'upload';
    const QUEUE_DONE = 'done';
    const QUEUE_FAILED = 'failed';

    private static $all_tube = [
        self::QUEUE_RESIZE,
        self::QUEUE_UPLOAD,
        self::QUEUE_DONE,
        self::QUEUE_FAILED,
    ];
    
    protected $copy_first = false;

    //for unique name
    protected $uniq_name = false;

    private $module_image_resize = null;
    private $module_upload = null;
    /**
     * @var Connection\Queue[]
     */
    private $connection_queue = [];


    public function __construct()
    {
        assert(is_dir(getenv('CLIMAGE_DIR_RESIZED')));
        assert(is_writable(getenv('CLIMAGE_DIR_RESIZED')));

        //check path
        $this
            ->setCopyFirst(getenv('CLIMAGE_COPY_FIRST'))
            ->setUniqName(getenv('CLIMAGE_UNIQ_NAME'));
    }
    
    public function status() 
    {
        $status = [];
        foreach (static::$all_tube as $tube) {
             $cur_stat = (array) $this
                ->getQueueConnector($tube)
                ->statsTube($tube);

            $status[$tube] = isset($cur_stat['current-jobs-ready'])
                ? $cur_stat['current-jobs-ready']
                : 'error';
        }
        
        return $status;
    }

    /**
     * disable delete original files
     * @param $bool
     * @return $this
     */
    public function setCopyFirst($bool)
    {
        $this->copy_first = (bool) $bool;
        if ($this->copy_first) {
            assert(is_dir(getenv('CLIMAGE_DIR_IMAGES')));
            assert(is_writable(getenv('CLIMAGE_DIR_IMAGES')));
        }
        return $this;
    }

    /**
     * generate uniq prefix
     * @param $bool
     * @return $this
     */
    public function setUniqName($bool)
    {
        $this->uniq_name = (bool) $bool;

        return $this;
    }
    /**
     * Add all image from $paths to queue
     * @param array $paths
     * @param array|null $result
     * @return array
     */
    public function schedule(array $paths, array &$result = null)
    {
        assert(count($paths));
        if (!$result) {
            $result = [
                'count' => [
                    'add' => 0,
                ],
                'errors' => [],
            ];
        }

        foreach ($paths as $path) {
            if (is_link($path)) {
                $result['errors']['link is disabled'][] = $path;
                continue;
            }
            $path = realpath($path);
            if (!file_exists($path)) {
                $result['errors']['not exists'][] = $path;
                continue;
            }
            if (!is_readable($path)) {
                $result['errors']['not readable'][] = $path;
                continue;
            }
            if (is_dir($path)) {
                foreach ((new \DirectoryIterator ($path)) as $info) {
                    $info = (string) $info;
                    if ($info[0] != '.') {
                        //hidden path or $info->isDot
                        $this->schedule(
                            [$path . DIRECTORY_SEPARATOR . $info],
                            $result
                        );
                    }
                }
                continue;
            }

            if (!is_file($path)) {
                $result['errors']['unknown type'][] = $path;
                continue;
            }
            if (!@exif_imagetype($path)) {
                $result['errors']['not image'][] = $path;
                continue;
            }
            $basename = basename($path);
            if ($this->copy_first) {
                $new_filename = $this->getNewImageName(
                    $basename,
                    getenv('CLIMAGE_DIR_IMAGES')
                );
                copy($path, $new_filename);
                if (!file_exists($new_filename)) {
                    $result['errors']['copy'][] = $path;
                    continue;
                }
                $path = $new_filename;
            }

            try {
                $this->addQueue($job = [
                    'type' => static::QUEUE_RESIZE,
                    'filename' => $path,
                    'basename' => $basename,
                    'date_create' => time(),
                ]);
                ++$result['count']['add'];
            } catch (\Exception $e) {
                $result['errors']['queue error'][] = $path;
                error_log($e->getMessage());
                continue;
            }
        }

        return $result;
    }

    protected function getNewImageName($old_name, $path = null, $ext = null)
    {
        if (is_string($ext)) {
            //prepare ext
            $old_name = preg_replace('~\.[^\.\/]*$~iu', $ext !== '' ? '.' . $ext : null, $old_name);
        }
        
        if (is_string($path)) {
            $path = $path . DIRECTORY_SEPARATOR;
        }

        if (!$this->uniq_name) {
            return $path . basename($old_name);
        }

        $i = 1000;
        $new_filename = preg_replace(
            '~\.([^\.]*)$~u',
            '.' . microtime(true) . ".$1",
            basename($old_name)
        );
        if ($path && is_dir($path)) {
            while (                
                file_exists($path . $new_filename)
                && --$i
            ) {
                $new_filename = preg_replace(
                    '~\.([^\.]*)$~u',
                    '.' . microtime(true) . ".$1",
                    basename($old_name)
                );
            }
        }
        
        return $path . $new_filename;
    }
    
    protected function addFiledQueue(array $job, $message = null)
    {
        if (!isset($job['error']['try_count'][$job['type']])) {
            $job['error']['try_count'][$job['type']] = 0;
        }
        ++$job['error']['try_count'][$job['type']];
        $job['error'][$message] = $message;

        $this
            ->getQueueConnector(static::QUEUE_FAILED)
            ->put(
                json_encode($job)
            );

        return $this;
    }

    protected function addQueue(array $job)
    {
        $this
            ->getQueueConnector($job['type'])
                ->put(
                    json_encode($job)
                );

        return $this;
    }

    public function run($queue_name, $count = null)
    {
        $this->getQueueConnector($queue_name)
            ->watch($queue_name)
            ->ignore('default');

        while (is_null($count) || --$count >= 0) {
            $queu = $this->getQueueConnector($queue_name)
                ->reserve();
            $job = json_decode(
                $queu->getData(),
                true
            );
            try {
                $this->processSingleJob($job);
            } catch (\Exception $e) {
                $this->addFiledQueue(
                    $job,
                    get_class($e) . ': ' . $e->getMessage()
                );
            }
            $this->getQueueConnector($queue_name)
                ->delete($queu);
        }
    }

    protected function processSingleJob(array $job)
    {
        switch ($job['type']) {
            case static::QUEUE_RESIZE:
                $this->precessResize($job);
                break;
            case static::QUEUE_UPLOAD:
                $this->precessUpload($job);
                break;
        }
        
        return $this;
    }

    protected function precessResize(array $job)
    {
        $new_filename = $this->getNewImageName(
            $job['basename'],
            getenv('CLIMAGE_DIR_RESIZED'),
            getenv('CLIMAGE_PREPARE_FORMAT')
        );        
 
        try {
            $this->getModuleImageResize()
                ->run(
                    $job['filename'],
                    $new_filename
                );
        } catch (E\ClimageException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw (new E\WrongResultException($e->getMessage()))
                ->setRealException($e);
        }

        $old_filename = $job['filename'];
        $this->addQueue(
            [
                'filename' => $new_filename,
                'type' => static::QUEUE_UPLOAD,
                'error' => null,
            ] + $job
        );
        unlink($old_filename);

        return $this;
    }

    protected function precessUpload(array $job)
    {
        $this->getModuleUpload()->run($job['filename']);

        $this->addQueue(
            [
                'type' => static::QUEUE_DONE,
                'error' => null,
            ] + $job
        );

        unlink($job['filename']);

        return $this;
    }

    protected function getModuleImageResize()
    {
        if (!$this->module_image_resize) {
            $this->module_image_resize = new Image\Resize();
        }
        
        return $this->module_image_resize;
    }

    protected function getModuleUpload()
    {
        if (!$this->module_upload) {
            $this->module_upload = new Cloud\Upload();
        }

        return $this->module_upload;
    }

    /**
     * @param string $name tube
     * @return \Pheanstalk\Pheanstalk
     */
    protected function getQueueConnector($name)
    {
        if (!isset($this->connection_queue[$name])) {
            $this->connection_queue[$name] = new Connection\Queue($name);
        }

        return $this->connection_queue[$name]->getLink();
    }
}