<?php

class EasyImap
{

  const TIMEOUT_CONNECTION = 30;
  protected $_socket;
  protected $_current_folder = null;
  protected $_tagCount = 0;

  function __construct($host = '', $port = null, $ssl = false){
    if ($host) {
      $this->connect($host, $port, $ssl);
    }
  }


  public function __destruct(){
    $this->logout();
  }
    
  public function logout(){
    $result = false;
    if ($this->_socket) {
      try {
        $result = $this->send('LOGOUT', array(), true);
      } catch (Exception $e) {}
      fclose($this->_socket);
      $this->_socket = null;
    }
    return $result;
  }

  public function connect($host, $port = null, $ssl = false){
    if ($ssl == 'SSL') {
      $host = 'ssl://' . $host;
    }

    if ($port === null) {
      $port = $ssl === 'SSL' ? 993 : 143;
    }

    $errno  =  0;
    $errstr = '';
    $this->_socket = @fsockopen($host, $port, $errno, $errstr, self::TIMEOUT_CONNECTION);
    if(!$this->_socket)
      return false;
    stream_set_timeout($this->_socket, 60);

  }

  protected function _nextLine(){
	  
    $line = @fgets($this->_socket);
    if ($line === false) {
      echo('cannot read - connection closed?');
    }

    return $line;
  }


  protected function _assumedNextLine($start){
    $line = $this->_nextLine();
    return strpos($line, $start) === 0;
  }

  protected function _nextTaggedLine(&$tag){
    $line = $this->_nextLine();
    $out = explode(' ', $line, 2);
    $tag = (isset($out[0]))?$out[0]:null;
    $line= (isset($out[1]))?$out[1]:null;
    if(!isset($tag, $line))
       throw new Exception('Got Empty response');
    
    return $line;
  }


  protected function _decodeLine($line){
    $tokens = array();
    $stack = array();

    $line = rtrim($line) . ' ';
    while (($pos = strpos($line, ' ')) !== false) {
      $token = substr($line, 0, $pos);
      while ($token[0] == '(') {
        array_push($stack, $tokens);
        $tokens = array();
        $token = substr($token, 1);
      }
      if ($token[0] == '"') {
        if (preg_match('%^\(*"((.|\\\\|\\")*?)" *%', $line, $matches)) {
          $tokens[] = $matches[1];
          $line = substr($line, strlen($matches[0]));
          continue;
        }
      }
      
      if ($token[0] == '{') {
        $endPos = strpos($token, '}');
        $chars = substr($token, 1, $endPos - 1);
         if (is_numeric($chars)) {
           $token = '';
           while (strlen($token) < $chars) {
             $token .= $this->_nextLine();
           }
           $line = '';
           if (strlen($token) > $chars) {
             $line = substr($token, $chars);
             $token = substr($token, 0, $chars);
           } else {
             $line .= $this->_nextLine();
           }
           $tokens[] = $token;
           $line = trim($line) . ' ';
           continue;
         }
       }
       
      if ($stack && $token[strlen($token) - 1] == ')') {
        $braces = strlen($token);
        $token = rtrim($token, ')');
        $braces -= strlen($token) + 1;
        if (rtrim($token) != '') {
          $tokens[] = rtrim($token);
        }
         
        $token = $tokens;
        $tokens = array_pop($stack);
        while ($braces-- > 0) {
          $tokens[] = $token;
          $token = $tokens;
          $tokens = array_pop($stack);
        }
      }
      $tokens[] = $token;
      $line = substr($line, $pos + 1);
    }

    while ($stack) {
      $child = $tokens;
      $tokens = array_pop($stack);
      $tokens[] = $child;
    }

    return $tokens;
  }


  public function readLine(&$tokens = array(), $wantedTag = '*', $dontParse = false){
    $line = $this->_nextTaggedLine($tag);
    if (!$dontParse) {
      $tokens = $this->_decodeLine($line);
    } else {
      $tokens = $line;
    }

    return $tag == $wantedTag;
  }

  public function readResponse($tag, $dontParse = false){
		
    $lines = array();
    while (!$this->readLine($tokens, $tag, $dontParse)) {
      $lines[] = $tokens;
    }
    
    if ($dontParse) {
      $tokens = array(substr($tokens, 0, 2));
    }
    
    if ($tokens[0] == 'OK') {
      return $lines ? $lines : true;
    } else if ($tokens[0] == 'NO'){
      return false;
    }
    return null;
  }


  public function sendRequest($command, $tokens = array(), &$tag = null){
    if (!$tag) {
      ++$this->_tagCount;
      $tag = 'TAG' . $this->_tagCount;
    }
    
    $line = $tag . ' ' . $command;

    foreach ($tokens as $token){
      $line .= ' ' . $token;
    }
    
    
    if (@fputs($this->_socket, $line . "\r\n") === false) {
      echo('cannot write - connection closed?');
    }
  }

  public function send($command, $tokens = array(), $dontParse = false){
    $this->sendRequest($command, $tokens, $tag);
    $response = $this->readResponse($tag, $dontParse);

    return $response;
  }


  public function escapeString($string){
    if (func_num_args() < 2) {
      if (strpos($string, "\n") !== false) {
        return array('{' . strlen($string) . '}', $string);
      } else {
        return '"' . str_replace(array('\\', '"'), array('\\\\', '\\"'), $string) . '"';
      }
    }
    
    $result = array();
    foreach (func_get_args() as $string) {
      $result[] = $this->escapeString($string);
    }
    return $result;
  }


  public function escapeList($list){
    $result = array();
    foreach ($list as $k => $v) {
      if (!is_array($v)) {
        $result[] = $v;
        continue;
      }
      $result[] = $this->escapeList($v);
    }
    if(count($result) > 1)
      return '(' . implode(' ', $result) . ')';
    else
      return $result[0];
  }

  public function select($box = 'INBOX', $type = 'EXAMINE'){
    $this->sendRequest($type, array($this->escapeString($box)), $tag);

    $result = array();
    while (!$this->readLine($tokens, $tag)) {
      if ($tokens[0] == 'FLAGS') {
        array_shift($tokens);
        $result['flags'] = $tokens;
        continue;
      }
      switch ($tokens[1]) {
        case 'EXISTS':
        case 'RECENT':
          $result[strtolower($tokens[1])] = $tokens[0];
          break;
        case '[UIDVALIDITY':
          $result['uidvalidity'] = (int)$tokens[2];
          break;
        default:
          // ignore
      }
    }

    if ($tokens[0] != 'OK') {
      return false;
    }
    
    return $result;    
        
  }


  public function search(array $params){
    $response = $this->send('uid SEARCH', $params);
    if (!$response) {
      return $response;
    }

    foreach ($response as $ids) {
      if ($ids[0] == 'SEARCH') {
        array_shift($ids);
        return $ids;
      }
    }
    return array();
  }
    
  public function fetch($items, $from, $to = null){
    if (is_array($from)) {
      $set = implode(',', $from);
      $set = $set;
    } else if ($to === null) {
      $set = (int)$from;
    } else if ($to === INF) {
      $set = (int)$from . ':*';
    } else {
      $set = (int)$from . ':' . (int)$to;
    }

    $items = (array)$items;
    $itemList = $this->escapeList($items);

    $this->sendRequest('uid FETCH', array($set, $itemList), $tag);

    $result = array();
    while (!$this->readLine($tokens, $tag)) {
      // ignore other responses
      if(isset($tokens[0]) && !isset($tokens[1]))
        $tokens = $tokens[0];

      if ($tokens[1] != 'FETCH') {
        continue;
      }
      // ignore other messages
      if ($to === null && !is_array($from) && $tokens[0] != $from) {
        continue;
      }
      if (count($items) == 1 && (strpos($items[0], 'peek') === 0)) {
        if ($tokens[2][0] == $items[0]) {
          $data = $tokens[2][1];
        } else {
          $count = count($tokens[2]);
          for ($i = 2; $i < $count; $i += 2) {
            if ($tokens[2][$i] != $items[0]) {
              continue;
            }
            $data = $tokens[2][$i + 1];
            break;
          }
        }
      } else {
        $data = array();
        while (key($tokens[2]) !== null) {
          $data[current($tokens[2])] = next($tokens[2]);
          next($tokens[2]);
        }
      }
      
      if ($to === null && !is_array($from) && $tokens[0] == $from) {
        while (!$this->readLine($tokens, $tag));
        return $data;
      }
      $result[$tokens[0]] = $data;
    }

    if ($to === null && !is_array($from)) {
      echo('the single id was not found in response');
    }

    return $result;
  }

    
  
  public function parseMessageFields($field_name, $field_value, $structure = array()){
		$result = array();
    $message_id = '';
    $subject = '';
    $from  = '';
		if(isset($structure['html']) && stripos($field_name, "BODY[".$structure['html']."]") !== false){
			$result = array('body_html' => quoted_printable_decode($field_value));
		}elseif(isset($structure['text']) && stripos($field_name, "BODY[".$structure['text']."]") !== false){
		  $result = array('body_text' => quoted_printable_decode($field_value));
		}elseif((stripos($field_value,'Subject:') !== false) || (stripos($field_value,'From:') !== false) || (stripos($field_value,'Message-ID:') !== false)){	
		  $decoded_headers = imap_rfc822_parse_headers($field_value);
		  $subject = isset($decoded_headers->subject) ? $decoded_headers->subject : '';
		  $message_id = isset($decoded_headers->message_id) ? $decoded_headers->message_id : '';
		  $from = isset($decoded_headers->fromaddress) ? $decoded_headers->fromaddress : '';
			$result = array('subject' => $subject, 'message_id' => $message_id, 'from' => $from);
		}
		  
		return $result;
		
	}
    
    
  public function parseMessage($message_data = array(), $message_structure_ids = array()){
	  $temp_result = array();
    foreach($message_data as $key => $value){
      $base_key = null;
      if(!is_array($value) && ($key == 'INTERNALDATE')){
        $temp_result['date'] = $value;
        continue;
      }

      if(!is_array($value)){
        $valid_field = $this->parseMessageFields($key, $value, $message_structure_ids);
        $temp_result =  array_merge($valid_field, $temp_result);
        continue;
      }
     
      foreach($value as $key2 => $value2){
		    $key2 = (is_null($base_key) ? $key2  : $base_key);
			  $valid_field = $this->parseMessageFields($key2, $value2, $message_structure_ids);				
				if(!empty($valid_field))
			    $temp_result =  array_merge($valid_field, $temp_result);
				  if(isset($message_structure_ids['html']) && stripos($value2, "BODY[".$message_structure_ids['html']."]") !== false){
						$base_key = "BODY[".$message_structure_ids['html']."]";
				  }elseif(isset($message_structure_ids['text']) && stripos($value2, "BODY[".$message_structure_ids['text']."]") !== false){
					  $base_key =  "BODY[".$message_structure_ids['text']."]";
					}else{
						$base_key = null;
					}
					
		    }
	    }
	  return  $temp_result;
	}
    
  public function parseStructure($data, $body_structure = array(),$prefix = ''){
    foreach($data as $body_id => $type){
      if(is_array($type)){
				$b_id = $body_id+1;
				$p = (!empty($prefix)) ? "$prefix.$b_id" : $b_id;
			  $body_structure = $this->parseStructure($type, $body_structure, $p);
			}
			  if($type === 'HTML' && !isset($body_structure['html'])){
					if($body_id == 11)
					  $body_structure['html'] = (!empty($prefix)? "$prefix.2" : "2");
					else
				    $body_structure['html'] =   (!empty($prefix)? "$prefix.$body_id" : "$body_id");
			  }elseif($type === 'PLAIN' && !isset($body_structure['text'])){
				  $body_structure['text'] =   (!empty($prefix)? "$prefix.$body_id" : "$body_id");  
			  }
    }
    return $body_structure;
	}
  public function getMessage($ids = array(),$summary = true){
	  
	  $subject = '';
	  $from = '';
	  $body_html = '';
	  $body_text = '';
	  $result = array();
	  $mdata = null;
    $structure = array(); 

	  if($summary){
		  $params = array('body[header.fields (from Message-ID subject)]', "INTERNALDATE");
	    $mdata = $this->fetch($params, $ids);
	    foreach($mdata as $id => $message_data){
			  $result[$message_data['UID']] = $this->parseMessage($message_data);
	    }
		}else{
     	$data = $this->fetch(array('BODYSTRUCTURE'), $ids );
	    foreach($data as $id => $message_data){
        $s_data = (is_array($message_data['BODYSTRUCTURE'][0]) ? $message_data['BODYSTRUCTURE'][0] : $message_data['BODYSTRUCTURE']); 
	      $structure[$message_data['UID']] = $this->parseStructure($s_data);
	    }

			foreach($ids as $id){
				if(!isset($structure[$id]))
				  continue;
			  $message_structure = $structure[$id];
			  $html = isset($message_structure['html']) ? $message_structure['html'] : "2";
	      $plain = isset($message_structure['text']) ? $message_structure['text'] : "1";
		    $message_structure_ids = array('html' => $html, 'text' => $plain);
			  $params = array("body.peek[$html]", "body.peek[$plain]","body[header.fields (Message-ID from subject)]", "INTERNALDATE");
			  $mdata = $this->fetch($params, array($id));
			  foreach($mdata as $id => $message_data){
				  $result[$message_data['UID']] = $this->parseMessage($message_data, $message_structure_ids);	
				}
      }
		}
		
    return $result;
  }
    

}
