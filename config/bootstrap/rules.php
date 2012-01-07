<?php
/**
 * li3_attachable: the most rad li3 file uploader.
 *
 * @copyright     Copyright 2012, Tobias Sandelius (http://sandelius.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

use lithium\util\Validator;

Validator::add('attachmentType', function($value, $type, $data) {
	if (isset($data['attachment'])) {
		$mime = $data['attachment']['type'];
		$mimeTypes = require __DIR__ . '/mime.php';
		foreach ($data['types'] as $each) {
			if (isset($mimeTypes[$each]) && in_array($mime, $mimeTypes[$each])) {
				return true;
			}
		}
		return false;
	}
	return true;
});

Validator::add('attachmentSize', function($value, $type, $data) {
	if (isset($data['attachment'])) {
		$size = $data['attachment']['size'];
		if (is_string($data['size'])) {
			if (preg_match('/([0-9\.]+) ?([a-z]*)/i', $data['size'], $matches)) {
				$number = $matches[1];
				$suffix = $matches[2];
				$suffixes = array(""=> 0, "Bytes"=>0, "KB"=>1, "MB"=>2, "GB"=>3, "TB"=>4, "PB"=>5);
				if (isset($suffixes[$suffix])) {
					$data['size'] = round($number * pow(1024, $suffixes[$suffix]));
				}
			}
		}
		return $data['size'] >= $size;
	}
	return true;
});

?>