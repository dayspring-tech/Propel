<?php

/**
 * This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

/**
 * Keeps an aggregate column updated with related table
 *
 * @author     François Zaninotto
 * @version    $Revision$
 * @package    propel.generator.behavior.aggregate_column
 */
class AggregateColumnRelationBehavior extends Behavior
{

    // default parameters value
    protected $parameters = array(
        'foreign_table' => '',
        'update_method' => '',
    );

    public function postSave($builder): string
    {
        $relationName = $this->getRelationName($builder);

        return "\$this->updateRelated{$relationName}(\$con);";
    }

    // no need for a postDelete() hook, since delete() uses Query::delete(),
    // which already has a hook

    public function objectAttributes($builder): string
    {
        $relationName = $this->getRelationName($builder);

        return "protected \$old{$relationName};
";
    }

    public function objectMethods($builder)
    {
        return $this->addObjectUpdateRelated($builder);
    }

    protected function addObjectUpdateRelated($builder)
    {
        $relationName = $this->getRelationName($builder);
        $updateMethodName = $this->getParameter('update_method');

        return $this->renderTemplate('objectUpdateRelated', array(
            'relationName'     => $relationName,
            'variableName'     => lcfirst($relationName),
            'updateMethodName' => $this->getParameter('update_method'),
        ));
    }

    public function objectFilter(&$script, $builder)
    {
        $relationName = $this->getRelationName($builder);
        $relatedClass = $this->getForeignTable()->getPhpName();
        $search = "public function set{$relationName}({$relatedClass} \$v = null)
    {";
        $replace = $search . "
        // aggregate_column_relation behavior
        if (null !== \$this->a{$relationName} && \$v !== \$this->a{$relationName}) {
            \$this->old{$relationName} = \$this->a{$relationName};
        }";
        $script = str_replace($search, $replace, $script);
    }

    public function preUpdateQuery($builder)
    {
        return $this->getFindRelated($builder);
    }

    public function preDeleteQuery($builder)
    {
        return $this->getFindRelated($builder);
    }

    protected function getFindRelated($builder): string
    {
        $relationName = $this->getRelationName($builder);

        return "\$this->findRelated{$relationName}s(\$con);";
    }

    public function postUpdateQuery($builder)
    {
        return $this->getUpdateRelated($builder);
    }

    public function postDeleteQuery($builder)
    {
        return $this->getUpdateRelated($builder);
    }

    protected function getUpdateRelated($builder): string
    {
        $relationName = $this->getRelationName($builder);

        return "\$this->updateRelated{$relationName}s(\$con);";
    }

    public function queryMethods($builder): string
    {
        $script = '';
        $script .= $this->addQueryFindRelated($builder);
        $script .= $this->addQueryUpdateRelated($builder);

        return $script;
    }

    protected function addQueryFindRelated($builder)
    {
        $foreignKey = $this->getForeignKey();
        $foreignQueryBuilder = $builder->getNewStubQueryBuilder($foreignKey->getForeignTable());
        $builder->declareClass($foreignQueryBuilder->getFullyQualifiedClassname());
        $relationName = $this->getRelationName($builder);

        return $this->renderTemplate('queryFindRelated', array(
            'foreignTable'     => $this->getForeignTable(),
            'relationName'     => $relationName,
            'variableName'     => lcfirst($relationName),
            'foreignQueryName' => $foreignQueryBuilder->getClassname(),
            'refRelationName'  => $builder->getRefFKPhpNameAffix($foreignKey),
        ));
    }

    protected function addQueryUpdateRelated($builder)
    {
        $relationName = $this->getRelationName($builder);

        return $this->renderTemplate('queryUpdateRelated', array(
            'relationName'     => $relationName,
            'variableName'     => lcfirst($relationName),
            'updateMethodName' => $this->getParameter('update_method'),
        ));
    }

    protected function getForeignTable()
    {
        return $this->getTable()->getDatabase()->getTable($this->getParameter('foreign_table'));
    }

    protected function getForeignKey()
    {
        $foreignTable = $this->getForeignTable();
        // let's infer the relation from the foreign table
        $fks = $this->getTable()->getForeignKeysReferencingTable($foreignTable->getName());

        // FIXME doesn't work when more than one fk to the same table
        return array_shift($fks);
    }

    protected function getRelationName($builder)
    {
        return $builder->getFKPhpNameAffix($this->getForeignKey());
    }
}
