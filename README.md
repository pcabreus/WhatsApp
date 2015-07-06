WhatsAppApi
===========

This library is a simple php programming object oriented of "https://github.com/mgp25/Chat-API" application.

For full documentation **read the [wiki](https://github.com/mgp25/Chat-API/wiki)**

Installation
------------

You first must add the library to your composer.json:

    [json]
    "require": {

        "pcabreus/whatsapp-api": "~0.1"
    }

Then you need to install the library with composer.

Usage
-----

After that you can use the WhatsAppApi class like this:

    [php]
    //Create the service
    $whatsAppApi = new WhatsAppApi();
    //Configure the connexion with the user credentials
    $whatsAppApi->config("1123456789", "pcabreus", "this-is-secret", false);

    //Send a message to a contact
    $whatsAppApi->sendMessage("1987654321", "This is a message", WhatsAppApi::MESSAGE_TYPE_TEXT);

    //Or you cant send a message to a group of contact
    $numbers = array("1987654321", "44123456789");
    $whatsAppApi->sendMessage($numbers, "This message go to many users", WhatsAppApi::MESSAGE_TYPE_TEXT);

ToDo

More documentation...