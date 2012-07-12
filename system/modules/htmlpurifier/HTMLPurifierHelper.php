<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

/**
 * TYPOlight Open Source CMS
 * Copyright (C) 2005-2010 Leo Feyer
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  Nothing Interactive 2012 <https://www.nothing.ch/>
 * @author     Andreas Schempp <andreas@schempp.ch>
 * @author     Stefan Pfister <red@nothing.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */


class HTMLPurifierHelper extends Backend
{

	/**
	 * Current object instance (Singleton)
	 * @var object
	 */
	protected static $objInstance;

	/**
	 * HTMLPurifier instance
	 */
	protected $objPurifier;

	/**
	 * TinyMCE override
	 */
	protected $rte = '';


	/**
	 * Prevent direct instantiation (Singleton)
	 */
	protected function __construct()
	{
		parent::__construct();

		$this->import('BackendUser', 'User');

		require_once(TL_ROOT . '/plugins/htmlpurifier/HTMLPurifier.standalone.php');
		$this->objPurifier = HTMLPurifier::instance($this->getConfig());
	}


	/**
	 * Prevent cloning of the object (Singleton)
	 */
	final private function __clone()
	{ }


	/**
	 * Return the current object instance (Singleton)
	 * @return object
	 */
	public static function getInstance()
	{
		if (!is_object(self::$objInstance))
		{
			self::$objInstance = new HTMLPurifierHelper();
		}

		return self::$objInstance;
	}


	/**
	 * Clean a string.
	 *
	 * We need to decode [nbsp] for HTMLPurifier to work correctly, but re-encode afterwards.
	 */
	public function purify($varValue, $dc)
	{
		if (is_string($varValue))
		{
			$tags = preg_split('/{{([^}]+)}}/', $varValue, -1, PREG_SPLIT_DELIM_CAPTURE);

			$varValue = '';
			$arrTags = array();

			for ($_rit = 0; $_rit < count($tags); $_rit = $_rit + 2)
			{
				$varValue .= $tags[$_rit];

				if (!isset($tags[$_rit + 1]))
					continue;

				$strTag = $tags[$_rit + 1];

				$arrTags['((_tag_' . $_rit . '_))'] = '{{' . $strTag . '}}';

				$varValue .= '((_tag_' . $_rit . '_))';
			}

			$arrJSFix = array();
			preg_match_all('@(<a.*)( onclick="window.open\(this.href\); return false;")([^>]*>)@', $varValue, $arrMatches);
			foreach ($arrMatches[0] as $i => $match)
			{
				$varValue = str_replace($match, $arrMatches[1][$i] . $arrMatches[3][$i], $varValue);
				$arrJSFix[$arrMatches[1][$i] . $arrMatches[3][$i]] = $match;
			}

			//			$varValue = htmlentities($this->objPurifier->purify($this->restoreBasicEntities($varValue)), ENT_COMPAT, $GLOBALS['TL_CONFIG']['characterSet']);
			$varValue = $this->objPurifier->purify($this->restoreBasicEntities($varValue));

			if (count($arrJSFix))
			{
				$varValue = str_replace(array_keys($arrJSFix), $arrJSFix, $varValue);
			}

			if (count($arrTags))
			{
				$varValue = str_replace(array_keys($arrTags), array_values($arrTags), $varValue);
			}

			$varValue = str_replace(array('[&nbsp;]', '&nbsp;'), array('[nbsp]', '[nbsp]'), $varValue);
			//			$varValue = html_entity_decode($varValue, ENT_COMPAT, $GLOBALS['TL_CONFIG']['characterSet']);
		}

		return $varValue;
	}


	/**
	 * Using Hook loadDataContainer, we search for fields to clean
	 */
	public function addFilters($strTable)
	{
		if ($strTable == 'tl_settings' || !is_array($GLOBALS['TL_DCA'][$strTable]['fields']))
			return;

		foreach ($GLOBALS['TL_DCA'][$strTable]['fields'] as $strField => $arrData)
		{
			$blnRTE = strpos($arrData['eval']['rte'], 'tinyMCE') === 0 ? true : false;

			if ($blnRTE /* || $arrData['eval']['allowHtml'] || $arrData['eval']['preserveTags']*/)
			{
				$GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['save_callback'][] = array(
					'HTMLPurifierHelper', 'purify'
				);

				if ($blnRTE && $this->rte != '')
				{
					$GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['eval']['rte'] = $this->rte;
				}
			}
		}
	}


	/**
	 * Load the HTMLPurifier configuration from database.
	 */
	private function getConfig()
	{
		$arrConfig = array('Cache.SerializerPath'=> TL_ROOT . '/system/tmp');
		$intConfig = $this->User->htmlpurifier;

		// Find group configuration
		if (!$intConfig && !$this->User->isAdmin && is_array($this->User->groups) && count($this->User->groups))
		{
			$objGroup = $this->Database->prepare("SELECT * FROM tl_user_group WHERE id IN (" . implode(',', $this->User->groups) . ") AND htmlpurifier!=''")->limit(1)->execute();

			if ($objGroup->numRows)
			{
				$intConfig = $objGroup->htmlpurifier;
			}
		}

		$arrTags = array_unique(array_map(create_function('$a', 'preg_match(\'@([a-z0-9]+)@i\', $a, $m); return $m[1];'), explode('><', $GLOBALS['TL_CONFIG']['allowedTags'])));

		// Remove HTML5 tags, HTMLPurifier does not support them
		$arrTags = array_diff($arrTags, array('article', 'aside', 'figure', 'figcaption', 'section'));

		$objConfig = $this->Database->prepare("SELECT * FROM tl_htmlpurifier WHERE id=? OR fallback='1' ORDER BY fallback")->limit(1)->execute($intConfig);

		if ($objConfig->numRows)
		{
			foreach ($objConfig->row() as $k => $v)
			{
				$strParam = str_replace('_', '.', $k);

				switch ($strParam)
				{
					case 'Attr.DefaultTextDir':
					case 'HTML.TidyLevel':
					case 'Attr.DefaultImageAlt':
					case 'HTML.Doctype':
						if (!strlen($v))
						{
							continue(2);
						}
						$varValue = $v;
						break;

					case 'Attr.AllowedClasses':
					case 'Attr.ForbiddenClasses':
					case 'Attr.IDBlacklist':
					case 'HTML.AllowedAttributes':
					case 'HTML.ForbiddenAttributes':
					case 'CSS.AllowedProperties':
					case 'CSS.ForbiddenProperties':
						if (!strlen($v))
						{
							continue(2);
						}
						$varValue = trimsplit(',', $v);
						break;

					case 'Attr.EnableID':
					case 'AutoFormat.Linkify':
					case 'AutoFormat.RemoveEmpty':
					case 'AutoFormat.RemoveEmpty.RemoveNbsp':
					case 'AutoFormat.RemoveSpansWithoutAttributes':
					case 'CSS.AllowImportant':
					case 'CSS.AllowTricky':
					case 'CSS.Proprietary':
					case 'HTML.Trusted':
					case 'URI.DisableExternal':
					case 'URI.DisableExternalResources':
						$varValue = $v ? true : false;
						break;

					case 'AutoFormat.RemoveEmpty.RemoveNbsp.Exceptions':
						if (!strlen($v))
						{
							continue(2);
						}
						$varValue = array_fill_keys(trimsplit(',', $v), true);
						break;

					case 'rte':
						$this->rte = $v;
						continue(2);

					case 'HTML.ForbiddenElements':
						$arrForbidden = trimsplit(',', $v);
						$arrTags = array_diff($arrTags, $arrForbidden);
						continue(2);

					default:
						continue(2);
				}

				$arrConfig[$strParam] = $varValue;
			}
		}

		// Clean tags not supported by htmlpurifier
		$arrTags = array_diff($arrTags, array(
											 'area', 'base', 'button', 'form', 'fieldset', 'input', 'label', 'legend',
											 'link', 'map', 'object', 'optgroup', 'option', 'param', 'select', 'style',
											 'textarea', 'u', 'script', 'fb', 'php'
										));

		$objConfig = HTMLPurifier_Config::create($arrConfig);
		$objConfig->set('HTML.AllowedElements', array_fill_keys($arrTags, true));
		$objConfig->set('Attr.AllowedFrameTargets', array('_blank'));

		return $objConfig;
	}
}

