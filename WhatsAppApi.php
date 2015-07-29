<?php

namespace Pcabreus\WhatsApp;

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

    public function config($number, $nickname, $password, $debug = false)
    {
        $this->setNumber($number);
        $this->setNick($nickname);
        $this->setPassword($password);
        $this->setDebug($debug);

        $this->wa = new \WhatsProt($this->number, $this->nick, $this->debug);
        $this->wa->eventManager()->bind('onGetMessage', array($this, 'processReceivedMessage'));
        $this->wa->eventManager()->bind('onConnect', array($this, 'connected'));
        $this->wa->eventManager()->bind('onGetGroups', array($this, 'processGroupArray'));
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
     * @param array|string|integer $toNumbers
     * @param $message
     * @param $type
     */
    public function sendMessage($toNumbers, $message, $type)
    {
        $this->connectToWhatsApp();
        if (!is_array($toNumbers)) {
            $toNumbers = array($toNumbers);
        }

        foreach ($toNumbers as $to) {
            if ($type === self::MESSAGE_TYPE_TEXT) {
                $this->wa->sendMessageComposing($to);
                $this->wa->sendMessage($to, $message);
            }
            if ($type === self::MESSAGE_TYPE_IMAGE) {
                $this->wa->sendMessageImage($to, $message);
            }
            if ($type === self::MESSAGE_TYPE_AUDIO) {
                $this->wa->sendMessageAudio($to, $message);
            }
            if ($type === self::MESSAGE_TYPE_VIDEO) {
                $this->wa->sendMessageVideo($to, $message);
            }
            if ($type === self::MESSAGE_TYPE_LOCATION) {
                $this->wa->sendMessageLocation(
                    $to,
                    $message['userlong'],
                    $message['userlat'],
                    $message['locationname'],
                    null
                );
            }
        }
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
     */
    public function sendBroadcast($toNumbers, $message, $type)
    {
        $this->connectToWhatsApp();
        if ($type === self::MESSAGE_TYPE_TEXT) {
            $this->wa->sendBroadcastMessage($toNumbers, $message);
        }
        if ($type === self::MESSAGE_TYPE_IMAGE) {
            $this->wa->sendBroadcastImage($toNumbers, $message);
        }
        if ($type === self::MESSAGE_TYPE_AUDIO) {
            $this->wa->sendBroadcastAudio($toNumbers, $message);
        }
        if ($type === self::MESSAGE_TYPE_VIDEO) {
            $this->wa->sendBroadcastVideo($toNumbers, $message);
        }
        if ($type === self::MESSAGE_TYPE_LOCATION) {
            $this->wa->sendBroadcastLocation(
                $toNumbers,
                $message['userlong'],
                $message['userlat'],
                $message['locationname'],
                null
            );
        }
    }

    /**
     * Send the code for request the WhatsApp password to it own phone
     *
     * The option for method can be:
     *      - WhatsAppApi::CODE_REQUEST_TYPE_SMS
     *      - WhatsAppApi::CODE_REQUEST_TYPE_VOICE
     *
     * @param string $method The method should be sms or voice
     * @return bool
     */
    public function sendCodeRequest($method = WhatsAppApi::CODE_REQUEST_TYPE_SMS)
    {
        try {
            $this->wa->codeRequest($method);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the WhatsApp password
     *
     * @param string $code Code received in the phone after sendCodeRequest() method execution
     * @return string|null The WhatsApp password
     */
    public function getPasswordFromCode($code)
    {
        try {
            $result = $this->wa->codeRegister($code);

            return $result->pw;
        } catch (\Exception $e) {
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


} 