<?php
return [
    'routes' => [
	[
	    'name' => 'transfer#transfer',
	    'url' => 'ajax/transfer.php',
	    'verb' => 'POST'
	],
	[
	    'name' => 'transfer#probe',
	    'url' => 'ajax/probe.php',
	    'verb' => 'GET'
	]
    ]
];
