# [UNMAINTAINED]
This project is not maintained anymore.

# CONTAO EXTENSION: HTML PURIFIER
This extension implements the [HTML Purifier](http://htmlpurifier.org/) PHP library, which filters user-submitted HTML framework into Contao's backend. Rich text editors content can then be purified on save.

## SETUP AND USAGE
### Prerequisites
 * Contao 3.0.x
 * [HTML Purifier](http://htmlpurifier.org/) 4.5.x Standalone

### Installation
1. Extract [HTML Purifier](http://htmlpurifier.org/) Standalone to /vendor/htmlpurifier in the root folder from Contao
1. Copy the _system_ folder into the root folder from Contao
2. Update the database (e.g. with the _Extension manager_)
3. Create a configuration for the HTML Purifier in the backend from Contao (backend module below _System_)
4. Apply the [HTML Purifier](http://htmlpurifier.org/) configuration to your user groups (Users|User groups >Edit >Allowed fields >HTMLPurifier configuration)

### Known Issues
1. HTML Purifier removes target="_blank" attributes if you set the document type to XHTML 1.0 Strict. If you need the link target, please switch to XHTML 1.0 Transitional.

## VERSION HISTORY

### 2.1.0 (2013-04-25)
 * Added URIScheme to allow tel: href in anchors.

### 2.0.0 (2013-03-14)
 * Updated for Contao 3 compatibility
 * Upgraded HTML Purifier requirement to 4.5.x

### 1.0.0 (2012-07-12)
 * Initial release

## LICENSE
* Author:	  	Nothing Interactive, Switzerland
* Website: 		[https://www.nothing.ch/](https://www.nothing.ch/)
* Version: 		2.0.0
* Date: 		  2013-03-14
* License: 		[GNU Lesser General Public License (LGPL)](http://www.gnu.org/licenses/lgpl.html)
