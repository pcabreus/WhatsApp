<?php
/**
 * This file is part of the Pcabreus\WhatsApp library.
 *
 * (c) Pedro Carlos Abreu <pcabreus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pcabreus\WhatsApp\Exception;

use Exception;
use Pcabreus\WhatsApp\WhatsAppResponse;


/**
 * Class ResponseExceptionInterface
 * @package Pcabreus\WhatsApp\Exception
 *
 * @author Pedro Carlos Abreu <pcabreus@gmail.com>
 */
class ResponseException extends \Exception
{
    /**
     * @var WhatsAppResponse
     */
    private $response;

    public function __construct(
        $message = "",
        WhatsAppResponse $whatsAppResponse = null,
        $code = 0,
        Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->response = $whatsAppResponse;
    }


    /**
     * @param WhatsAppResponse $whatsAppResponse
     * @return mixed
     */
    public function setResponse(WhatsAppResponse $whatsAppResponse)
    {
        $this->response = $whatsAppResponse;

        return $this;
    }

    /**
     * @return WhatsAppResponse
     */
    public function getResponse()
    {
        return $this->response;
    }
} 