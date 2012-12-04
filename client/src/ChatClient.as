
package {
	
	import flash.display.MovieClip;
	import flash.events.KeyboardEvent;
	import XMLSocketClient;
	
	public class ChatClient extends MovieClip {
		
		var _socket:XMLSocketClient;
	
		public function ChatClient() 
		{
			_socket = new XMLSocketClient(this);
			stage.addEventListener(KeyboardEvent.KEY_DOWN, displayKey);
			
		}
		
		function update(msg:String):void
		{
			chatTxt.appendText("Received: " + msg + "\r\n");
			
		}
		
		function displayKey(keyEvent:KeyboardEvent):void 
		{
			if(keyEvent.keyCode == 13)
			{
				var msg:String =  messageTxt.text;
				if(msg != "")
				{
					chatTxt.appendText("Sent: " + msg + "\r\n");
					_socket.send(msg);
					messageTxt.text = "";
				}
				
			}
			
		}
		
	}
}
