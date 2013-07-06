<?php

define(OUTPUT_DIR, __DIR__ . '/../../data/original/articles/peerj/');

if (!file_exists(OUTPUT_DIR)) {
	mkdir(OUTPUT_DIR, 0777, true);
}

$url = 'https://peerj.com/articles/index.json';

do {
	print "$url\n";
	$data = json_decode(file_get_contents($url), true);

	foreach ($data['_embedded'] as $item) {
		$command = sprintf('wget %s --continue --header="Accept-Encoding: gzip" --output-document %s',
			escapeshellarg($item['_links']['alternate']['application/xml']['href']),
			escapeshellarg(OUTPUT_DIR . base64_encode($item['@id']) . '.xml'));
		exec($command);
	}

	$url = $data['_links']['next']['href'];
} while ($url);