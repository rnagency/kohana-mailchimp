<?php defined('SYSPATH') or die('No direct script access.');

class Mailchimp_Core extends MCAPI {

	protected static $_instance = array();
	public $api_key;
	public $errorMessage;
    public $errorCode;


	/**
	* Singleton pattern
	*
	* @return Mailchimp_Core
	*/
	public static function instance($apikey = NULL, $secure = NULL)
	{
		if ($apikey === NULL)
		{
			$apikey = Kohana::$config->load('mailchimp.apikey');
		}

		if ($secure === NULL)
		{
			$secure = Kohana::$config->load('mailchimp.secure');
		}
		
		if ( ! isset(Mailchimp_Core::$_instance[$apikey]))
		{
			// Create a new session instance
			Mailchimp_Core::$_instance[$apikey] = new Mailchimp_Core($apikey, $secure);
		}

		return Mailchimp_Core::$_instance[$apikey];
	}

	public function listMembersBySegment($list_id, $segment_id, $status='subscribed')
	{
		// Build the url to the export API
		$dc = "us1";
	    if (strstr($this->api_key,"-")){
        	list($key, $dc) = explode("-",$this->api_key,2);
            if (!$dc) $dc = "us1";
        }
        $host = $dc.".api.mailchimp.com/export/1.0/list?";
		$params['apikey'] = $this->api_key;
		$params['id'] = $list_id;
		$params['status'] = $status;
		$params['segment'] = array(
			'match' => 'all',
			'conditions' => array(
				array (
					'field' => 'static_segment',
					'op' => 'eq',
					'value' => $segment_id)));

		// Work out the url (check for secure too)
		if ($this->secure)
		{
			$url = 'https://'.$host.http_build_query($params);
		}
		else
		{
			$url = 'http://'.$host.http_build_query($params);
		}

		$content = file_get_contents($url);
		$exploded = explode("\n",$content);
		
		// Check for errors (will be in first item)
		$keys = json_decode($exploded[0], TRUE);
		if (array_key_exists('error', $keys))
		{
			$this->errorMessage = $keys['error'];
			$this->errorCode = $keys['code'];
			return FALSE;
		}

		// We need to take the items in the array and json_decode each line. We
		// also need to get rid of the first item (its the field labels)
		array_shift($exploded);
		$members = array();
		foreach ($exploded as $member)
		{
			if (($values = json_decode($member, TRUE)) === NULL) continue;
			$members[] = array_combine($keys, $values);
		}
		return $members;
	}
}
