<?php

/**
 * This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

/**
 * Behavior to adds nested set tree structure columns and abilities
 *
 * @author     François Zaninotto
 * @author     heltem <heltem@o2php.com>
 * @package    propel.generator.behavior.nestedset
 */
class NestedSetBehaviorObjectBuilderModifier
{
    protected $behavior, $table, $builder, $objectClassname, $peerClassname;

    public function __construct($behavior)
    {
        $this->behavior = $behavior;
        $this->table = $behavior->getTable();
    }

    protected function getParameter($key)
    {
        return $this->behavior->getParameter($key);
    }

    protected function getColumnAttribute($name): string
    {
        return strtolower($this->behavior->getColumnForParameter($name)->getName());
    }

    protected function getColumnPhpName($name)
    {
        return $this->behavior->getColumnForParameter($name)->getPhpName();
    }

    protected function setBuilder($builder)
    {
        $this->builder = $builder;

        $this->objectClassname = $builder->getStubObjectBuilder()->getClassname();
        $this->queryClassname = $builder->getStubQueryBuilder()->getClassname();
        $this->peerClassname = $builder->getStubPeerBuilder()->getClassname();

        $this->builder->declareClass($builder->getStubObjectBuilder()->getFullyQualifiedClassname());
        $this->builder->declareClass($builder->getStubQueryBuilder()->getFullyQualifiedClassname());
    }

    public function objectAttributes($builder): string
    {
        $objectClassname = $builder->getStubObjectBuilder()->getClassname();

        return "
/**
 * Queries to be executed in the save transaction
 * @var        array
 */
protected \$nestedSetQueries = array();

/**
 * Internal cache for children nodes
 * @var        null|PropelObjectCollection
 */
protected \$collNestedSetChildren = null;

/**
 * Internal cache for parent node
 * @var        null|$objectClassname
 */
protected \$aNestedSetParent = null;

";
    }

    public function preSave($builder): string
    {
        $peerClassname = $builder->getStubPeerBuilder()->getClassname();
        $queryClassname = $builder->getStubQueryBuilder()->getClassname();

        $script = "if (\$this->isNew() && \$this->isRoot()) {
    // check if no other root exist in, the tree
    \$nbRoots = $queryClassname::create()
        ->addUsingAlias($peerClassname::LEFT_COL, 1, Criteria::EQUAL)";

        if ($this->behavior->useScope()) {
            $script .= "
        ->addUsingAlias($peerClassname::SCOPE_COL, \$this->getScopeValue(), Criteria::EQUAL)";
        }

        $script .= "
        ->count(\$con);
    if (\$nbRoots > 0) {
            throw new PropelException(";

        if ($this->behavior->useScope()) {
            $script .= "sprintf('A root node already exists in this tree with scope \"%s\".', \$this->getScopeValue())";
        } else {
            $script .= "'A root node already exists in this tree. To allow multiple root nodes, add the `use_scope` parameter in the nested_set behavior tag.'";
        }

        $script .= ");
    }
}
\$this->processNestedSetQueries(\$con);";

        return $script;
    }

    public function preDelete($builder): string
    {
        $peerClassname = $builder->getStubPeerBuilder()->getClassname();

        return "if (\$this->isRoot()) {
    throw new PropelException('Deletion of a root node is disabled for nested sets. Use $peerClassname::deleteTree(" . ($this->behavior->useScope() ? '$scope' : '') . ") instead to delete an entire tree');
}

if (\$this->isInTree()) {
    \$this->deleteDescendants(\$con);
}
";
    }

    public function postDelete($builder): string
    {
        $peerClassname = $builder->getStubPeerBuilder()->getClassname();

        return "if (\$this->isInTree()) {
    // fill up the room that was used by the node
    $peerClassname::shiftRLValues(-2, \$this->getRightValue() + 1, null" . ($this->behavior->useScope() ? ", \$this->getScopeValue()" : "") . ", \$con);
}
";
    }

    public function objectClearReferences($builder): string
    {
        return "\$this->collNestedSetChildren = null;
\$this->aNestedSetParent = null;";
    }

    public function objectMethods($builder)
    {
        $this->setBuilder($builder);
        $script = '';

        $this->addProcessNestedSetQueries($script);

        if ($this->getColumnPhpName('left_column') != 'LeftValue') {
            $this->addGetLeft($script);
        }
        if ($this->getColumnPhpName('right_column') != 'RightValue') {
            $this->addGetRight($script);
        }
        if ($this->getColumnPhpName('level_column') != 'Level') {
            $this->addGetLevel($script);
        }
        if ($this->getParameter('use_scope') == 'true' && $this->getColumnPhpName('scope_column') != 'ScopeValue') {
            $this->addGetScope($script);
        }

        if ($this->getColumnPhpName('left_column') != 'LeftValue') {
            $this->addSetLeft($script);
        }
        if ($this->getColumnPhpName('right_column') != 'RightValue') {
            $this->addSetRight($script);
        }
        if ($this->getColumnPhpName('level_column') != 'Level') {
            $this->addSetLevel($script);
        }
        if ($this->getParameter('use_scope') == 'true' && $this->getColumnPhpName('scope_column') != 'ScopeValue') {
            $this->addSetScope($script);
        }

        $this->addMakeRoot($script);

        $this->addIsInTree($script);
        $this->addIsRoot($script);
        $this->addIsLeaf($script);
        $this->addIsDescendantOf($script);
        $this->addIsAncestorOf($script);

        $this->addHasParent($script);
        $this->addSetParent($script);
        $this->addGetParent($script);

        $this->addHasPrevSibling($script);
        $this->addGetPrevSibling($script);

        $this->addHasNextSibling($script);
        $this->addGetNextSibling($script);

        $this->addNestedSetChildrenClear($script);
        $this->addNestedSetChildrenInit($script);
        $this->addNestedSetChildAdd($script);
        $this->addHasChildren($script);
        $this->addGetChildren($script);
        $this->addCountChildren($script);

        $this->addGetFirstChild($script);
        $this->addGetLastChild($script);
        $this->addGetSiblings($script);
        $this->addGetDescendants($script);
        $this->addCountDescendants($script);
        $this->addGetBranch($script);
        $this->addGetAncestors($script);

        $this->addAddChild($script);
        $this->addInsertAsFirstChildOf($script);
        $this->addInsertAsLastChildOf($script);
        $this->addInsertAsPrevSiblingOf($script);
        $this->addInsertAsNextSiblingOf($script);

        $this->addMoveToFirstChildOf($script);
        $this->addMoveToLastChildOf($script);
        $this->addMoveToPrevSiblingOf($script);
        $this->addMoveToNextSiblingOf($script);
        $this->addMoveSubtreeTo($script);

        $this->addDeleteDescendants($script);

        $this->addGetIterator($script);

        if ($this->getParameter('method_proxies') == 'true') {
            $this->addCompatibilityProxies($script);
        }

        return $script;
    }

    protected function addProcessNestedSetQueries(&$script)
    {
        $script .= "
/**
 * Execute queries that were saved to be run inside the save transaction
 */
protected function processNestedSetQueries(\$con)
{
    foreach (\$this->nestedSetQueries as \$query) {
        \$query['arguments'][]= \$con;
        call_user_func_array(\$query['callable'], \$query['arguments']);
    }
    \$this->nestedSetQueries = array();
}
";
    }

    protected function addGetLeft(&$script)
    {
        $script .= "
/**
 * Proxy getter method for the left value of the nested set model.
 * It provides a generic way to get the value, whatever the actual column name is.
 *
 * @return     int The nested set left value
 */
public function getLeftValue()
{
    return \$this->{$this->getColumnAttribute('left_column')};
}
";
    }

    protected function addGetRight(&$script)
    {
        $script .= "
/**
 * Proxy getter method for the right value of the nested set model.
 * It provides a generic way to get the value, whatever the actual column name is.
 *
 * @return     int The nested set right value
 */
public function getRightValue()
{
    return \$this->{$this->getColumnAttribute('right_column')};
}
";
    }

    protected function addGetLevel(&$script)
    {
        $script .= "
/**
 * Proxy getter method for the level value of the nested set model.
 * It provides a generic way to get the value, whatever the actual column name is.
 *
 * @return     int The nested set level value
 */
public function getLevel()
{
    return \$this->{$this->getColumnAttribute('level_column')};
}
";
    }

    protected function addGetScope(&$script)
    {
        $script .= "
/**
 * Proxy getter method for the scope value of the nested set model.
 * It provides a generic way to get the value, whatever the actual column name is.
 *
 * @return     int The nested set scope value
 */
public function getScopeValue()
{
    return \$this->{$this->getColumnAttribute('scope_column')};
}
";
    }

    protected function addSetLeft(&$script)
    {
        $script .= "
/**
 * Proxy setter method for the left value of the nested set model.
 * It provides a generic way to set the value, whatever the actual column name is.
 *
 * @param      int \$v The nested set left value
 * @return     {$this->objectClassname} The current object (for fluent API support)
 */
public function setLeftValue(\$v)
{
    return \$this->set{$this->getColumnPhpName('left_column')}(\$v);
}
";
    }

    protected function addSetRight(&$script)
    {
        $script .= "
/**
 * Proxy setter method for the right value of the nested set model.
 * It provides a generic way to set the value, whatever the actual column name is.
 *
 * @param      int \$v The nested set right value
 * @return     {$this->objectClassname} The current object (for fluent API support)
 */
public function setRightValue(\$v)
{
    return \$this->set{$this->getColumnPhpName('right_column')}(\$v);
}
";
    }

    protected function addSetLevel(&$script)
    {
        $script .= "
/**
 * Proxy setter method for the level value of the nested set model.
 * It provides a generic way to set the value, whatever the actual column name is.
 *
 * @param      int \$v The nested set level value
 * @return     {$this->objectClassname} The current object (for fluent API support)
 */
public function setLevel(\$v)
{
    return \$this->set{$this->getColumnPhpName('level_column')}(\$v);
}
";
    }

    protected function addSetScope(&$script)
    {
        $script .= "
/**
 * Proxy setter method for the scope value of the nested set model.
 * It provides a generic way to set the value, whatever the actual column name is.
 *
 * @param      int \$v The nested set scope value
 * @return     {$this->objectClassname} The current object (for fluent API support)
 */
public function setScopeValue(\$v)
{
    return \$this->set{$this->getColumnPhpName('scope_column')}(\$v);
}
";
    }

    protected function addMakeRoot(&$script)
    {
        $script .= "
/**
 * Creates the supplied node as the root node.
 *
 * @return     {$this->objectClassname} The current object (for fluent API support)
 * @throws     PropelException
 */
public function makeRoot()
{
    if (\$this->getLeftValue() || \$this->getRightValue()) {
        throw new PropelException('Cannot turn an existing node into a root node.');
    }

    \$this->setLeftValue(1);
    \$this->setRightValue(2);
    \$this->setLevel(0);

    return \$this;
}
";
    }

    protected function addIsInTree(&$script)
    {
        $script .= "
/**
 * Tests if onbject is a node, i.e. if it is inserted in the tree
 *
 * @return     bool
 */
public function isInTree()
{
    return \$this->getLeftValue() > 0 && \$this->getRightValue() > \$this->getLeftValue();
}
";
    }

    protected function addIsRoot(&$script)
    {
        $script .= "
/**
 * Tests if node is a root
 *
 * @return     bool
 */
public function isRoot()
{
    return \$this->isInTree() && \$this->getLeftValue() == 1;
}
";
    }

    protected function addIsLeaf(&$script)
    {
        $script .= "
/**
 * Tests if node is a leaf
 *
 * @return     bool
 */
public function isLeaf()
{
    return \$this->isInTree() &&  (\$this->getRightValue() - \$this->getLeftValue()) == 1;
}
";
    }

    protected function addIsDescendantOf(&$script)
    {
        $objectClassname = $this->objectClassname;
        $script .= "
/**
 * Tests if node is a descendant of another node
 *
 * @param      $objectClassname \$node Propel node object
 * @return     bool
 */
public function isDescendantOf(\$parent)
{";
        if ($this->behavior->useScope()) {
            $script .= "
    if (\$this->getScopeValue() !== \$parent->getScopeValue()) {
        return false; //since the `this` and \$parent are in different scopes, there's no way that `this` is be a descendant of \$parent.
    }";
        }
        $script .= "

    return \$this->isInTree() && \$this->getLeftValue() > \$parent->getLeftValue() && \$this->getRightValue() < \$parent->getRightValue();
}
";
    }

    protected function addIsAncestorOf(&$script)
    {
        $objectClassname = $this->objectClassname;
        $script .= "
/**
 * Tests if node is a ancestor of another node
 *
 * @param      $objectClassname \$node Propel node object
 * @return     bool
 */
public function isAncestorOf(\$child)
{
    return \$child->isDescendantOf(\$this);
}
";
    }

    protected function addHasParent(&$script)
    {
        $script .= "
/**
 * Tests if object has an ancestor
 *
 * @param      PropelPDO \$con Connection to use.
 * @return     bool
 */
public function hasParent(PropelPDO \$con = null)
{
    return \$this->getLevel() > 0;
}
";
    }

    protected function addSetParent(&$script)
    {
        $objectClassname = $this->objectClassname;
        $script .= "
/**
 * Sets the cache for parent node of the current object.
 * Warning: this does not move the current object in the tree.
 * Use moveTofirstChildOf() or moveToLastChildOf() for that purpose
 *
 * @param      $objectClassname \$parent
 * @return     $objectClassname The current object, for fluid interface
 */
public function setParent(\$parent = null)
{
    \$this->aNestedSetParent = \$parent;

    return \$this;
}
";
    }

    protected function addGetParent(&$script)
    {
        $script .= "
/**
 * Gets parent node for the current object if it exists
 * The result is cached so further calls to the same method don't issue any queries
 *
 * @param      PropelPDO \$con Connection to use.
 * @return     mixed 		Propel object if exists else false
 */
public function getParent(PropelPDO \$con = null)
{
    if (\$this->aNestedSetParent === null && \$this->hasParent()) {
        \$this->aNestedSetParent = {$this->queryClassname}::create()
            ->ancestorsOf(\$this)
            ->orderByLevel(true)
            ->findOne(\$con);
    }

    return \$this->aNestedSetParent;
}
";
    }

    protected function addHasPrevSibling(&$script)
    {
        $peerClassname = $this->peerClassname;
        $queryClassname = $this->queryClassname;
        $script .= "
/**
 * Determines if the node has previous sibling
 *
 * @param      PropelPDO \$con Connection to use.
 * @return     bool
 */
public function hasPrevSibling(PropelPDO \$con = null)
{
    if (!{$this->peerClassname}::isValid(\$this)) {
        return false;
    }

    return $queryClassname::create()
        ->filterBy" . $this->getColumnPhpName('right_column') . "(\$this->getLeftValue() - 1)";
        if ($this->behavior->useScope()) {
            $script .= "
        ->inTree(\$this->getScopeValue())";
        }
        $script .= "
        ->count(\$con) > 0;
}
";
    }

    protected function addGetPrevSibling(&$script)
    {
        $queryClassname = $this->queryClassname;
        $script .= "
/**
 * Gets previous sibling for the given node if it exists
 *
 * @param      PropelPDO \$con Connection to use.
 * @return     mixed 		Propel object if exists else false
 */
public function getPrevSibling(PropelPDO \$con = null)
{
    return $queryClassname::create()
        ->filterBy" . $this->getColumnPhpName('right_column') . "(\$this->getLeftValue() - 1)";
        if ($this->behavior->useScope()) {
            $script .= "
        ->inTree(\$this->getScopeValue())";
        }
        $script .= "
        ->findOne(\$con);
}
";
    }

    protected function addHasNextSibling(&$script)
    {
        $peerClassname = $this->peerClassname;
        $queryClassname = $this->queryClassname;
        $script .= "
/**
 * Determines if the node has next sibling
 *
 * @param      PropelPDO \$con Connection to use.
 * @return     bool
 */
public function hasNextSibling(PropelPDO \$con = null)
{
    if (!{$this->peerClassname}::isValid(\$this)) {
        return false;
    }

    return $queryClassname::create()
        ->filterBy" . $this->getColumnPhpName('left_column') . "(\$this->getRightValue() + 1)";
        if ($this->behavior->useScope()) {
            $script .= "
        ->inTree(\$this->getScopeValue())";
        }
        $script .= "
        ->count(\$con) > 0;
}
";
    }

    protected function addGetNextSibling(&$script)
    {
        $queryClassname = $this->queryClassname;
        $script .= "
/**
 * Gets next sibling for the given node if it exists
 *
 * @param      PropelPDO \$con Connection to use.
 * @return     mixed 		Propel object if exists else false
 */
public function getNextSibling(PropelPDO \$con = null)
{
    return $queryClassname::create()
        ->filterBy" . $this->getColumnPhpName('left_column') . "(\$this->getRightValue() + 1)";
        if ($this->behavior->useScope()) {
            $script .= "
        ->inTree(\$this->getScopeValue())";
        }
        $script .= "
        ->findOne(\$con);
}
";
    }

    protected function addNestedSetChildrenClear(&$script)
    {
        $script .= "
/**
 * Clears out the \$collNestedSetChildren collection
 *
 * This does not modify the database; however, it will remove any associated objects, causing
 * them to be refetched by subsequent calls to accessor method.
 *
 * @return     void
 */
public function clearNestedSetChildren()
{
    \$this->collNestedSetChildren = null;
}
";
    }

    protected function addNestedSetChildrenInit(&$script)
    {
        $script .= "
/**
 * Initializes the \$collNestedSetChildren collection.
 *
 * @return     void
 */
public function initNestedSetChildren()
{
    \$this->collNestedSetChildren = new PropelObjectCollection();
    \$this->collNestedSetChildren->setModel('" . $this->builder->getNewStubObjectBuilder($this->table)->getClassname() . "');
}
";
    }

    protected function addNestedSetChildAdd(&$script)
    {
        $objectClassname = $this->objectClassname;
        $objectName = '$' . $this->table->getStudlyPhpName();
        $script .= "
/**
 * Adds an element to the internal \$collNestedSetChildren collection.
 * Beware that this doesn't insert a node in the tree.
 * This method is only used to facilitate children hydration.
 *
 * @param      $objectClassname $objectName
 *
 * @return     void
 */
public function addNestedSetChild($objectName)
{
    if (\$this->collNestedSetChildren === null) {
        \$this->initNestedSetChildren();
    }
    if (!in_array($objectName, \$this->collNestedSetChildren->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
        \$this->collNestedSetChildren[]= $objectName;
        {$objectName}->setParent(\$this);
    }
}
";
    }

    protected function addHasChildren(&$script)
    {
        $script .= "
/**
 * Tests if node has children
 *
 * @return     bool
 */
public function hasChildren()
{
    return (\$this->getRightValue() - \$this->getLeftValue()) > 1;
}
";
    }

    protected function addGetChildren(&$script)
    {
        $objectClassname = $this->objectClassname;
        $queryClassname = $this->queryClassname;
        $script .= "
/**
 * Gets the children of the given node
 *
 * @param      Criteria  \$criteria Criteria to filter results.
 * @param      PropelPDO \$con Connection to use.
 * @return     array     List of $objectClassname objects
 */
public function getChildren(\$criteria = null, PropelPDO \$con = null)
{
    if (null === \$this->collNestedSetChildren || null !== \$criteria) {
        if (\$this->isLeaf() || (\$this->isNew() && null === \$this->collNestedSetChildren)) {
            // return empty collection
            \$this->initNestedSetChildren();
        } else {
            \$collNestedSetChildren = $queryClassname::create(null, \$criteria)
              ->childrenOf(\$this)
              ->orderByBranch()
                ->find(\$con);
            if (null !== \$criteria) {
                return \$collNestedSetChildren;
            }
            \$this->collNestedSetChildren = \$collNestedSetChildren;
        }
    }

    return \$this->collNestedSetChildren;
}
";
    }

    protected function addCountChildren(&$script)
    {
        $objectClassname = $this->objectClassname;
        $queryClassname = $this->queryClassname;
        $script .= "
/**
 * Gets number of children for the given node
 *
 * @param      Criteria  \$criteria Criteria to filter results.
 * @param      PropelPDO \$con Connection to use.
 * @return     int       Number of children
 */
public function countChildren(\$criteria = null, PropelPDO \$con = null)
{
    if (null === \$this->collNestedSetChildren || null !== \$criteria) {
        if (\$this->isLeaf() || (\$this->isNew() && null === \$this->collNestedSetChildren)) {
            return 0;
        } else {
            return $queryClassname::create(null, \$criteria)
                ->childrenOf(\$this)
                ->count(\$con);
        }
    } else {
        return count(\$this->collNestedSetChildren);
    }
}
";
    }

    protected function addGetFirstChild(&$script)
    {
        $objectClassname = $this->objectClassname;
        $queryClassname = $this->queryClassname;
        $script .= "
/**
 * Gets the first child of the given node
 *
 * @param      Criteria \$query Criteria to filter results.
 * @param      PropelPDO \$con Connection to use.
 * @return     array 		List of $objectClassname objects
 */
public function getFirstChild(\$query = null, PropelPDO \$con = null)
{
    if (\$this->isLeaf()) {
        return array();
    } else {
        return $queryClassname::create(null, \$query)
            ->childrenOf(\$this)
            ->orderByBranch()
            ->findOne(\$con);
    }
}
";
    }

    protected function addGetLastChild(&$script)
    {
        $objectClassname = $this->objectClassname;
        $queryClassname = $this->queryClassname;
        $script .= "
/**
 * Gets the last child of the given node
 *
 * @param      Criteria \$query Criteria to filter results.
 * @param      PropelPDO \$con Connection to use.
 * @return     array 		List of $objectClassname objects
 */
public function getLastChild(\$query = null, PropelPDO \$con = null)
{
    if (\$this->isLeaf()) {
        return array();
    } else {
        return $queryClassname::create(null, \$query)
            ->childrenOf(\$this)
            ->orderByBranch(true)
            ->findOne(\$con);
    }
}
";
    }

    protected function addGetSiblings(&$script)
    {
        $objectClassname = $this->objectClassname;
        $queryClassname = $this->queryClassname;
        $script .= "
/**
 * Gets the siblings of the given node
 *
 * @param      bool			\$includeNode Whether to include the current node or not
 * @param      Criteria \$query Criteria to filter results.
 * @param      PropelPDO \$con Connection to use.
 *
 * @return     array 		List of $objectClassname objects
 */
public function getSiblings(\$includeNode = false, \$query = null, PropelPDO \$con = null)
{
    if (\$this->isRoot()) {
        return array();
    } else {
         \$query = $queryClassname::create(null, \$query)
                ->childrenOf(\$this->getParent(\$con))
                ->orderByBranch();
        if (!\$includeNode) {
            \$query->prune(\$this);
        }

        return \$query->find(\$con);
    }
}
";
    }

    protected function addGetDescendants(&$script)
    {
        $objectClassname = $this->objectClassname;
        $queryClassname = $this->queryClassname;
        $script .= "
/**
 * Gets descendants for the given node
 *
 * @param      Criteria \$query Criteria to filter results.
 * @param      PropelPDO \$con Connection to use.
 * @return     array 		List of $objectClassname objects
 */
public function getDescendants(\$query = null, PropelPDO \$con = null)
{
    if (\$this->isLeaf()) {
        return array();
    } else {
        return $queryClassname::create(null, \$query)
            ->descendantsOf(\$this)
            ->orderByBranch()
            ->find(\$con);
    }
}
";
    }

    protected function addCountDescendants(&$script)
    {
        $objectClassname = $this->objectClassname;
        $queryClassname = $this->queryClassname;
        $script .= "
/**
 * Gets number of descendants for the given node
 *
 * @param      Criteria \$query Criteria to filter results.
 * @param      PropelPDO \$con Connection to use.
 * @return     int 		Number of descendants
 */
public function countDescendants(\$query = null, PropelPDO \$con = null)
{
    if (\$this->isLeaf()) {
        // save one query
        return 0;
    } else {
        return $queryClassname::create(null, \$query)
            ->descendantsOf(\$this)
            ->count(\$con);
    }
}
";
    }

    protected function addGetBranch(&$script)
    {
        $objectClassname = $this->objectClassname;
        $queryClassname = $this->queryClassname;
        $script .= "
/**
 * Gets descendants for the given node, plus the current node
 *
 * @param      Criteria \$query Criteria to filter results.
 * @param      PropelPDO \$con Connection to use.
 * @return     array 		List of $objectClassname objects
 */
public function getBranch(\$query = null, PropelPDO \$con = null)
{
    return $queryClassname::create(null, \$query)
        ->branchOf(\$this)
        ->orderByBranch()
        ->find(\$con);
}
";
    }

    protected function addGetAncestors(&$script)
    {
        $objectClassname = $this->objectClassname;
        $queryClassname = $this->queryClassname;
        $script .= "
/**
 * Gets ancestors for the given node, starting with the root node
 * Use it for breadcrumb paths for instance
 *
 * @param      Criteria \$query Criteria to filter results.
 * @param      PropelPDO \$con Connection to use.
 * @return     array 		List of $objectClassname objects
 */
public function getAncestors(\$query = null, PropelPDO \$con = null)
{
    if (\$this->isRoot()) {
        // save one query
        return array();
    } else {
        return $queryClassname::create(null, \$query)
            ->ancestorsOf(\$this)
            ->orderByBranch()
            ->find(\$con);
    }
}
";
    }

    protected function addAddChild(&$script)
    {
        $objectClassname = $this->objectClassname;
        $useScope = $this->behavior->useScope();
        $script .= "
/**
 * Inserts the given \$child node as first child of current
 * The modifications in the current object and the tree
 * are not persisted until the child object is saved.
 *
 * @param      $objectClassname \$child	Propel object for child node
 *
 * @return     $objectClassname The current Propel object
 */
public function addChild($objectClassname \$child)
{
    if (\$this->isNew()) {
        throw new PropelException('A $objectClassname object must not be new to accept children.');
    }
    \$child->insertAsFirstChildOf(\$this);

    return \$this;
}
";
    }

    protected function getPeerClassNameWithNamespace()
    {
        $peerClassname = $this->peerClassname;
        if ($namespace = $this->builder->getStubPeerBuilder()->getNamespace()) {
            $peerClassname = '\\\\' . $namespace . '\\\\' . $peerClassname;
        }

        return $peerClassname;
    }

    protected function addInsertAsFirstChildOf(&$script)
    {
        $objectClassname = $this->objectClassname;
        $peerClassname = $this->getPeerClassNameWithNamespace();
        $useScope = $this->behavior->useScope();
        $script .= "
/**
 * Inserts the current node as first child of given \$parent node
 * The modifications in the current object and the tree
 * are not persisted until the current object is saved.
 *
 * @param      $objectClassname \$parent	Propel object for parent node
 *
 * @return     $objectClassname The current Propel object
 */
public function insertAsFirstChildOf(\$parent)
{
    if (\$this->isInTree()) {
        throw new PropelException('A $objectClassname object must not already be in the tree to be inserted. Use the moveToFirstChildOf() instead.');
    }
    \$left = \$parent->getLeftValue() + 1;
    // Update node properties
    \$this->setLeftValue(\$left);
    \$this->setRightValue(\$left + 1);
    \$this->setLevel(\$parent->getLevel() + 1);";
        if ($useScope) {
            $script .= "
    \$scope = \$parent->getScopeValue();
    \$this->setScopeValue(\$scope);";
        }
        $script .= "
    // update the children collection of the parent
    \$parent->addNestedSetChild(\$this);

    // Keep the tree modification query for the save() transaction
    \$this->nestedSetQueries []= array(
        'callable'  => array('$peerClassname', 'makeRoomForLeaf'),
        'arguments' => array(\$left" . ($useScope ? ", \$scope" : "") . ", \$this->isNew() ? null : \$this)
    );

    return \$this;
}
";
    }

    protected function addInsertAsLastChildOf(&$script)
    {
        $objectClassname = $this->objectClassname;
        $peerClassname = $this->getPeerClassNameWithNamespace();
        $useScope = $this->behavior->useScope();
        $script .= "
/**
 * Inserts the current node as last child of given \$parent node
 * The modifications in the current object and the tree
 * are not persisted until the current object is saved.
 *
 * @param      $objectClassname \$parent	Propel object for parent node
 *
 * @return     $objectClassname The current Propel object
 */
public function insertAsLastChildOf(\$parent)
{
    if (\$this->isInTree()) {
        throw new PropelException('A $objectClassname object must not already be in the tree to be inserted. Use the moveToLastChildOf() instead.');
    }
    \$left = \$parent->getRightValue();
    // Update node properties
    \$this->setLeftValue(\$left);
    \$this->setRightValue(\$left + 1);
    \$this->setLevel(\$parent->getLevel() + 1);";
        if ($useScope) {
            $script .= "
    \$scope = \$parent->getScopeValue();
    \$this->setScopeValue(\$scope);";
        }
        $script .= "
    // update the children collection of the parent
    \$parent->addNestedSetChild(\$this);

    // Keep the tree modification query for the save() transaction
    \$this->nestedSetQueries []= array(
        'callable'  => array('$peerClassname', 'makeRoomForLeaf'),
        'arguments' => array(\$left" . ($useScope ? ", \$scope" : "") . ", \$this->isNew() ? null : \$this)
    );

    return \$this;
}
";
    }

    protected function addInsertAsPrevSiblingOf(&$script)
    {
        $objectClassname = $this->objectClassname;
        $peerClassname = $this->getPeerClassNameWithNamespace();
        $useScope = $this->behavior->useScope();
        $script .= "
/**
 * Inserts the current node as prev sibling given \$sibling node
 * The modifications in the current object and the tree
 * are not persisted until the current object is saved.
 *
 * @param      $objectClassname \$sibling	Propel object for parent node
 *
 * @return     $objectClassname The current Propel object
 */
public function insertAsPrevSiblingOf(\$sibling)
{
    if (\$this->isInTree()) {
        throw new PropelException('A $objectClassname object must not already be in the tree to be inserted. Use the moveToPrevSiblingOf() instead.');
    }
    \$left = \$sibling->getLeftValue();
    // Update node properties
    \$this->setLeftValue(\$left);
    \$this->setRightValue(\$left + 1);
    \$this->setLevel(\$sibling->getLevel());";
        if ($useScope) {
            $script .= "
    \$scope = \$sibling->getScopeValue();
    \$this->setScopeValue(\$scope);";
        }
        $script .= "
    // Keep the tree modification query for the save() transaction
    \$this->nestedSetQueries []= array(
        'callable'  => array('$peerClassname', 'makeRoomForLeaf'),
        'arguments' => array(\$left" . ($useScope ? ", \$scope" : "") . ", \$this->isNew() ? null : \$this)
    );

    return \$this;
}
";
    }

    protected function addInsertAsNextSiblingOf(&$script)
    {
        $objectClassname = $this->objectClassname;
        $peerClassname = $this->getPeerClassNameWithNamespace();
        $useScope = $this->behavior->useScope();
        $script .= "
/**
 * Inserts the current node as next sibling given \$sibling node
 * The modifications in the current object and the tree
 * are not persisted until the current object is saved.
 *
 * @param      $objectClassname \$sibling	Propel object for parent node
 *
 * @return     $objectClassname The current Propel object
 */
public function insertAsNextSiblingOf(\$sibling)
{
    if (\$this->isInTree()) {
        throw new PropelException('A $objectClassname object must not already be in the tree to be inserted. Use the moveToNextSiblingOf() instead.');
    }
    \$left = \$sibling->getRightValue() + 1;
    // Update node properties
    \$this->setLeftValue(\$left);
    \$this->setRightValue(\$left + 1);
    \$this->setLevel(\$sibling->getLevel());";
        if ($useScope) {
            $script .= "
    \$scope = \$sibling->getScopeValue();
    \$this->setScopeValue(\$scope);";
        }
        $script .= "
    // Keep the tree modification query for the save() transaction
    \$this->nestedSetQueries []= array(
        'callable'  => array('$peerClassname', 'makeRoomForLeaf'),
        'arguments' => array(\$left" . ($useScope ? ", \$scope" : "") . ", \$this->isNew() ? null : \$this)
    );

    return \$this;
}
";
    }

    protected function addMoveToFirstChildOf(&$script)
    {
        $objectClassname = $this->objectClassname;
        $script .= "
/**
 * Moves current node and its subtree to be the first child of \$parent
 * The modifications in the current object and the tree are immediate
 *
 * @param      $objectClassname \$parent	Propel object for parent node
 * @param      PropelPDO \$con	Connection to use.
 *
 * @return     $objectClassname The current Propel object
 */
public function moveToFirstChildOf(\$parent, PropelPDO \$con = null)
{
    if (!\$this->isInTree()) {
        throw new PropelException('A $objectClassname object must be already in the tree to be moved. Use the insertAsFirstChildOf() instead.');
    }";
        $script .= "
    if (\$parent->isDescendantOf(\$this)) {
        throw new PropelException('Cannot move a node as child of one of its subtree nodes.');
    }

    \$this->moveSubtreeTo(\$parent->getLeftValue() + 1, \$parent->getLevel() - \$this->getLevel() + 1" . ($this->behavior->useScope() ? ", \$parent->getScopeValue()" : "") . ", \$con);

    return \$this;
}
";
    }

    protected function addMoveToLastChildOf(&$script)
    {
        $objectClassname = $this->objectClassname;
        $script .= "
/**
 * Moves current node and its subtree to be the last child of \$parent
 * The modifications in the current object and the tree are immediate
 *
 * @param      $objectClassname \$parent	Propel object for parent node
 * @param      PropelPDO \$con	Connection to use.
 *
 * @return     $objectClassname The current Propel object
 */
public function moveToLastChildOf(\$parent, PropelPDO \$con = null)
{
    if (!\$this->isInTree()) {
        throw new PropelException('A $objectClassname object must be already in the tree to be moved. Use the insertAsLastChildOf() instead.');
    }";
        $script .= "
    if (\$parent->isDescendantOf(\$this)) {
        throw new PropelException('Cannot move a node as child of one of its subtree nodes.');
    }

    \$this->moveSubtreeTo(\$parent->getRightValue(), \$parent->getLevel() - \$this->getLevel() + 1" . ($this->behavior->useScope() ? ", \$parent->getScopeValue()" : "") . ", \$con);

    return \$this;
}
";
    }

    protected function addMoveToPrevSiblingOf(&$script)
    {
        $objectClassname = $this->objectClassname;
        $script .= "
/**
 * Moves current node and its subtree to be the previous sibling of \$sibling
 * The modifications in the current object and the tree are immediate
 *
 * @param      $objectClassname \$sibling	Propel object for sibling node
 * @param      PropelPDO \$con	Connection to use.
 *
 * @return     $objectClassname The current Propel object
 */
public function moveToPrevSiblingOf(\$sibling, PropelPDO \$con = null)
{
    if (!\$this->isInTree()) {
        throw new PropelException('A $objectClassname object must be already in the tree to be moved. Use the insertAsPrevSiblingOf() instead.');
    }
    if (\$sibling->isRoot()) {
        throw new PropelException('Cannot move to previous sibling of a root node.');
    }";
        $script .= "
    if (\$sibling->isDescendantOf(\$this)) {
        throw new PropelException('Cannot move a node as sibling of one of its subtree nodes.');
    }

    \$this->moveSubtreeTo(\$sibling->getLeftValue(), \$sibling->getLevel() - \$this->getLevel()" . ($this->behavior->useScope() ? ", \$sibling->getScopeValue()" : "") . ", \$con);

    return \$this;
}
";
    }

    protected function addMoveToNextSiblingOf(&$script)
    {
        $objectClassname = $this->objectClassname;
        $script .= "
/**
 * Moves current node and its subtree to be the next sibling of \$sibling
 * The modifications in the current object and the tree are immediate
 *
 * @param      $objectClassname \$sibling	Propel object for sibling node
 * @param      PropelPDO \$con	Connection to use.
 *
 * @return     $objectClassname The current Propel object
 */
public function moveToNextSiblingOf(\$sibling, PropelPDO \$con = null)
{
    if (!\$this->isInTree()) {
        throw new PropelException('A $objectClassname object must be already in the tree to be moved. Use the insertAsNextSiblingOf() instead.');
    }
    if (\$sibling->isRoot()) {
        throw new PropelException('Cannot move to next sibling of a root node.');
    }";
        $script .= "
    if (\$sibling->isDescendantOf(\$this)) {
        throw new PropelException('Cannot move a node as sibling of one of its subtree nodes.');
    }

    \$this->moveSubtreeTo(\$sibling->getRightValue() + 1, \$sibling->getLevel() - \$this->getLevel()" . ($this->behavior->useScope() ? ", \$sibling->getScopeValue()" : "") . ", \$con);

    return \$this;
}
";
    }

    protected function addMoveSubtreeTo(&$script)
    {
        $objectClassname = $this->objectClassname;
        $peerClassname = $this->peerClassname;
        $useScope = $this->behavior->useScope();
        $script .= "
/**
 * Move current node and its children to location \$destLeft and updates rest of tree
 *
 * @param      int	\$destLeft Destination left value
 * @param      int	\$levelDelta Delta to add to the levels
 * @param      PropelPDO \$con		Connection to use.
 */
protected function moveSubtreeTo(\$destLeft, \$levelDelta" . ($this->behavior->useScope() ? ", \$targetScope = null" : "") . ", PropelPDO \$con = null)
{
    \$preventDefault = false;
    \$left  = \$this->getLeftValue();
    \$right = \$this->getRightValue();";

        if ($useScope) {
            $script .= "
    \$scope = \$this->getScopeValue();

    if (\$targetScope === null) {
        \$targetScope = \$scope;
    }";
        }

        $script .= "


    \$treeSize = \$right - \$left +1;

    if (\$con === null) {
        \$con = Propel::getConnection($peerClassname::DATABASE_NAME, Propel::CONNECTION_WRITE);
    }

    \$con->beginTransaction();
    try {

        // make room next to the target for the subtree
        $peerClassname::shiftRLValues(\$treeSize, \$destLeft, null" . ($useScope ? ", \$targetScope" : "") . ", \$con);

";

        if ($useScope) {

            $script .= "

        if (\$targetScope != \$scope) {

            //move subtree to < 0, so the items are out of scope.
            $peerClassname::shiftRLValues(-\$right, \$left, \$right" . ($useScope ? ", \$scope" : "") . ", \$con);

            //update scopes
            $peerClassname::setNegativeScope(\$targetScope, \$con);

            //update levels
            $peerClassname::shiftLevel(\$levelDelta, \$left - \$right, 0" . ($useScope ? ", \$targetScope" : "") . ", \$con);

            //move the subtree to the target
            $peerClassname::shiftRLValues((\$right - \$left) + \$destLeft, \$left - \$right, 0" . ($useScope ? ", \$targetScope" : "") . ", \$con);


            \$preventDefault = true;
        }
        ";
        }

        $script .= "

        if (!\$preventDefault) {


            if (\$left >= \$destLeft) { // src was shifted too?
                \$left += \$treeSize;
                \$right += \$treeSize;
            }

            if (\$levelDelta) {
                // update the levels of the subtree
                $peerClassname::shiftLevel(\$levelDelta, \$left, \$right" . ($useScope ? ", \$scope" : "") . ", \$con);
            }

            // move the subtree to the target
            $peerClassname::shiftRLValues(\$destLeft - \$left, \$left, \$right" . ($useScope ? ", \$scope" : "") . ", \$con);
        }
        ";

        $script .= "
        // remove the empty room at the previous location of the subtree
        $peerClassname::shiftRLValues(-\$treeSize, \$right + 1, null" . ($useScope ? ", \$scope" : "") . ", \$con);

        // update all loaded nodes
        $peerClassname::updateLoadedNodes(null, \$con);

        \$con->commit();
    } catch (Exception \$e) {
        \$con->rollback();
        throw \$e;
    }
}
";
    }

    protected function addDeleteDescendants(&$script)
    {
        $objectClassname = $this->objectClassname;
        $peerClassname = $this->peerClassname;
        $queryClassname = $this->queryClassname;
        $useScope = $this->behavior->useScope();
        $script .= "
/**
 * Deletes all descendants for the given node
 * Instance pooling is wiped out by this command,
 * so existing $objectClassname instances are probably invalid (except for the current one)
 *
 * @param      PropelPDO \$con Connection to use.
 *
 * @return     int 		number of deleted nodes
 */
public function deleteDescendants(PropelPDO \$con = null)
{
    if (\$this->isLeaf()) {
        // save one query
        return;
    }
    if (\$con === null) {
        \$con = Propel::getConnection($peerClassname::DATABASE_NAME, Propel::CONNECTION_READ);
    }
    \$left = \$this->getLeftValue();
    \$right = \$this->getRightValue();";
        if ($useScope) {
            $script .= "
    \$scope = \$this->getScopeValue();";
        }
        $script .= "
    \$con->beginTransaction();
    try {
        // delete descendant nodes (will empty the instance pool)
        \$ret = $queryClassname::create()
            ->descendantsOf(\$this)
            ->delete(\$con);

        // fill up the room that was used by descendants
        $peerClassname::shiftRLValues(\$left - \$right + 1, \$right, null" . ($useScope ? ", \$scope" : "") . ", \$con);

        // fix the right value for the current node, which is now a leaf
        \$this->setRightValue(\$left + 1);

        \$con->commit();
    } catch (Exception \$e) {
        \$con->rollback();
        throw \$e;
    }

    return \$ret;
}
";
    }

    protected function addGetIterator(&$script)
    {
        $this->builder->declareClassNamespace('NestedSetRecursiveIterator');
        $script .= "
/**
 * Returns a pre-order iterator for this node and its children.
 *
 * @return     RecursiveIterator
 */
public function getIterator()
{
    return new NestedSetRecursiveIterator(\$this);
}
";
    }

    protected function addCompatibilityProxies(&$script)
    {
        $objectClassname = $this->objectClassname;
        $script .= "
/**
 * Alias for makeRoot(), for BC with Propel 1.4 nested sets
 *
 * @deprecated since 1.5
 * @see        makeRoot
 */
public function createRoot()
{
    return \$this->makeRoot();
}

/**
 * Alias for getParent(), for BC with Propel 1.4 nested sets
 *
 * @deprecated since 1.5
 * @see        getParent
 */
public function retrieveParent(PropelPDO \$con = null)
{
    return \$this->getParent(\$con);
}

/**
 * Alias for setParent(), for BC with Propel 1.4 nested sets
 *
 * @deprecated since 1.5
 * @see        setParent
 */
public function setParentNode(\$parent = null)
{
    return \$this->setParent(\$parent);
}

/**
 * Alias for countDescendants(), for BC with Propel 1.4 nested sets
 *
 * @deprecated since 1.5
 * @see        setParent
 */
public function getNumberOfDescendants(PropelPDO \$con = null)
{
    return \$this->countDescendants(null, \$con);
}

/**
 * Alias for countChildren(), for BC with Propel 1.4 nested sets
 *
 * @deprecated since 1.5
 * @see        setParent
 */
public function getNumberOfChildren(PropelPDO \$con = null)
{
    return \$this->countChildren(null, \$con);
}

/**
 * Alias for getPrevSibling(), for BC with Propel 1.4 nested sets
 *
 * @deprecated since 1.5
 * @see        getParent
 */
public function retrievePrevSibling(PropelPDO \$con = null)
{
    return \$this->getPrevSibling(\$con);
}

/**
 * Alias for getNextSibling(), for BC with Propel 1.4 nested sets
 *
 * @deprecated since 1.5
 * @see        getParent
 */
public function retrieveNextSibling(PropelPDO \$con = null)
{
    return \$this->getNextSibling(\$con);
}

/**
 * Alias for getFirstChild(), for BC with Propel 1.4 nested sets
 *
 * @deprecated since 1.5
 * @see        getParent
 */
public function retrieveFirstChild(PropelPDO \$con = null)
{
    return \$this->getFirstChild(null, \$con);
}

/**
 * Alias for getLastChild(), for BC with Propel 1.4 nested sets
 *
 * @deprecated since 1.5
 * @see        getParent
 */
public function retrieveLastChild(PropelPDO \$con = null)
{
    return \$this->getLastChild(null, \$con);
}

/**
 * Alias for getAncestors(), for BC with Propel 1.4 nested sets
 *
 * @deprecated since 1.5
 * @see        getAncestors
 */
public function getPath(PropelPDO \$con = null)
{
    \$path = \$this->getAncestors(null, \$con);
    \$path []= \$this;

    return \$path;
}
";
    }
}
