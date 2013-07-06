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
		preg_match('/(\d+)$/', $item['@id'], $matches);
		list(, $id) = $matches;
		print "$id\n";

		$body = json_encode(array(
			'url' => $item['_links']['alternate']['application/xml']['href'],
			'token' => 'abcd',
		));

		$options = array(
			'http' => array(
				'method' => 'PUT',
				'header' => "Content-Type: application/json\r\nContent-Length: " . strlen($body),
				'content' => $body,
			)
		);

		$docURL = 'http://localhost:1441/documents/' . $id;
		print "$docURL\n";
		$result = file_get_contents($docURL, NULL, stream_context_create($options));
		print_r($result);
	}

	$url = $data['_links']['next']['href'];
} while ($url);