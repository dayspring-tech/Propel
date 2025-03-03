<?php

/**
 * This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */



/**
 * Generates the empty PHP5 stub query class for use with single table inheritance.
 *
 * This class produces the empty stub class that can be customized with application
 * business logic, custom behavior, etc.
 *
 *
 * @author     François Zaninotto
 * @package    propel.generator.builder.om
 */
class QueryInheritanceBuilder extends OMBuilder
{

    /**
     * The current child "object" we are operating on.
     */
    protected $child;

    /**
     * Returns the name of the current class being built.
     *
     * @return string
     */
    public function getUnprefixedClassname(): string
    {
        return $this->getBuildProperty('basePrefix') . $this->getNewStubQueryInheritanceBuilder($this->getChild())->getUnprefixedClassname();
    }

    /**
     * Gets the package for the [base] object classes.
     *
     * @return string
     */
    public function getPackage(): string
    {
        return ($this->getChild()->getPackage() ? $this->getChild()->getPackage() : parent::getPackage()) . ".om";
    }

    public function getNamespace()
    {
        if ($namespace = parent::getNamespace()) {
            if ($this->getGeneratorConfig() && $omns = $this->getGeneratorConfig()->getBuildProperty('namespaceOm')) {
                return $namespace . '\\' . $omns;
            } else {
                return $namespace;
            }
        }
    }

    /**
     * Set the child object that we're operating on currently.
     *
     * @param   $child Inheritance
     */
    public function setChild(Inheritance $child)
    {
        $this->child = $child;
    }

    /**
     * Returns the child object we're operating on currently.
     *
     * @return Inheritance
     * @throws BuildException - if child was not set.
     */
    public function getChild()
    {
        if (!$this->child) {
            throw new BuildException("The PHP5MultiExtendObjectBuilder needs to be told which child class to build (via setChild() method) before it can build the stub class.");
        }

        return $this->child;
    }

    /**
     * Returns classpath to parent class.
     *
     * @return string
     */
    protected function getParentClassName()
    {
        $ancestorClassName = ClassTools::classname($this->getChild()->getAncestor());
        if ($this->getDatabase()->hasTableByPhpName($ancestorClassName)) {
            return $this->getNewStubQueryBuilder($this->getDatabase()->getTableByPhpName($ancestorClassName))->getClassname();
        } else {
            // find the inheritance for the parent class
            foreach ($this->getTable()->getChildrenColumn()->getChildren() as $child) {
                if ($child->getClassName() == $ancestorClassName) {
                    return $this->getNewStubQueryInheritanceBuilder($child)->getClassname();
                }
            }
        }
    }

    /**
     * Adds the include() statements for files that this class depends on or utilizes.
     *
     * @param string &$script The script will be modified in this method.
     */
    protected function addIncludes(&$script)
    {
        $requiredClassFilePath = $this->getStubQueryBuilder()->getClassFilePath();

        $script .= "
require '" . $requiredClassFilePath . "';
";
    } // addIncludes()

    /**
     * Adds class phpdoc comment and opening of class.
     *
     * @param string &$script The script will be modified in this method.
     */
    protected function addClassOpen(&$script)
    {
        $table = $this->getTable();
        $tableName = $table->getName();
        $tableDesc = $table->getDescription();

        $baseBuilder = $this->getStubQueryBuilder();
        $this->declareClassFromBuilder($baseBuilder);
        $baseClassname = $this->getParentClassName();

        $script .= "
/**
 * Skeleton subclass for representing a query for one of the subclasses of the '$tableName' table.
 *
 * $tableDesc
 *";
        if ($this->getBuildProperty('addTimeStamp')) {
            $now = (new DateTime())->format(DateTimeInterface::ATOM);
            $script .= "
 * This class was autogenerated by Propel " . $this->getBuildProperty('version') . " on:
 *
 * $now
 *";
        }
        $script .= "
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator." . $this->getPackage() . "
 */
class " . $this->getClassname() . " extends " . $baseClassname . " {
";
    }

    /**
     * Specifies the methods that are added as part of the stub object class.
     *
     * By default there are no methods for the empty stub classes; override this method
     * if you want to change that behavior.
     *
     * @see        ObjectBuilder::addClassBody()
     */
    protected function addClassBody(&$script)
    {
        $this->declareClassFromBuilder($this->getStubPeerBuilder());
        $this->declareClasses('PropelPDO', 'Criteria', 'BasePeer', 'PropelException');
        $this->addFactory($script);
        $this->addPreSelect($script);
        $this->addPreUpdate($script);
        $this->addPreDelete($script);
        $this->addDoDeleteAll($script);
        $this->addFindOneOrCreate($script);
    }

    /**
     * Adds the factory for this object.
     *
     * @param string &$script The script will be modified in this method.
     */
    protected function addFactory(&$script)
    {
        $builder = $this->getNewStubQueryInheritanceBuilder($this->getChild());
        $this->declareClassFromBuilder($builder);
        $classname = $builder->getClassname();
        $script .= "
    /**
     * Returns a new " . $classname . " object.
     *
     * @param     string \$modelAlias The alias of a model in the query
     * @param     Criteria \$criteria Optional Criteria to build the query from
     *
     * @return " . $classname . "
     */
    public static function create(\$modelAlias = null, \$criteria = null)
    {
        if (\$criteria instanceof " . $classname . ") {
            return \$criteria;
        }
        \$query = new " . $classname . "();
        if (null !== \$modelAlias) {
            \$query->setModelAlias(\$modelAlias);
        }
        if (\$criteria instanceof Criteria) {
            \$query->mergeWith(\$criteria);
        }

        return \$query;
    }
";
    }

    protected function addPreSelect(&$script)
    {
        $child = $this->getChild();
        $col = $child->getColumn();

        $script .= "
    /**
     * Filters the query to target only " . $child->getClassname() . " objects.
     */
    public function preSelect(PropelPDO \$con)
    {
        " . $this->getClassKeyCondition() . "
    }
";
    }

    protected function addPreUpdate(&$script)
    {
        $child = $this->getChild();
        $col = $child->getColumn();

        $script .= "
    /**
     * Filters the query to target only " . $child->getClassname() . " objects.
     */
    public function preUpdate(&\$values, PropelPDO \$con, \$forceIndividualSaves = false)
    {
        " . $this->getClassKeyCondition() . "
    }
";
    }

    protected function addPreDelete(&$script)
    {
        $child = $this->getChild();
        $col = $child->getColumn();

        $script .= "
    /**
     * Filters the query to target only " . $child->getClassname() . " objects.
     */
    public function preDelete(PropelPDO \$con)
    {
        " . $this->getClassKeyCondition() . "
    }
";
    }

    protected function getClassKeyCondition(): string
    {
        $child = $this->getChild();
        $col = $child->getColumn();

        return "\$this->addUsingAlias(" . $this->getColumnConstant($col) . ", " . $this->getPeerClassname() . "::CLASSKEY_" . strtoupper($child->getKey()) . ");";
    }

    protected function addDoDeleteAll(&$script)
    {
        $child = $this->getChild();

        $script .= "
    /**
     * Issue a DELETE query based on the current ModelCriteria deleting all rows in the table
     * Having the " . $child->getClassname() . " class.
     * This method is called by ModelCriteria::deleteAll() inside a transaction
     *
     * @param PropelPDO \$con a connection object
     *
     * @return integer the number of deleted rows
     */
    public function doDeleteAll(\$con)
    {
        // condition on class key is already added in preDelete()
        return parent::doDelete(\$con);
    }
";
    }

    /**
     * Closes class.
     *
     * @param string &$script The script will be modified in this method.
     */
    protected function addClassClose(&$script)
    {
        $script .= "
} // " . $this->getClassname() . "
";
    }

    /**
     * Adds findOneOrCreate function for this object.
     *
     * @param unknown $script
     */
    protected function addFindOneOrCreate(&$script)
    {
        $child = $this->getChild();
        $col = $child->getColumn();
        $script .= "

    /**
     * Issue a SELECT ... LIMIT 1 query based on the current ModelCriteria
     * and format the result with the current formatter
     * By default, returns a model object
     *
     * @param PropelPDO \$con an optional connection object
     *
     * @return mixed the result, formatted by the current formatter
     *
     * @throws PropelException
     */
    public function findOneOrCreate(\$con = null)
    {
        if (\$this->joins) {
            throw new PropelException('findOneOrCreate() cannot be used on a query with a join, because Propel cannot transform a SQL JOIN into a subquery. You should split the query in two queries to avoid joins.');
        }
        if (!\$ret = \$this->findOne(\$con)) {
            \$class = " . $this->getPeerClassname() . "::CLASSNAME_" . strtoupper($child->getKey()) . ";
            \$obj = new \$class;
            foreach (\$this->keys() as \$key) {
                \$obj->setByName(\$key, \$this->getValue(\$key), BasePeer::TYPE_COLNAME);
            }
            \$ret = \$this->getFormatter()->formatRecord(\$obj);
        }

        return \$ret;
    }
";
    }
} // MultiExtensionQueryBuilder
