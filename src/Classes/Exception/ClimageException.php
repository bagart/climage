<?php

namespace Climage\Exception;

/**
 * Class WrongResultException
 * @package Climage\Exception
 */
class ClimageException extends \Exception
{
    /**
     * @var \Exception|null
     */
    private $real_exception = null;

    /**
     * @param \Exception $e
     * @return $this
     */
    public function setRealException(\Exception $e) {
        $this->real_exception =  $e;
        
        return $this;
    }

    /**
     * @return \Exception|null
     */
    public function getRealException() {
        return $this->real_exception;
    }

}