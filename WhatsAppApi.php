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

use Pcabreus\WhatsApp\Exception\CodeRegisterFailed;
use Pcabreus\WhatsApp\Exception\CodeRegisterFailedException;
use Pcabreus\WhatsApp\Exception\ResponseException;
use Pcabreus\WhatsApp\Exception\TooManyGuessesException;
use Pcabreus\WhatsApp\Exception\TooRecentException;

/**
 * Class WhatsAppApi
 *
 * This class is an api for work with WhatsApp server
 * The actual implementation is based on \WhatsProt, "mgp25/whatsapi"
 *
 * @package Pcabreus\WhatsApp
 *
 * @author Pedro Carlos Abreu <pcabreus@gmail.com>
 */
class WhatsAppApi
{
    const MESSAGE_TYPE_TEXT = 'text';
    const MESSAGE_TYPE_IMAGE = 'image';
    const MESSAGE_TYPE_AUDIO = 'audio';
    const MESSAGE_TYPE_VIDEO = 'video';
    const MESSAGE_TYPE_LOCATION = 'location';

    const CODE_REQUEST_TYPE_SMS = 'sms';
    const CODE_REQUEST_TyPE_VOICE = 'voice';

    /**
     * @var  \WhatsProt
     */
    private $wa;

    private $number;
    private $nick;
    private $password;
    private $contacts = array();
    private $waGroupList;

    private $messages;
    private $connected;

    private $debug;

    public function config($number, $nickname, $password, $debug = false, $identifyFile = false)
    {
        $this->setNumber($number);
        $this->setNick($nickname);
        $this->setPassword($password);
        $this->setDebug($debug);

        $this->wa = new \WhatsProt($this->number, $this->nick, $this->debug, $identifyFile);
        $this->wa->eventManager()->bind('onGetMessage', array($this, 'processReceivedMessage'));
        $this->wa->eventManager()->bind('onConnect', array($this, 'connected'));
        $this->wa->eventManager()->bind('onGetGroups', array($this, 'processGroupArray'));
        $this->wa->eventManager()->bind('onCodeRequestFailedTooRecent', array($this, 'throwTooRecentException'));
        $this->wa->eventManager()->bind(
            'onCodeRequestFailedTooManyGuesses',
            array($this, 'throwTooManyGuessesException')
        );
        $this->wa->eventManager()->bind('onCodeRequestFailed', array($this, 'throwCodeRequestFailedException'));
        $this->wa->eventManager()->bind('onCodeRegisterFailed', array($this, 'throwCodeRegisterFailedException'));
    }

    /**
     * Process inbound text messages.
     *
     * If an inbound message is detected, this method will
     * store the details so that it can be shown to the user
     * at a suitable time.
     *
     * @param string $phone The number that is receiving the message
     * @param string $from The number that is sending the message
     * @param string $id The unique ID for the message
     * @param string $type Type of inbound message
     * @param string $time Y-m-d H:m:s formatted string
     * @param string $name The Name of sender (nick)
     * @param string $data The actual message
     *
     * @return void
     */
    public function processReceivedMessage($phone, $from, $id, $type, $time, $name, $data)
    {
        $matches = null;
        $time = date('Y-m-d H:i:s', $time);
        if (preg_match('/\d*/', $from, $matches)) {
            $from = $matches[0];
        }
        $this->messages[] = array(
            'phone' => $phone,
            'from' => $from,
            'id' => $id,
            'type' => $type,
            'time' => $time,
            'name' => $name,
            'data' => $data
        );
    }

    /**
     * Sets flag when there is a connection with WhatsAPP servers.
     *
     * @return void
     */
    public function connected()
    {
        $this->connected = true;
    }

    /**
     * Process the event onGetGroupList and sets a formatted array of groups the user belongs to.
     *
     * @param  string $phone The phone number (jid ) of the user
     * @param  array $groupArray Array with details of all groups user eitehr belongs to or owns.
     * @return array|boolean
     */
    public function processGroupArray($phone, $groupArray)
    {
        $formattedGroups = array();

        if (!empty($groupArray)) {
            foreach ($groupArray as $group) {
                $formattedGroups[] = array('name' => "GROUP: " . $group['subject'], 'id' => $group['id']);
            }

            $this->waGroupList = $formattedGroups;

            return true;
        }

        return false;
    }

    /**
     * Return all groups a user belongs too.
     *
     * Log into the whatsapp servers and return a list
     * of all the groups a user participates in.
     *
     * @return void
     */
    public function getGroupList()
    {
        $this->connectToWhatsApp();
        $this->wa->sendGetGroups();
    }

    /**
     * @param $status
     * @return bool
     */
    public function updateStatus($status)
    {
        if (isset($status) && trim($status) !== '') {
            $this->connectToWhatsApp();
            $this->wa->sendStatusUpdate($status);

            return true;
        }

        return false;
    }

    /**
     * Sends a message to a contact.
     *
     * Depending on the inputs sends a
     * message/video/image/location message to
     * a contact.
     *
     * @param $toNumbers
     * @param $message
     * @param $type
     * @return array
     */
    public function sendMessage($toNumbers, $message, $type)
    {
        $this->connectToWhatsApp();
        if (!is_array($toNumbers)) {
            $toNumbers = array($toNumbers);
        }

        $messagesId = array();

        foreach ($toNumbers as $to) {
            $id = null;
            if ($type === self::MESSAGE_TYPE_TEXT) {
                $this->wa->sendMessageComposing($to);
                $id = $this->wa->sendMessage($to, $message);
            }
            if ($type === self::MESSAGE_TYPE_IMAGE) {
                $id = $this->wa->sendMessageImage($to, $message);
            }
            if ($type === self::MESSAGE_TYPE_AUDIO) {
                $id = $this->wa->sendMessageAudio($to, $message);
            }
            if ($type === self::MESSAGE_TYPE_VIDEO) {
                $id = $this->wa->sendMessageVideo($to, $message);
            }
            if ($type === self::MESSAGE_TYPE_LOCATION) {
                $id = $this->wa->sendMessageLocation(
                    $to,
                    $message['userlong'],
                    $message['userlat'],
                    $message['locationname'],
                    null
                );
            }
            $messagesId[$to] = $id;
        }

        return $messagesId;
    }

    /**
     * Sends a broadcast Message to a group of contacts.
     *
     * Currenly only sends a normal message to
     * a group of contacts.
     *
     * @param $toNumbers
     * @param $message
     * @param $type
     * @return array|null|string
     */
    public function sendBroadcast($toNumbers, $message, $type)
    {
        $this->connectToWhatsApp();

        $messagesId = array();
        if ($type === self::MESSAGE_TYPE_TEXT) {
            $messagesId = $this->wa->sendBroadcastMessage($toNumbers, $message);
        }
        if ($type === self::MESSAGE_TYPE_IMAGE) {
            $messagesId = $this->wa->sendBroadcastImage($toNumbers, $message);
        }
        if ($type === self::MESSAGE_TYPE_AUDIO) {
            $messagesId = $this->wa->sendBroadcastAudio($toNumbers, $message);
        }
        if ($type === self::MESSAGE_TYPE_VIDEO) {
            $messagesId = $this->wa->sendBroadcastVideo($toNumbers, $message);
        }
        if ($type === self::MESSAGE_TYPE_LOCATION) {
            $messagesId = $this->wa->sendBroadcastLocation(
                $toNumbers,
                $message['userlong'],
                $message['userlat'],
                $message['locationname'],
                null
            );
        }

        return $messagesId;
    }

    /**
     * Send the code for request the WhatsApp password to it own phone
     *
     * The option for method can be:
     *      - WhatsAppApi::CODE_REQUEST_TYPE_SMS
     *      - WhatsAppApi::CODE_REQUEST_TYPE_VOICE
     *
     * @param string $method The method should be sms or voice
     * @return null|WhatsAppResponse
     */
    public function sendCodeRequest($method = WhatsAppApi::CODE_REQUEST_TYPE_SMS)
    {
        try {
            $result = $this->wa->codeRequest($method);

            return new WhatsAppResponse($result);
        } catch (TooRecentException $e) {
            return $e->getResponse();
        } catch (TooManyGuessesException $e) {
            return $e->getResponse();
        } catch (ResponseException $e) {
            return $e->getResponse();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the WhatsApp password
     *
     * @param $code
     * @return null|WhatsAppResponse
     */
    public function sendCodeRegister($code)
    {
        try {
            $result = $this->wa->codeRegister($code);

            $response = new WhatsAppResponse($result);

            return $response;
        } catch (CodeRegisterFailedException $e) {
            return $e->getResponse();
        }catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Cleanly disconnect from Whatsapp.
     *
     * Ensure at end of script, if a connected had been made
     * to the whatsapp servers, that it is nicely terminated.
     *
     * @return void
     */
    public function __destruct()
    {
        if (isset($this->wa) && $this->connected) {
            $this->wa->disconnect();
        }
    }

    /**
     * Connect to Whatsapp.
     *
     * Create a connection to the whatsapp servers
     * using the supplied password.
     *
     * @return boolean
     */
    private function connectToWhatsApp()
    {
        if (isset($this->wa)) {
            $this->wa->connect();
            $this->wa->loginWithPassword($this->password);

            return true;
        }

        return false;
    }

    /**
     * @return mixed
     */
    public function getNumber()
    {
        return $this->number;
    }

    /**
     * @param mixed $number
     */
    public function setNumber($number)
    {
        $this->number = $number;
    }

    /**
     * @return mixed
     */
    public function getNick()
    {
        return $this->nick;
    }

    /**
     * @param mixed $nick
     */
    public function setNick($nick)
    {
        $this->nick = $nick;
    }

    /**
     * @return mixed
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param mixed $password
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * @return mixed
     */
    public function getConnected()
    {
        return $this->connected;
    }

    /**
     * @param mixed $connected
     */
    public function setConnected($connected)
    {
        $this->connected = $connected;
    }

    /**
     * @return array
     */
    public function getContacts()
    {
        return $this->contacts;
    }

    /**
     * @param array $contacts
     */
    public function setContacts($contacts)
    {
        $this->contacts = $contacts;
    }

    /**
     * @return mixed
     */
    public function getDebug()
    {
        return $this->debug;
    }

    /**
     * @param mixed $debug
     */
    public function setDebug($debug)
    {
        $this->debug = $debug;
    }

    /**
     * @return mixed
     */
    public function getMessages()
    {
        return $this->messages;
    }

    /**
     * @param mixed $messages
     */
    public function setMessages($messages)
    {
        $this->messages = $messages;
    }

    /**
     * @param $phoneNumber
     * @param $method
     * @param $reason
     * @param $retryAfter
     * @throws TooRecentException
     */
    public function throwTooRecentException($phoneNumber, $method, $reason, $retryAfter)
    {
        $response = new WhatsAppResponse(
            array(
                'status' => 'fail',
                'reason' => $reason,
                'retry_after' => $retryAfter,
                'method' => $method,
                'number' => $phoneNumber
            )
        );
        $minutes = round($retryAfter / 60);
        throw new TooRecentException("Code already sent. Retry after $minutes minutes.", $response);
    }

    /**
     * @param $phoneNumber
     * @param $method
     * @param $reason
     * @param $retryAfter
     * @throws TooManyGuessesException
     */
    public function throwTooManyGuessesException($phoneNumber, $method, $reason, $retryAfter)
    {
        $response = new WhatsAppResponse(
            array(
                'status' => 'fail',
                'reason' => $reason,
                'retry_after' => $retryAfter,
                'method' => $method,
                'number' => $phoneNumber
            )
        );
        $minutes = round($retryAfter / 60);
        throw new TooManyGuessesException("Too many guesses. Retry after $minutes minutes.", $response);
    }

    /**
     * @param $phoneNumber
     * @param $method
     * @param $reason
     * @param $param
     * @throws ResponseException
     */
    public function throwCodeRequestFailedException($phoneNumber, $method, $reason, $param)
    {
        $response = new WhatsAppResponse(
            array(
                'status' => 'fail',
                'reason' => $reason,
                'param' => $param,
                'method' => $method,
                'number' => $phoneNumber
            )
        );
        throw new ResponseException("There was a problem trying to request the code.", $response);
    }

    /**
     * @param $phoneNumber
     * @param $status
     * @param $reason
     * @param $retryAfter
     * @throws CodeRegisterFailedException
     */
    public function throwCodeRegisterFailedException($phoneNumber, $status, $reason, $retryAfter)
    {
        $response = new WhatsAppResponse(
            array(
                'status' => $status,
                'reason' => $reason,
                'retry_after' => $retryAfter,
                'number' => $phoneNumber
            )
        );
        throw new CodeRegisterFailedException("An error occurred registering the registration code from WhatsApp. Reason: $reason", $response);
    }
} 