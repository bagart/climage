<?php
namespace Climage\Module\Image;

use Climage\Exception as E;

class Resize
{
    private $result_type = IMAGETYPE_JPEG;
    private $result_quality = 90;
    private $result_height = 640;
    private $result_width = 640;
    private $result_bg_color = '#fff';
    private $result_enlarge = false;

    public function __construct()
    {
        if (getenv('CLIMAGE_PREPARE_RESIZE_WIDTH') !== false) {
            $this->setResultWidth(
                getenv('CLIMAGE_PREPARE_RESIZE_WIDTH')
            );
        }
        if (getenv('CLIMAGE_PREPARE_RESIZE_HEIGHT') !== false) {
            $this->setResultHeight(
                getenv('CLIMAGE_PREPARE_RESIZE_HEIGHT')
            );
        }
        if (getenv('CLIMAGE_PREPARE_RESIZE_QUALITY') !== false) {
            $this->setResultQuality(
                getenv('CLIMAGE_PREPARE_RESIZE_QUALITY')
            );
        }
        if (getenv('CLIMAGE_PREPARE_RESIZE_BG_COLOR') !== false) {
            $this->setResultBgColor(
                getenv('CLIMAGE_PREPARE_RESIZE_BG_COLOR')
            );
        }
        if (getenv('CLIMAGE_PREPARE_RESIZE_ENLARGE') !== false) {
            $this->setResultEnlarge(
                getenv('CLIMAGE_PREPARE_RESIZE_ENLARGE')
            );
        }
    }

    public function setResultType($result_type)
    {
        $this->result_type = $result_type;
        
        return $this;
    }

    public function setResultQuality($result_quality)
    {
        $this->result_quality = (int) $result_quality;

        return $this;
    }

    public function setResultHeight($result_height)
    {
        $this->result_height = (int) $result_height;

        return $this;
    }
    
    public function setResultWidth($result_width)
    {
        $this->result_width = (int) $result_width;

        return $this;
    }

    public function setResultBgColor($result_bg_color)
    {
        $this->result_bg_color = $result_bg_color;

        return $this;
    }

    public function setResultEnlarge($result_enlarge)
    {
        $this->result_enlarge = (bool) $result_enlarge;

        return $this;
    }


    public function run($filename, $new_filename)
    {
        if (!file_exists($filename)) {
            throw new E\WrongParamException("!file_exists(orig)");
        }
        $source = new \Imagick($filename);
        
        if (strlen($this->result_bg_color)) {
            $source->setImageBackgroundColor($this->result_bg_color);
        }
        
        $source->setCompressionQuality($this->result_quality);

        $source->thumbnailImage(
            $this->result_width, 
            $this->result_height,
            $this->result_enlarge,
            strlen($this->result_bg_color) > 0
        );

        file_put_contents($new_filename, $source);
        $source->destroy();
        unset($source);

        if (!file_exists($new_filename)) {
            throw new E\WrongResultException(
                "result file not exists: $new_filename"
            );
        }

        return $this;
    }
}