<pre><?php
include("iCloudCalView.php");

$iCloud = new iCloudCalView('you@eksempel.dk', 'SecretPassword');

$cals = $iCloud->getCals();

$events = $iCloud->getEvents($cals[0]["href"], mktime(0,0,0,1,1,2012), mktime());

print_r($cals);

print_r($events);

