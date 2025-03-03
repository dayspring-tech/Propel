<?php

/**
 * This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

/**
 * A validator for required fields.
 *
 * Below is an example usage for your Propel xml schema file.
 *
 * <code>
 *   <column name="username" type="VARCHAR" size="25" required="true" />
 *
 *   <validator column="username">
 *     <rule name="required" message="Username is required." />
 *   </validator>
 * </code>
 *
 * @author     Michael Aichler <aichler@mediacluster.de>
 * @version    $Revision$
 * @package    propel.runtime.validator
 */
class RequiredValidator implements BasicValidator
{
    /**
     * @see       BasicValidator::isValid()
     *
     * @param ValidatorMap $map
     * @param string       $str
     *
     * @return boolean
     */
    public function isValid(ValidatorMap $map, $str): bool
    {
        return ($str !== null && $str !== "");
    }
}
