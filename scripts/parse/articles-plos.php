<?php

// note: add this to /etc/xml/catalog so that "id" is recognised as an id attribute
// http://dtd.nlm.nih.gov/ncbi/pmc/articleset/ => id    ID    #IMPLIED
// <rewriteURI uriStartString="http://dtd.nlm.nih.gov/ncbi/pmc/articleset/" rewritePrefix="file:///usr/share/xml/articleset/"/>

define(INPUT_DIR, __DIR__ . '/../../data/original/articles/plos-one/');
define(OUTPUT_DIR, __DIR__ . '/../../data/parsed/contributions/');

if (!file_exists(OUTPUT_DIR)) {
	mkdir(OUTPUT_DIR, 0777, true);
}

$output = fopen(OUTPUT_DIR . 'plos_one_author_contributions.csv', 'w');
fputcsv($output, array('doi', 'position', 'name', 'initials', 'conceived', 'performed', 'analyzed', 'contributed', 'wrote', 'countries', 'affiliations', 'text'));

$contributionTypes = array(
	'conceived' => 'conceived and designed the experiments',
	'performed' => 'performed the experiments',
	'analyzed' => 'analyzed the data',
	'contributed' => 'contributed reagents/materials/analysis tools',
	'wrote' => 'wrote the paper',
	//'read' => 'read the'
);

$files = glob(INPUT_DIR . '*.xml');

foreach ($files as $file) {
	$dom = new DOMDocument;
	$dom->preserveWhiteSpace = false;
	$dom->validateOnParse = true;
	$dom->load($file, LIBXML_DTDLOAD);
	$xpath = new DOMXPath($dom);

	$doiNodes = $xpath->query('//front/article-meta/article-id[@pub-id-type="doi"]');
	$doi = $doiNodes->item(0)->textContent;

	$contributionNodes = $xpath->query('//fn[@fn-type="con"]/p');
	printf("%d contribution nodes in %s\n", $contributionNodes->length, $file);

	if (!$contributionNodes->length) {
		continue;
	}

	$contributions = array();
	$contributionNode = $contributionNodes->item(0);
	$contributionItems = preg_split('/\.\s+/', $contributionNode->nodeValue);

	foreach ($contributionItems as $fullContributionText) {
		$fullContributionText = trim($fullContributionText, ' .');
		list(, $initials) = preg_split('/:\s+/', $fullContributionText);

		$initialsList = preg_split('/\s+/', $initials);

		//print_r(array($fullContributionText, $initialsList, array_keys($authors)));

		foreach ($initialsList as $initials) {
			$contributions[$initials][] = $fullContributionText;
		}
	}

	$authorNodes = $xpath->query('//front/article-meta/contrib-group/contrib[@contrib-type="author"]');
	foreach ($authorNodes as $authorNode) {
		$name = array();

		$givenNameNodes = $xpath->query('name/given-names', $authorNode);
		if ($givenNameNodes->length) {
			$name[] = $givenNameNodes->item(0)->textContent;
		}

		$surnameNodes = $xpath->query('name/surname', $authorNode);
		if ($surnameNodes->length) {
			$name[] = $surnameNodes->item(0)->textContent;
		}

		$name = implode(' ', $name);
		preg_match_all('/(?:\b([A-Z])|-)/', $name, $matches, PREG_PATTERN_ORDER); // FIXME: McGroty is just "M"

		$initials = implode('', $matches[1]);

		/* get the author position by counting the number of previous author nodes */
		$position = 1;
		$sibling = $authorNode;
		while ($sibling = $sibling->previousSibling) {
			if ($sibling->nodeType === XML_ELEMENT_NODE && $sibling->nodeName == 'contrib' && $sibling->getAttribute('contrib-type') == 'author') {
				$position++;
			}
		}

		$author = array(
			'doi' => $doi,
			'position' => $position,
			'name' => array(),
			'initials' => $initials,
			'conceived' => 0,
			'performed' => 0,
			'analyzed' => 0,
			'contributed' => 0,
			'wrote' => 0,
			//'read' => 0,
			'countries' => array(),
			'affiliations' => array(),
			'text' => isset($contributions[$initials]) ? implode('; ', $contributions[$initials]) : null,
		);

		if (isset($contributions[$initials])) {
			foreach ($contributions[$initials] as $fullContributionText) {
				foreach ($contributionTypes as $contributionType => $contributionText) {
					if (stripos($fullContributionText, $contributionText) !== false) {
						$author[$contributionType] = 1;
					}
				}
			}
		}

		$givenNameNodes = $xpath->query('name/given-names', $authorNode);
		if ($givenNameNodes->length) {
			$author['name'][] = $givenNameNodes->item(0)->textContent;
		}

		$surnameNodes = $xpath->query('name/surname', $authorNode);
		if ($surnameNodes->length) {
			$author['name'][] = $surnameNodes->item(0)->textContent;
		}

		$affRidNodes = $xpath->query('xref[@ref-type="aff"]/@rid', $authorNode);
		if ($affRidNodes->length) {
			foreach ($affRidNodes as $affRidNode) {
				$affNode = $dom->getElementById($affRidNode->nodeValue);

				$labelNode = $xpath->query('label', $affNode)->item(0);
				if ($labelNode) {
					$affNode->removeChild($labelNode);
				}

				$author['affiliations'][] = trim($affNode->textContent);

				$countryNodes = $xpath->query('country', $affNode);
				if ($countryNodes->length) {
					$author['countries'][] = $countryNodes->item(0)->textContent;
				}
			}
		}

		$author['name'] = implode(' ', $author['name']);
		$author['countries'] = implode('; ', array_unique($author['countries']));
		$author['affiliations'] = implode('; ', $author['affiliations']);

		fputcsv($output, $author);
	}
}
