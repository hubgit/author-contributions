<?php

define(INPUT_DIR, __DIR__ . '/../../data/parsed/contributions/');
define(OUTPUT_DIR, INPUT_DIR);

if (!file_exists(OUTPUT_DIR)) {
	mkdir(OUTPUT_DIR, 0777, true);
}

$input = fopen(INPUT_DIR . 'peerj_author_contributions.csv', 'r');
$fields = fgetcsv($input);

$output = fopen(OUTPUT_DIR . 'peerj_countries.tsv', 'w');
fputcsv($output, array('country', 'articles'));

$counts = array();

while (($row = fgetcsv($input)) !== false) {
	$data = array_combine($fields, $row);

	$doi = $data['doi'];

	$countries = preg_split('/;\s+/', $data['countries']);

	foreach ($countries as $country) {
		$counts[$country][$doi] = 1;
	}
}

foreach ($counts as $country => $dois) {
	fputcsv($output, array($country, count($dois)), "\t");
}