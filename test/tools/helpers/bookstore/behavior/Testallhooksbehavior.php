<?php

/**
 * This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

class TestAllHooksBehavior extends Behavior
{
  protected $tableModifier, $objectBuilderModifier, $peerBuilderModifier, $queryBuilderModifier;

  public function getTableModifier()
  {
    if (is_null($this->tableModifier)) {
      $this->tableModifier = new TestAllHooksTableModifier($this);
    }

    return $this->tableModifier;
  }

  public function getObjectBuilderModifier()
  {
    if (is_null($this->objectBuilderModifier)) {
      $this->objectBuilderModifier = new TestAllHooksObjectBuilderModifier($this);
    }

    return $this->objectBuilderModifier;
  }

  public function getPeerBuilderModifier()
  {
    if (is_null($this->peerBuilderModifier)) {
      $this->peerBuilderModifier = new TestAllHooksPeerBuilderModifier($this);
    }

    return $this->peerBuilderModifier;
  }

  public function getQueryBuilderModifier()
  {
    if (is_null($this->queryBuilderModifier)) {
      $this->queryBuilderModifier = new TestAllHooksQueryBuilderModifier($this);
    }

    return $this->queryBuilderModifier;
  }
}

class TestAllHooksTableModifier
{
  protected $behavior, $table;

  public function __construct($behavior)
  {
    $this->behavior = $behavior;
    $this->table = $behavior->getTable();
  }

  public function modifyTable()
  {
    $this->table->addColumn(array(
      'name' => 'test',
      'type' => 'TIMESTAMP'
    ));
  }
}

class TestAllHooksObjectBuilderModifier
{
  public function objectAttributes($builder): string
  {
    return 'public $customAttribute = 1;';
  }

  public function preSave($builder): string
  {
    return '$this->preSave = 1;$this->preSaveIsAfterSave = isset($affectedRows);$this->preSaveBuilder="' . get_class($builder) . '";';
  }

  public function postSave($builder): string
  {
    return '$this->postSave = 1;$this->postSaveIsAfterSave = isset($affectedRows);$this->postSaveBuilder="' . get_class($builder) . '";';
  }

  public function preInsert($builder): string
  {
    return '$this->preInsert = 1;$this->preInsertIsAfterSave = isset($affectedRows);$this->preInsertBuilder="' . get_class($builder) . '";';
  }

  public function postInsert($builder): string
  {
    return '$this->postInsert = 1;$this->postInsertIsAfterSave = isset($affectedRows);$this->postInsertBuilder="' . get_class($builder) . '";';
  }

  public function preUpdate($builder): string
  {
    return '$this->preUpdate = 1;$this->preUpdateIsAfterSave = isset($affectedRows);$this->preUpdateBuilder="' . get_class($builder) . '";';
  }

  public function postUpdate($builder): string
  {
    return '$this->postUpdate = 1;$this->postUpdateIsAfterSave = isset($affectedRows);$this->postUpdateBuilder="' . get_class($builder) . '";';
  }

  public function preDelete($builder): string
  {
    return '$this->preDelete = 1;$this->preDeleteIsBeforeDelete = isset(Table3Peer::$instances[$this->id]);$this->preDeleteBuilder="' . get_class($builder) . '";';
  }

  public function postDelete($builder): string
  {
    return '$this->postDelete = 1;$this->postDeleteIsBeforeDelete = isset(Table3Peer::$instances[$this->id]);$this->postDeleteBuilder="' . get_class($builder) . '";';
  }

  public function postHydrate($builder): string
  {
    return '$this->postHydrate = 1;$this->postHydrateIsAfterHydrate = isset($this->id);$this->postHydrateBuilder="' . get_class($builder) . '";';
  }

  public function objectMethods($builder): string
  {
    return 'public function hello() { return "' . get_class($builder) .'"; }';
  }

  public function objectCall($builder): string
  {
      return 'if ($name == "foo") return "bar";';
  }

  public function objectFilter(&$string, $builder)
  {
    $string .= 'class testObjectFilter { const FOO = "' . get_class($builder) . '"; }';
  }
}

class TestAllHooksPeerBuilderModifier
{
  public function staticAttributes($builder): string
  {
    return 'public static $customStaticAttribute = 1;public static $staticAttributeBuilder = "' . get_class($builder) . '";';
  }

  public function staticMethods($builder): string
  {
    return 'public static function hello() { return "' . get_class($builder) . '"; }';
  }

  public function preSelect($builder): string
  {
    return '$con->preSelect = "' . get_class($builder) . '";';
  }

  public function peerFilter(&$string, $builder)
  {
    $string .= 'class testPeerFilter { const FOO = "' . get_class($builder) . '"; }';
  }
}

class TestAllHooksQueryBuilderModifier
{
    public function preSelectQuery($builder): string
    {
        return '// foo';
    }

    public function preDeleteQuery($builder): string
    {
        return '// foo';
    }

    public function postDeleteQuery($builder): string
    {
        return '// foo';
    }

    public function preUpdateQuery($builder): string
    {
        return '// foo';
    }

    public function postUpdateQuery($builder): string
    {
        return '// foo';
    }
}
