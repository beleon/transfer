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
	],
	[
	    'name' => 'transfer#prepare',
	    'url' => 'ajax/prepare.php',
	    'verb' => 'POST'
	],
	[
	    'name' => 'transfer#start',
	    'url' => 'ajax/start.php',
	    'verb' => 'POST'
	],
	[
	    'name' => 'transfer#progress',
	    'url' => 'ajax/progress.php',
	    'verb' => 'GET'
	],
	[
	    'name' => 'transfer#cancel',
	    'url' => 'ajax/cancel.php',
	    'verb' => 'POST'
	]
    ]
];
