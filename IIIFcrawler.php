<?php
/**
 * IIIF Crawler - See README.md for details.
 *
 * PHP version 5.4
 *
 * Copyright (C) Villanova University 2015.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */

// Validate URL parameter
$url = isset($argv[1]) ? $argv[1] : null;
if (empty($url)) {
    die("Usage: {$argv[0]} [IIIF Manifest URL] [MIME type to harvest]\n");
}

// Default MIME parameter to image/jpeg if not supplied
$mime = isset($argv[2]) ? $argv[2] : 'image/jpeg';

// Load the JSON
if (!$json = file_get_contents($url)) {
    die("Problem retrieving $url.\n");
}

// Parse the JSON
if (!$data = json_decode($json)) {
    die("Problem decoding JSON.\n");
}

// Validate the JSON
if (!isset($data->sequences) || !is_array($data->sequences)
    || empty($data->sequences)
) {
    die("No sequences found in manifest.\n");
}

// Initialize harvest counter
$harvested = 0;

// Loop through sequences
foreach ($data->sequences as $seqNum => $sequence) {
    if (!isset($sequence->canvases) || !is_array($sequence->canvases)
        || empty($sequence->canvases)
    ) {
        echo "No canvases found in sequence $seqNum.\n";
        continue;
    }
    // Loop through canvases
    foreach ($sequence->canvases as $canvNum => $canvas) {
        // Grab the next file
        $nextFilename = getFilename($seqNum, $canvNum, getExtensionFromMime($mime));
        if (!saveMatchingFile($canvas, $nextFilename, $mime)) {
            echo "No matching $mime found for sequence $seqNum, canvas $canvNum\n";
        } else {
            echo "Saved $nextFilename\n";
            $harvested++;
        }
    }
}

// Done! Report results.
echo "$harvested $mime file(s) harvested.\n";

/**
 * Return a file extension for the given MIME type.
 *
 * @param string $mime MIME type
 *
 * @return string
 */
function getExtensionFromMime($mime)
{
    switch ($mime) {
    case 'image/jpeg':
        return 'jpg';
    case 'text/plain':
        return 'txt';
    default:
        return 'bin';
    }
}

/**
 * Determine a filename based on sequence and canvas numbers.
 *
 * @param int    $seqNum    Sequence number
 * @param int    $canvNum   Canvas number
 * @param string $extension File extension to use
 *
 * @return string
 */
function getFilename($seqNum, $canvNum, $extension)
{
    return str_pad($seqNum, 10, '0', STR_PAD_LEFT) . '-'
        . str_pad($canvNum, 10, '0', STR_PAD_LEFT) . '.' . $extension;
}

/**
 * Extract a file matching a mime type from a canvas and save it to disk.
 * Return true if match found, false otherwise.
 *
 * @param array  $canvas Canvas to search
 * @param string $file   Filename to save
 * @param string $mime   MIME type to find
 *
 * @return bool
 */
function saveMatchingFile($canvas, $file, $mime)
{
    foreach (['images', 'rendering'] as $section) {
        if (isset($canvas->$section) && is_array($canvas->$section)) {
            foreach ($canvas->$section as $item) {
                if ($section == 'images') {
                    $item = $item->resource;
                }
                if (isset($item->format) && $item->format == $mime
                    && isset($item->{'@id'})
                ) {
                    file_put_contents($file, file_get_contents($item->{'@id'}));
                    return true;
                }
            }
        }
    }
    return false;
}