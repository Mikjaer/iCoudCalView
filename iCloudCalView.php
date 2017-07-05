<?php

# Copyright (c) 2013 Mikkel Mikjaer Christensen
# Released as is with no waranty according to GPL 3.0
# http://www.gnu.org/licenses/gpl.html

class iCloudCalView
{
	protected $username;
	protected $password;
	protected $uid;
	protected $server = "https://p01-caldav.icloud.com"; 	// Somebody claims that it's important to use the right server-numer
								// For me "p01" has worked every single time, however you can use
								// p01 - p09
	
	public function __construct($username, $password)
	{
		$this->username = $username;
		$this->password = $password;
		
		$this->uid 	= $this->fetchUid($username,$password);
	
	}


	private function fetchUid($user,$pw)
	{
		$principal_request="<A:propfind xmlns:A='DAV:'>
		<A:prop>
		<A:current-user-principal/>
		</A:prop>
		</A:propfind>";
		$url = $this->server;
		$response=$this->execRequest($url, $principal_request, "PROPFIND", 1);
		$uid = (String)$response->response->propstat->prop->{"current-user-principal"}->href;
		$userID = explode("/", $uid);
		return $userID[1];
	}

	public function getUid()
	{
		return $this->uid;
	}

	public function getCals()
	{
		$calendars_request="<A:propfind xmlns:A='DAV:'>
		<A:prop>
		<A:displayname/>
		</A:prop>
		</A:propfind>";
		
		$url=$this->server."/".$this->uid."/calendars/";
		$response=$this->execRequest($url, $calendars_request, "PROPFIND", 1);
		
		$calendars=array();
		foreach($response->response as $cal)
		{
			unset($href,$entry);
			$href=(String)$cal->href;
			$href=split("/",$href);
			$href=$href[3];

			$entry["href"]=$href;
			$entry["name"]=(String)$cal->propstat[0]->prop[0]->displayname;
			if ($entry["href"] != "")
				$calendars[]=$entry;
		}
		return $calendars;
	}


	protected function execRequest($url, $xml, $type, $depth)
	{
		$c=curl_init($url);
		curl_setopt($c, CURLOPT_HTTPHEADER, array("Depth: $depth", "Prefer: return-minimal", "Content-Type: application/xml; charset='UTF-8'"));
		curl_setopt($c, CURLOPT_HEADER, 0);
		curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($c, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($c, CURLOPT_USERPWD, $this->username.":".$this->password);
		curl_setopt($c, CURLOPT_CUSTOMREQUEST, $type);
		curl_setopt($c, CURLOPT_POSTFIELDS, $xml);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
		
		$data=curl_exec($c);
		curl_close($c);

		return simplexml_load_string($data);
	}



	public function loadEvents($url, $fromDate, $toDate)
	{
		$strFromDate = strftime("%Y%m%dT%H%M%SZ", $fromDate);
		$strToDate = strftime("%Y%m%dT%H%M%SZ", $toDate);
		$principal_request=' 
			   <C:calendar-query xmlns:D="DAV:"
		                 xmlns:C="urn:ietf:params:xml:ns:caldav">
			     <D:prop>
			       <D:getetag/>
			       <C:calendar-data />
			     </D:prop>
			     <C:filter>
			       <C:comp-filter name="VCALENDAR">
			         <C:comp-filter name="VEVENT">
		           <C:time-range start="' . $strFromDate . '"
		                         end="' . $strToDate . '"/>
			         </C:comp-filter>
			       </C:comp-filter>
			     </C:filter>
			   </C:calendar-query>
		';
		return $this->execRequest($url, $principal_request, 'REPORT', 1);
	}

	private function loadIcs($url, $ics)
	{
		$server = preg_split("/\//",$url,null,PREG_SPLIT_NO_EMPTY);
		$url="https://".$server[1].$ics;

		$c=curl_init($url);
		curl_setopt($c, CURLOPT_HTTPHEADER, array("Depth: 1", "Prefer: return-minimal", "Content-Type: application/xml; charset='UTF-8'"));
		curl_setopt($c, CURLOPT_HEADER, 0);
		
		curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 0);
		
		curl_setopt($c, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($c, CURLOPT_USERPWD, $this->username.":".$this->password);
		
		curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
		
		$data=curl_exec($c);
		curl_close($c);
		$data = preg_split("/\n/",$data);
		foreach ($data as $dat)
		{
			$key=substr($dat,0,strpos($dat,":"));
			$value=substr($dat,strpos($dat,":")+1);
			$ret[$key]=$value;
		}
		return $ret;
	}

	public function getEvents($cal, $fromDate, $toDate)
	{
		$i=0;
		$url = $this->server."/".$this->uid."/calendars/".$cal."/";
		$events = $this->loadEvents($url,$fromDate,$toDate);
		foreach ($events as $event)
		{
			$ret[]=$this->loadIcs($url,(String)$event->href);
		}
		return $ret;
	}
}

?>
