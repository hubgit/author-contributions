<?php

define(OUTPUT_DIR, __DIR__ . '/../../data/original/articles/plos-one/');

if (!file_exists(OUTPUT_DIR)) {
	mkdir(OUTPUT_DIR, 0777, true);
}

$params = array(
	'db' => 'pmc',
	'term' => '"PLOS ONE"[Journal]',
	'retmode' => 'xml',
	'usehistory' => 'y',
	'retmax' => 0,
	'retmode' => 'xml',
);

$url = 'http://eutils.be-md.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?' . http_build_query($params);
print "$url\n";

$dom = new DOMDocument;
$dom->load($url);

$count = $dom->getElementsByTagName('Count')->item(0)->textContent;
print "$count articles\n";

$queryKey = $dom->getElementsByTagName('QueryKey')->item(0)->textContent;
$webEnv = $dom->getElementsByTagName('WebEnv')->item(0)->textContent;

$limit = 100;

for ($offset = 0; $offset <= $count; $offset += $limit) {
	$params = array(
		'db' => 'pmc',
		'query_key' => $queryKey,
		'webenv' => $webEnv,
		'retstart' => $offset,
		'retmax' => $limit,
		'retmode' => 'xml',
		'rettype' => 'uilist',
	);

	$url = 'http://eutils.be-md.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?' . http_build_query($params);
	$dom = new DOMDocument;
	$dom->load($url);

	foreach ($dom->getElementsByTagName('Id') as $idNode) {
		$id = (int) $idNode->textContent;
		$url = 'http://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?db=pmc&id=' . $id;

		$command = sprintf('wget %s --continue --header="Accept-Encoding: gzip,deflate" --output-document %s',
			escapeshellarg($url),
			escapeshellarg(OUTPUT_DIR . $id . '.xml'));
		exec($command);
	}
}
