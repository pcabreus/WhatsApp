<?php
/**
 * This file is part of the Pcabreus\WhatsApp library.
 *
 * (c) Pedro Carlos Abreu <pcabreus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pcabreus\WhatsApp;


/**
 * Class WhatsAppResponse
 * @package Pcabreus\WhatsApp
 *
 * @author Pedro Carlos Abreu <pcabreus@gmail.com>
 */
class WhatsAppResponse
{
    private $messageException;
    private $response;

    public function __construct($response = null)
    {
        $this->response = $response;
    }


    /**
     * Is the status 'ok'
     *
     * @return bool
     */
    public function isOk()
    {
        return $this->getProperty('status') === 'ok';
    }

    /**
     * Is the status 'sent'
     *
     * @return bool
     */
    public function isSent()
    {
        return $this->getProperty('status') === 'sent';
    }

    /**
     * Is the status 'fail'
     *
     * @return bool
     */
    public function isFail()
    {
        return $this->getProperty('status') === 'fail';
    }

    /**
     * Get the fail reason
     *
     * @return mixed
     */
    public function getFailReason()
    {
        return $this->getProperty('reason');
    }

    /**
     * Get a property
     *
     * @param $property
     * @return mixed
     */
    public function getProperty($property)
    {
        if (is_array($this->response)) {
            return $this->response[$property];
        }

        return isset($this->response->$property) ? $this->response->$property : null;
    }

    /**
     * @return mixed
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @param mixed $response
     */
    public function setResponse($response)
    {
        $this->response = $response;
    }

    /**
     * @return mixed
     */
    public function getMessageException()
    {
        return $this->messageException;
    }

    /**
     * @param mixed $messageException
     */
    public function setMessageException($messageException)
    {
        $this->messageException = $messageException;
    }
} 