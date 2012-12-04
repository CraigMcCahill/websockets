<?php
/**
 * WebSocket extension class of phpWebSockets
 *
 */

require_once 'clientTypes.class.php';

class socketWebSocket extends socket
{
	private $clients = array();
	private $handshakes = array();
	private $flashPolicy;
	
	//const FLASH_SECURITY_POLICY_FILE = "flashpolicy.xml";
	const CODE_LENGTH = 8;
	
	public function __construct()
	{
		parent::__construct();
		//$flashPolicy = file_get_contents(FLASH_SECURITY_POLICY_FILE); 
		
		$this->flashPolicy =  '<cross-domain-policy>
     <allow-access-from domain="' . $this->host . '" to-ports="' . $this->port . '"/>
	</cross-domain-policy>';
		
		$this->run();
	}

	/**
	 * Runs the while loop, wait for connections and handle them
	 */
	private function run()
	{
		while(true)
		{
			# because socket_select gets the sockets it should watch from $changed_sockets
			# and writes the changed sockets to that array we have to copy the allsocket array
			# to keep our connected sockets list
			$changed_sockets = $this->allsockets;
		
			# blocks execution until data is received from any socket
			$write=NULL;
			$exceptions=NULL;
			$num_sockets = socket_select($changed_sockets,$write,$exceptions,NULL);
						
			# foreach changed socket...
			foreach( $changed_sockets as $socket )
			{
				# master socket changed means there is a new socket request
				if( $socket==$this->master )
				{
					# if accepting new socket fails
					if( ($client=socket_accept($this->master)) < 0 )
					{
						console('socket_accept() failed: reason: ' . socket_strerror(socket_last_error($client)));
						continue;
					}
					# if it is successful push the client to the allsockets array
					else
					{
						$this->allsockets[] = $client;

						# using array key from allsockets array, is that ok?
						# i want to avoid the often array_search calls
						$socket_index = array_search($client,$this->allsockets);
						$this->clients[$socket_index] = new stdClass;
						$this->clients[$socket_index]->socket_id = $client;

						$this->console($client . ' CONNECTED!');
					}
				}
				# client socket has sent data
				else
				{
					$this->console($client . 'atempting to send data');
					$socket_index = array_search($socket,$this->allsockets);
					$bytes = @socket_recv($socket,$buffer,2048,0);
					
					# the client status changed, but theres no data ---> disconnect
					
					if( $bytes === 0 )
					{
						//$this->console("Before socket falls over");
						$this->console($client . 'BYTES EQUALS ZERO!');
						$this->disconnected($socket);
					}
					# there is data to be read
					else
					{
						
						# this is a new connection, no handshake yet
						if( !isset($this->handshakes[$socket_index]) )
						{
							$this->do_handshake($buffer,$socket,$socket_index);
							
							/*
							for($i = 1; $i < $this->clients.length; $i++) 
							{
								$msg = $this->mask("{\"type\":\"add\", \"id\":" . $i . "}");
								$this->send($socket, $msg);
							}
							*/
							
							/*
							for ($i = 0; $i < $this->clients.length; $i++) 
							 {
							 	//and send to any that aren't the master or the sender
								if($this->clients[$i] != $this->master && $recipientClient != $socket)
								{
									$msg = $this->mask("{\"type\":\"add\", \"id\":" . $i . "}");
									$this->send($recipientClient, $msg);
								}
								//$this->interpretAndSend($recipientClient , $msg ,$socket_index);
								
							 }
							*/	
							
							//$this->send($socket,"Socket ID=" . $socket_index);
						}						
						# handshake already done, read data
						else
						{
							 $message = $this->handle_data($buffer);
							 
							 if($message == "NEW")
							 {
								$msg = $this->mask("{\"type\":\"add\", \"id\":" . $socket_index . "}");
								$this->console($client . 'NEW USER');
								foreach ($this->allsockets as $recipientClient) 
								{
									//and send to any that aren't the master or the sender
									if($recipientClient != $this->master && $recipientClient != $socket)
									$this->send($recipientClient, $msg);
									
								 }
							 }
							 else
							 {
								 //$this->console("Data received unmasked:" . $this->handle_data($buffer));
								 //loop through all the open sockets
								 foreach ($this->allsockets as $recipientClient) 
								 {
									//and send to any that aren't the master or the sender
									if($recipientClient != $this->master && $recipientClient != $socket)
									$this->interpretAndSend($recipientClient ,$buffer ,$socket_index);
								 }
							 }
						}
					}
				}
			}
		}
	}

	
	private function wrap($msg="") { return chr(0).$msg.chr(255); }
    private function unwrap($msg="") { return substr($msg, 1, strlen($msg)-2); }
	
	/**
	 * Remove masking from data see http://tools.ietf.org/html/draft-ietf-hybi-thewebsocketprotocol-08#section-4.3
	 * 
	 * @param string $data The received stream to be decoded 
	 */
	private function handle_data($data)
	{
	    
		$bytes = $data;
	    $data_length = "";
	    $mask = "";
	    $coded_data = "" ;
	    $decoded_data = "";        
	    $data_length = $bytes[1] & 127;
	    if($data_length === 126){
	       $mask = substr($bytes, 4, 8);
	       $coded_data = substr($bytes, 8);
	    }else if($data_length === 127){
	        $mask = substr($bytes, 10, 14);
	        $coded_data = substr($bytes, 14);
	    }else{
	        $mask = substr($bytes, 2, 6);
	        $coded_data = substr($bytes, 6);
	    }
	    for($i=0;$i<strlen($coded_data);$i++){
	        $decoded_data .= $coded_data[$i] ^ $mask[$i%4];
	    }
	    
	    return $decoded_data;
		
		//return $data;
	}
	
	/**
	 * TODO: Test this function 
	 * Add frame and masking from data see http://tools.ietf.org/html/draft-ietf-hybi-thewebsocketprotocol-08#section-4.3
	 * 
	 * @param string $data The received stream to be encoded 
	 */
	protected function mask($data)
	{
	  
	    $frame = Array();
	    $encoded = "";
	    $frame[0] = 0x81;
	    $data_length = strlen($data);
	
	    if($data_length <= 125){
	        $frame[1] = $data_length;    
	    }else{
	        $frame[1] = 126;  
	        $frame[2] = $data_length >> 8;
	        $frame[3] = $data_length & 0xFF; 
	    }
	
	    for($i=0;$i<sizeof($frame);$i++){
	        $encoded .= chr($frame[$i]);
	    }
	
	    $encoded .= $data;
	    return $encoded;  
		
		//return $data;
	}
	
	
	
	/**
	 * Manage the handshake procedure
	 *
	 * @param string $buffer The received stream to init the handshake
	 * @param socket $socket The socket from which the data came
	 * @param int $socket_index The socket index in the allsockets array
	 */
	private function do_handshake($buffer,$socket,$socket_index)
	{
		$this->console($buffer);
		//list($resource,$host,$origin) = $this->getheaders($buffer);
		list($resource, $headers, $code, $flashReq) = $this->handleRequestHeader($buffer);
		
		$upgrade = '';
	 	 //two keys for Protocol 00-05 - safari etc
	 	if (isset($headers['Sec-WebSocket-Key1']) && isset($headers['Sec-WebSocket-Key2'])) 
        {
        	$this->clients[$socket_index]->client_type = ClientTypes::PROTO_00;
        	$upgrade = $this->handshake00($socket, $resource, $headers, $code);
        }
        else if(isset($headers['Sec-WebSocket-Key'])) //one key for Protocol 06+ - chrome etc
        {
        	$this->clients[$socket_index]->client_type = ClientTypes::PROTO_06;
        	$upgrade = $this->handshake06($resource, $headers);
        }
        else if($flashReq) //It's a Flash client requesting security policy
        {
        	$this->console('Sending flash security policy');	
        	//$this->clients[$socket_index]->client_type = ClientTypes::FLASH;
        	$upgrade = $this->flashPolicy . "\0";
        }
        else if(isset($headers['Flash'])) //It's a flash client
        {
        	$this->clients[$socket_index]->client_type = ClientTypes::FLASH;
        	$upgrade = "Connected \r\n" . "\0";
        }
        
        $this->handshakes[$socket_index] = true;
		$this->console('Handshake=' . $upgrade);
		socket_write($socket,$upgrade,strlen($upgrade));

		$this->console('Done handshaking...');		
		
	}
	
	/**
	 * Manage the handshake procedure for Websocket Protocol 00
	 * http://tools.ietf.org/html/draft-ietf-hybi-thewebsocketprotocol-00 
	 * currently used by Safari 5
	 */
	
	protected function handshake00($socket, $resource, $headers,$code)
	{
		$securityResponse00 = '';
		//TODO: red from the socket again to get the code that comes after the headers
		$this->console("CODE=" . $code);
		
		if(strlen($code) != socketWebSocket::CODE_LENGTH) 
		{
			$this->console("Trying to read again!");
			@socket_recv($socket,$code,2048,0);
		}
		//$this->console("Security Code=" . $buffer);		
		$securityResponse00 = $this->getHandshakeSecurityKeys($headers['Sec-WebSocket-Key1'], $headers['Sec-WebSocket-Key2'], $code);
    	$origin = $headers['Origin']; //for Protocol 00 - safari etc
		$upgrade  = "HTTP/1.1 101 WebSocket Protocol Handshake\r\n" .
                "Upgrade: WebSocket\r\n" .
                "Connection: Upgrade\r\n" .
                "Sec-WebSocket-Origin: " . $origin . "\r\n" .
                "Sec-WebSocket-Location: ws://" . $headers['Host'] . $resource . "\r\n" .
                "\r\n". $securityResponse00;
          
        $this->console('RESPONSE HEADER 00: ' . $upgrade);  
		return $upgrade;
	}
	
	/**
	 * Manage the handshake procedure for Websocket Protocol 06-09
	 * //http://tools.ietf.org/html/draft-ietf-hybi-thewebsocketprotocol-06 
	 * (06 - 09 use the same handshake and masking) currently used by Chrome 15
	 * 
	 */
	
	protected function handshake06($resource, $headers)
	{
		$securityResponse06 = '';
		$securityResponse06 = $this->getHandshakeSecurityKey($headers['Sec-WebSocket-Key']);
		$origin = $headers['Sec-WebSocket-Origin']; //for Protocol 06-09 - chrome etc
		$upgrade  = "HTTP/1.1 101 Switching Protocols\r\n" .
                "Upgrade: websocket\r\n" .
                "Connection: Upgrade\r\n" .                 
        		"Sec-WebSocket-Accept:" . $securityResponse06 .
        	"\r\n" . "\r\n";  
    	 $this->console('RESPONSE HEADER 06: ' . $upgrade);
    	 return $upgrade; 	
	}
	
	
	/**
	 * Send differently formatted data between clients 
	 *
	  * @param socket $client The socket to which we send data
	 *  @param string $msg  The message we send
	 *  @param int $socket_index  The index of the socket sending the data
	 */

	protected function interpretAndSend($client,$msg, $socket_index)
	{
		
		$client_index = array_search($client,$this->allsockets);
			
		if($this->clients[$socket_index]->client_type == ClientTypes::PROTO_00)
		{ 
			
			//if sender is the same type as receiver - just send data
			if($this->clients[$client_index]->client_type == ClientTypes::PROTO_00)
			{
				
				$this->send($client,$msg);
			}	
			else if($this->clients[$client_index]->client_type == ClientTypes::PROTO_06)
			{
				//unwrap data and then mask it before sending	
				
				$msg = $this->unwrap($msg);
				$msg = $this->mask($msg);				
				$this->console($msg);
				$this->send($client,$msg);
			}
			else if($this->clients[$client_index]->client_type == ClientTypes::FLASH)
			{
				//unwrap data and then mask it before sending	
				$msg = $this->unwrap($msg);
				$this->console($msg);
				$this->send($client, $msg . "\0");
				
			}
		}		
		else if ($this->clients[$socket_index]->client_type == ClientTypes::PROTO_06)
		{
			
			if($this->clients[$client_index]->client_type == ClientTypes::PROTO_00)
			{
				//unmask data and then wrap it before sending	
				$msg = $this->handle_data($msg);
				$msg = $this->wrap($msg);
				$this->console("We are not the same:" . $msg);
				$this->send($client,$msg);
			}//if sender is the same type as receiver - just send data
			else if($this->clients[$client_index]->client_type == ClientTypes::PROTO_06)
			{
				$this->console("We are the same:" . $msg);
				
				//NEW
				$msg = $this->handle_data($msg);
				$msg = $this->mask($msg);
				//NEW
				
				if($this->send($client,$msg))
				{
					$this->console("Send returning true");
				}
				else
				{
					$this->console("Send returning false");
				}
			}
			else if($this->clients[$client_index]->client_type == ClientTypes::FLASH)
			{
				$msg = $this->handle_data($msg);
				$this->console($msg);
				$this->send($client,$msg . "\0");
			}
		}
		else if($this->clients[$socket_index]->client_type == ClientTypes::FLASH)
		{
			//if sender is the same type as receiver - just send data
			if($this->clients[$client_index]->client_type == ClientTypes::PROTO_00)
			{
				$msg = $this->wrap($msg);
				$this->console($msg);
				$this->send($client,$msg);
			}			
			else if($this->clients[$client_index]->client_type == ClientTypes::PROTO_06)
			{
				$msg = $this->mask($msg);				
				$this->console($msg);
				$this->send($client,$msg);
			}
			else if($this->clients[$client_index]->client_type == ClientTypes::FLASH)
			{
				$this->send($client, $msg);
				
			}
			
		}
		
		//$this->send($client, $msg);
		
	}
	
	
	
	/**
	 * Extends the socket class send method to send WebSocket messages
	 *
	 * @param socket $client The socket to which we send data
	 * @param string $msg  The message we send
	 */
	protected function send($client,$msg)
	{
										
		$this->console("we are sending");
		$this->console(">{$msg}");

		//return parent::send($client,chr(0).$msg.chr(255));
		return parent::send($client,$msg);
	}
	
	
	

	/**
	 * Disconnects a socket an delete all related data
	 *
	 * @param socket $socket The socket to disconnect
	 */
	private function disconnected($socket)
	{
		$index = array_search($socket, $this->allsockets);
		if( $index >= 0 )
		{
			unset($this->allsockets[$index]);
			unset($this->clients[$index]);
			unset($this->handshakes[$index]);
		}

		socket_close($socket);
		$this->console($socket." disconnected!");
		$errorcode = socket_last_error();
   		$errormsg = socket_strerror($errorcode); 
   		$this->console($errormsg);
	}

	/**
	 * Parse the handshake header from the client
	 *
	 * @param string $request
	 * @return array resource,headers
	 */
		
 	private function handleRequestHeader($request) {
        $resource = $code = null;
        $flashReq = false;
        preg_match('/GET (.*?) HTTP/', $request, $match) && $resource = $match[1];
        preg_match("/\r\n(.*?)\$/", $request, $match) && $code = $match[1];
        if (preg_match("<policy-file-request/>", $request)) $flashReq = true;
       
        $headers = array();
        
        foreach(explode("\r\n", $request) as $line) {
            if (strpos($line, ': ') !== false) {
                list($key, $value) = explode(': ', $line);
                $headers[trim($key)] = trim($value);
            }           
        }
        return array($resource, $headers, $code, $flashReq);
    }

	/**
	 * Extends the parent console method.
	 * For now we just set another type.
	 *
	 * @param string $msg
	 * @param string $type
	 */
	protected function console($msg,$type='WebSocket')
	{
		parent::console($msg,$type);
	}
	
	private function handleSecurityKey($key) {
        preg_match_all('/[0-9]/', $key, $number);
        preg_match_all('/ /', $key, $space);
        if ($number && $space) {
            return implode('', $number[0]) / count($space[0]);
        }
        return '';
    } 

    private function getHandshakeSecurityKey($key) {
      /*
       * The client sends a Sec-WebSocket-Key which is base64 encoded. 
       * To this key the magic string (GUID) "258EAFA5-E914-47DA-95CA-C5AB0DC85B11" is appended, hashed with SHA1 and then base64 encoded. 
       * Notice that the Sec-WebSocket-Key is base64 encoded but is not decoded by the server.
       */
    	
    	$magicString = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";
    	$key = $key . $magicString;
		$reply = base64_encode(sha1($key, true));
    	return $reply;
    }   
    
    
    private function getHandshakeSecurityKeys($key1, $key2, $code) {
         return md5(
            pack('N', $this->handleSecurityKey($key1)).
            pack('N', $this->handleSecurityKey($key2)).
            $code,            
            true
        );  
            
    }    
	
}

?>