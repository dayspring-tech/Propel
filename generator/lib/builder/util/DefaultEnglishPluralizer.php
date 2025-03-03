<?php

/**
 * This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */


/**
 * The default English pluralizer class.
 *
 * @author     Hans Lellelid <hans@xmpl.org>
 * @version    $Revision$
 * @package    propel.generator.builder.util
 */
class DefaultEnglishPluralizer implements Pluralizer
{

    /**
     * Generate a plural name based on the passed in root.
     *
     * @param string $root The root that needs to be pluralized (e.g. Author)
     *
     * @return string The plural form of $root (e.g. Authors).
     */
    public function getPluralForm($root): string
    {
        return $root . 's';
    }
}
