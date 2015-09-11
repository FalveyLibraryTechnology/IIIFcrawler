IIIF Crawler
============

Introduction
------------
This is a simple tool for downloading files specified in a IIIF Presentation API
manifest. See http://iiif.io/api/presentation/2.0/ for details on the standard.

PLEASE USE THIS TOOL RESPONSIBLY. This was developed for internal use and does
not contain logic for "polite" server access; if you are planning on harvesting
somebody else's content en masse, please make arrangements with the content
owner first.


Usage
-----
Simply download the script, then run:

php IIIFcrawler.php [manifest URL] [MIME type]

where [manifest URL] is the full URL to a valid manifest, and [MIME type] is the
MIME type you wish to harvest out of the manifest (image/jpeg is the default if
no value is provided).


Contributing
------------
As of this writing, this is an extremely bare-bones tool. Feel free to submit
pull requests to make it more feature-rich.