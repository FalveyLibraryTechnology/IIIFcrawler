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

// Create the crawler
$crawler = new Crawler($url, $mime);
$harvested = $crawler->crawl();
$errors = $crawler->getErrors();

// Done! Report results.
echo "$harvested $mime file(s) harvested.\n";
if (!empty($errors)) {
    echo "ERRORS:\n" . implode("\n", $errors) . "\n";
}

class Crawler
{
    /**
     * Starting point URL
     *
     * @var string
     */
    protected $startUrl;

    /**
     * MIME type to harvest
     *
     * @var string
     */
    protected $mime;

    /**
     * File extension to use on saved files
     *
     * @var string
     */
    protected $extension;

    /**
     * Error list
     *
     * @var array
     */
    protected $errors = [];

    /**
     * Harvest count
     *
     * @var int
     */
    protected $harvested = 0;

    /**
     * Constructor
     *
     * @param string $url  Starting point URL
     * @param string $mime MIME type to harvest
     */
    public function __construct($url, $mime)
    {
        $this->startUrl = $url;
        $this->mime = $mime;
        $this->extension = $this->getExtensionFromMime($mime);
    }

    /**
     * Start the crawling process; return the count of files harvested.
     *
     * @return int
     */
    public function crawl()
    {
        $this->getUrl($this->startUrl);
        return $this->harvested;
    }

    /**
     * Retrieve the contents of a URL.
     *
     * @param string $url URL to fetch.
     *
     * @return void
     */
    protected function getUrl($url)
    {
        // Load the JSON
        if (!$json = file_get_contents($url)) {
            return $this->addError("Problem retrieving $url.\n");
        }

        // Parse the JSON
        if (!$data = json_decode($json)) {
            return $this->addError("Problem decoding JSON from $url.\n");
        }

        // Validate the JSON
        if (!isset($data->sequences) || !is_array($data->sequences)
            || empty($data->sequences)
        ) {
            return $this->addError("No sequences found in manifest.\n");
        }

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
                $nextFilename = $this->getFilename($seqNum, $canvNum);
                if (!$this->saveMatchingFile($canvas, $nextFilename)) {
                    echo "No matching {$this->mime} found for sequence $seqNum, canvas $canvNum\n";
                } else {
                    echo "Saved $nextFilename\n";
                    $this->harvested++;
                }
            }
        }
    }

    /**
     * Log an error.
     *
     * @param string $error Message to log
     *
     * @return void
     */
    protected function addError($error)
    {
        $this->errors[] = $error;
    }

    /**
     * Get the logged errors.
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Return a file extension for the given MIME type.
     *
     * @param string $mime MIME type
     *
     * @return string
     */
    protected function getExtensionFromMime($mime)
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
     * @param int    $seqNum  Sequence number
     * @param int    $canvNum Canvas number
     *
     * @return string
     */
    protected function getFilename($seqNum, $canvNum)
    {
        return str_pad($seqNum, 10, '0', STR_PAD_LEFT) . '-'
            . str_pad($canvNum, 10, '0', STR_PAD_LEFT) . '.' . $this->extension;
    }

    /**
     * Extract a file matching a mime type from a canvas and save it to disk.
     * Return true if match found, false otherwise.
     *
     * @param array  $canvas Canvas to search
     * @param string $file   Filename to save
     *
     * @return bool
     */
    protected function saveMatchingFile($canvas, $file)
    {
        foreach (['images', 'rendering'] as $section) {
            if (isset($canvas->$section) && is_array($canvas->$section)) {
                foreach ($canvas->$section as $item) {
                    if ($section == 'images') {
                        $item = $item->resource;
                    }
                    if (isset($item->format) && $item->format == $this->mime
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
}
