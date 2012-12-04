<?php


/*
 * 
 * There are no enums in PHP so this abstract class counts for one - 
 * 
 * This class contains a list of client types, store this with the client so the client type can be identified and any quirks can be dealt with
 *  
 * For quick guide to different HTML5 WebSocket protocols see 
 * http://en.wikipedia.org/wiki/Websocket
 * 
 * For more detail see comments with each type
 * 
 */



abstract class ClientTypes
{
    const PROTO_00 = 0; //http://tools.ietf.org/html/draft-ietf-hybi-thewebsocketprotocol-00 currently used by Safari 5
    const PROTO_06 = 1; //http://tools.ietf.org/html/draft-ietf-hybi-thewebsocketprotocol-06 (06 - 09 use the same handshake and masking) currently used by Chrome 15
    const FLASH = 2;
    // etc.
}

?>