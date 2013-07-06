<?php

define(INPUT_DIR, __DIR__ . '/../../data/original/articles/peerj/');
define(OUTPUT_DIR, __DIR__ . '/../../data/parsed/contributions/');

if (!file_exists(OUTPUT_DIR)) {
	mkdir(OUTPUT_DIR, 0777, true);
}

$output = fopen(OUTPUT_DIR . 'peerj_author_contributions.csv', 'w');
fputcsv($output, array('doi', 'position', 'name', 'initials', 'conceived', 'performed', 'analyzed', 'contributed', 'wrote', 'countries', 'affiliations', 'text'));

$contributionTypes = array(
	'conceived' => 'conceived and designed the experiments',
	'performed' => 'performed the experiments',
	'analyzed' => 'analyzed the data',
	'contributed' => 'contributed reagents/materials/analysis tools',
	'wrote' => 'wrote the paper',
);

$files = glob(INPUT_DIR . '*.xml');

foreach ($files as $file) {
	$dom = new DOMDocument;
	$dom->preserveWhiteSpace = false;
	$dom->validateOnParse = true;
	$dom->load($file, LIBXML_DTDLOAD);
	$xpath = new DOMXPath($dom);

	$doiNodes = $xpath->query('front/article-meta/article-id[@pub-id-type="doi"]');
	$doi = $doiNodes->item(0)->textContent;

	$contributionNodes = $xpath->query('//fn-group[@content-type="author-contributions"]/fn/p');
	printf("%d contribution nodes in %s\n", $contributionNodes->length, $file);

	if (!$contributionNodes->length) {
		continue;
	}

	foreach ($contributionNodes as $contributionNode) {
		$fullContributionText = $contributionNode->nodeValue;

		$contribRidNodes = $xpath->query('xref[@ref-type="contrib"]/@rid', $contributionNode);
		foreach ($contribRidNodes as $contribRidNode) {
			$authorNode = $dom->getElementById($contribRidNode->nodeValue);

			/* get the author position by counting the number of previous siblings, assuming they are also authors */
			$position = 1;
			$sibling = $authorNode;
			while ($sibling = $sibling->previousSibling) {
				$position++;
			}

			$contribution = array(
				'doi' => $doi,
				'position' => $position,
				'name' => array(),
				'initials' => '',
				'conceived' => 0,
				'performed' => 0,
				'analyzed' => 0,
				'contributed' => 0,
				'wrote' => 0,
				'countries' => array(),
				'affiliations' => array(),
				'text' => $fullContributionText,
			);

			foreach ($contributionTypes as $contributionType => $contributionText) {
				if (stripos($fullContributionText, $contributionText) !== false) {
					$contribution[$contributionType] = 1;
				}
			}

			$givenNameNodes = $xpath->query('name/given-names', $authorNode);
			if ($givenNameNodes->length) {
				$contribution['name'][] = $givenNameNodes->item(0)->textContent;
			}

			$surnameNodes = $xpath->query('name/surname', $authorNode);
			if ($surnameNodes->length) {
				$contribution['name'][] = $surnameNodes->item(0)->textContent;
			}

			$affRidNodes = $xpath->query('xref[@ref-type="aff"]/@rid', $authorNode);
			if ($affRidNodes->length) {
				foreach ($affRidNodes as $affRidNode) {
					$affNode = $dom->getElementById($affRidNode->nodeValue);

					$labelNode = $xpath->query('label', $affNode)->item(0);
					if ($labelNode) {
						$affNode->removeChild($labelNode);
					}

					$contribution['affiliations'][] = $affNode->textContent;

					$countryNodes = $xpath->query('country', $affNode);
					if ($countryNodes->length) {
						$contribution['countries'][] = $countryNodes->item(0)->textContent;
					}
				}
			}

			$contribution['name'] = implode(' ', $contribution['name']);
			$contribution['countries'] = implode('; ', array_unique($contribution['countries']));
			$contribution['affiliations'] = implode('; ', $contribution['affiliations']);

			fputcsv($output, $contribution);
		}
	}
}