<?php

/**
 * This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

/**
 * TableMap is used to model a table in a database.
 *
 * GENERAL NOTE
 * ------------
 * The propel.map classes are abstract building-block classes for modeling
 * the database at runtime.  These classes are similar (a lite version) to the
 * propel.engine.database.model classes, which are build-time modeling classes.
 * These classes in themselves do not do any database metadata lookups.
 *
 * @author     Hans Lellelid <hans@xmpl.org> (Propel)
 * @author     John D. McNally <jmcnally@collab.net> (Torque)
 * @author     Daniel Rall <dlr@finemaltcoding.com> (Torque)
 * @version    $Revision$
 * @package    propel.runtime.map
 */
class TableMap
{
    /**
     * Columns in the table
     *
     * @var ColumnMap[]
     */
    protected $columns = array();

    /**
     * Columns in the table, using table phpName as key
     *
     * @var ColumnMap[]
     */
    protected $columnsByPhpName = array();

    /**
     * Columns in the table, using  as key
     *
     * @var ColumnMap[]
     */
    protected $columnsByInsensitiveCase = array();

    /**
     * The database this table belongs to
     *
     * @var DatabaseMap
     */
    protected $dbMap;

    // The name of the table
    protected $tableName;

    // The PHP name of the table
    protected $phpName;

    // The Classname for this table
    protected $classname;

    // The Package for this table
    protected $package;

    // Whether to use an id generator for pkey
    protected $useIdGenerator;

    // Whether the table uses single table inheritance
    protected $isSingleTableInheritance = false;

    // Whether the table is a Many to Many table
    protected $isCrossRef = false;

    // The primary key columns in the table
    protected $primaryKeys = array();

    // The foreign key columns in the table
    protected $foreignKeys = array();

    /**
     * The relationships in the table
     *
     * @var RelationMap[]
     */
    protected $relations = array();

    // Relations are lazy loaded. This property tells if the relations are loaded or not
    protected $relationsBuilt = false;

    // Object to store information that is needed if the for generating primary keys
    protected $pkInfo;

    /**
     * Construct a new TableMap.
     *
     */
    public function __construct($name = null, $dbMap = null)
    {
        if (null !== $name) {
            $this->setName($name);
        }
        if (null !== $dbMap) {
            $this->setDatabaseMap($dbMap);
        }
        $this->initialize();
    }

    /**
     * Initialize the TableMap to build columns, relations, etc
     * This method should be overridden by descendents
     */
    public function initialize()
    {
    }

    /**
     * Set the DatabaseMap containing this TableMap.
     *
     * @param DatabaseMap $dbMap A DatabaseMap.
     */
    public function setDatabaseMap(DatabaseMap $dbMap)
    {
        $this->dbMap = $dbMap;
    }

    /**
     * Get the DatabaseMap containing this TableMap.
     *
     * @return DatabaseMap A DatabaseMap.
     */
    public function getDatabaseMap()
    {
        return $this->dbMap;
    }

    /**
     * Set the name of the Table.
     *
     * @param string $name The name of the table.
     */
    public function setName($name)
    {
        $this->tableName = $name;
    }

    /**
     * Get the name of the Table.
     *
     * @return string A String with the name of the table.
     */
    public function getName()
    {
        return $this->tableName;
    }

    /**
     * Set the PHP name of the Table.
     *
     * @param string $phpName The PHP Name for this table
     */
    public function setPhpName($phpName)
    {
        $this->phpName = $phpName;
    }

    /**
     * Get the PHP name of the Table.
     *
     * @return string A String with the name of the table.
     */
    public function getPhpName()
    {
        return $this->phpName;
    }

    /**
     * Set the Classname of the Table. Could be useful for calling
     * Peer and Object methods dynamically.
     *
     * @param string $classname The Classname
     */
    public function setClassname($classname)
    {
        $this->classname = $classname;
    }

    /**
     * Get the Classname of the Propel Class belonging to this table.
     *
     * @return string
     */
    public function getClassname()
    {
        return $this->classname;
    }

    /**
     * Get the Peer Classname of the Propel Class belonging to this table.
     *
     * @return string
     */
    public function getPeerClassname()
    {
        return constant($this->classname . '::PEER');
    }

    /**
     * Set the Package of the Table
     *
     * @param string $package The Package
     */
    public function setPackage($package)
    {
        $this->package = $package;
    }

    /**
     * Get the Package of the table.
     *
     * @return string
     */
    public function getPackage()
    {
        return $this->package;
    }

    /**
     * Set whether or not to use Id generator for primary key.
     *
     * @param boolean $bit
     */
    public function setUseIdGenerator($bit)
    {
        $this->useIdGenerator = $bit;
    }

    /**
     * Whether to use Id generator for primary key.
     *
     * @return boolean
     */
    public function isUseIdGenerator()
    {
        return $this->useIdGenerator;
    }

    /**
     * Set whether or not to this table uses single table inheritance
     *
     * @param boolean $bit
     */
    public function setSingleTableInheritance($bit)
    {
        $this->isSingleTableInheritance = $bit;
    }

    /**
     * Whether this table uses single table inheritance
     *
     * @return boolean
     */
    public function isSingleTableInheritance()
    {
        return $this->isSingleTableInheritance;
    }

    /**
     * Sets the name of the sequence used to generate a key
     *
     * @param   $pkInfo information needed to generate a key
     */
    public function setPrimaryKeyMethodInfo($pkInfo)
    {
        $this->pkInfo = $pkInfo;
    }

    /**
     * Get the name of the sequence used to generate a primary key
     *
     * @return An Object.
     */
    public function getPrimaryKeyMethodInfo()
    {
        return $this->pkInfo;
    }

    /**
     * Add a column to the table.
     *
     * @param string  $name         A string with the column name.
     * @param string  $phpName      A string specifying php name.
     * @param string  $type         A string specifying the Propel type.
     * @param boolean $isNotNull    Whether column does not allow NULL values.
     * @param int     $size         An int specifying the size.
     * @param boolean $pk           True if column is a primary key.
     * @param string  $fkTable      A String with the foreign key table name.
     * @param string  $fkColumn     A String with the foreign key column name.
     * @param string  $defaultValue The default value for this column.
     *
     * @return ColumnMap The newly created column.
     */
    public function addColumn($name, $phpName, $type, $isNotNull = false, $size = null, $defaultValue = null, $pk = false, $fkTable = null, $fkColumn = null)
    {
        $col = new ColumnMap($name, $this);
        $col->setType($type);
        $col->setSize($size);
        $col->setPhpName($phpName);
        $col->setNotNull($isNotNull);
        $col->setDefaultValue($defaultValue);

        if ($pk) {
            $col->setPrimaryKey(true);
            $this->primaryKeys[$name] = $col;
        }

        if ($fkTable && $fkColumn) {
            $col->setForeignKey($fkTable, $fkColumn);
            $this->foreignKeys[$name] = $col;
        }

        $this->columns[$name] = $col;
        $this->columnsByPhpName[$phpName] = $col;
        $this->columnsByInsensitiveCase[strtolower($phpName)] = $col;

        return $col;
    }

    /**
     * Add a pre-created column to this table. It will replace any
     * existing column.
     *
     * @param ColumnMap $cmap A ColumnMap.
     *
     * @return ColumnMap The added column map.
     */
    public function addConfiguredColumn($cmap)
    {
        $this->columns[$cmap->getName()] = $cmap;

        return $cmap;
    }

    /**
     * Does this table contain the specified column?
     *
     * @param mixed   $name      name of the column or ColumnMap instance
     * @param boolean $normalize Normalize the column name (if column name not like FIRST_NAME)
     *
     * @return boolean True if the table contains the column.
     */
    public function hasColumn($name, $normalize = true)
    {
        if ($name instanceof ColumnMap) {
            $name = $name->getColumnName();
        } elseif ($normalize) {
            $name = ColumnMap::normalizeName($name);
        }

        return isset($this->columns[$name]);
    }

    /**
     * Get a ColumnMap for the table.
     *
     * @param string  $name      A String with the name of the table.
     * @param boolean $normalize Normalize the column name (if column name not like FIRST_NAME)
     *
     * @return ColumnMap       A ColumnMap.
     * @throws PropelException if the column is undefined
     */
    public function getColumn($name, $normalize = true)
    {
        if ($normalize) {
            $name = ColumnMap::normalizeName($name);
        }
        if (!$this->hasColumn($name, false)) {
            throw new PropelException("Cannot fetch ColumnMap for undefined column: " . $name);
        }

        return $this->columns[$name];
    }

    /**
     * Does this table contain the specified column?
     *
     * @param mixed $phpName name of the column
     *
     * @return boolean True if the table contains the column.
     */
    public function hasColumnByPhpName($phpName)
    {
        return isset($this->columnsByPhpName[$phpName]);
    }

    /**
     * Get a ColumnMap for the table.
     *
     * @param string $phpName A String with the name of the table.
     *
     * @return ColumnMap       A ColumnMap.
     * @throws PropelException if the column is undefined
     */
    public function getColumnByPhpName($phpName)
    {
        if (!isset($this->columnsByPhpName[$phpName])) {
            throw new PropelException("Cannot fetch ColumnMap for undefined column phpName: " . $phpName);
        }

        return $this->columnsByPhpName[$phpName];
    }

    public function hasColumnByInsensitiveCase($colName)
    {
        return isset($this->columnsByInsensitiveCase[strtolower($colName)]);
    }

    public function getColumnByInsensitiveCase($colName)
    {
        $colName = strtolower($colName);
        if (!isset($this->columnsByInsensitiveCase[$colName])) {
            throw new PropelException("Cannot fetch ColumnMap for undefined column name: " . $colName);
        }

        return $this->columnsByInsensitiveCase[$colName];
    }

    /**
     * Get a ColumnMap[] of the columns in this table.
     *
     * @return ColumnMap[]
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Add a primary key column to this Table.
     *
     * @param string  $columnName   A string with the column name.
     * @param string  $phpName      A string with the php name.
     * @param string  $type         A string specifying the Propel type.
     * @param boolean $isNotNull    Whether column does not allow NULL values.
     * @param int     $size         An int specifying the size.
     * @param string  $defaultValue
     *
     * @return ColumnMap Newly added PrimaryKey column.
     */
    public function addPrimaryKey($columnName, $phpName, $type, $isNotNull = false, $size = null, $defaultValue = null)
    {
        return $this->addColumn($columnName, $phpName, $type, $isNotNull, $size, $defaultValue, true, null, null);
    }

    /**
     * Add a foreign key column to the table.
     *
     * @param string  $columnName   A String with the column name.
     * @param string  $phpName      A string with the php name.
     * @param string  $type         A string specifying the Propel type.
     * @param string  $fkTable      A String with the foreign key table name.
     * @param string  $fkColumn     A String with the foreign key column name.
     * @param boolean $isNotNull    Whether column does not allow NULL values.
     * @param int     $size         An int specifying the size.
     * @param string  $defaultValue The default value for this column.
     *
     * @return ColumnMap Newly added ForeignKey column.
     */
    public function addForeignKey($columnName, $phpName, $type, $fkTable, $fkColumn, $isNotNull = false, $size = 0, $defaultValue = null)
    {
        return $this->addColumn($columnName, $phpName, $type, $isNotNull, $size, $defaultValue, false, $fkTable, $fkColumn);
    }

    /**
     * Add a foreign primary key column to the table.
     *
     * @param string  $columnName   A String with the column name.
     * @param string  $phpName      A string with the php name.
     * @param string  $type         A string specifying the Propel type.
     * @param string  $fkTable      A String with the foreign key table name.
     * @param string  $fkColumn     A String with the foreign key column name.
     * @param boolean $isNotNull    Whether column does not allow NULL values.
     * @param int     $size         An int specifying the size.
     * @param string  $defaultValue The default value for this column.
     *
     * @return ColumnMap Newly created foreign pkey column.
     */
    public function addForeignPrimaryKey($columnName, $phpName, $type, $fkTable, $fkColumn, $isNotNull = false, $size = 0, $defaultValue = null)
    {
        return $this->addColumn($columnName, $phpName, $type, $isNotNull, $size, $defaultValue, true, $fkTable, $fkColumn);
    }

    /**
     * @return boolean true if the table is a many to many
     */
    public function isCrossRef()
    {
        return $this->isCrossRef;
    }

    /**
     * set the isCrossRef
     *
     * @param boolean $isCrossRef
     */
    public function setIsCrossRef($isCrossRef)
    {
        $this->isCrossRef = $isCrossRef;
    }

    /**
     * Returns array of ColumnMap objects that make up the primary key for this table
     *
     * @return array ColumnMap[]
     */
    public function getPrimaryKeys()
    {
        return $this->primaryKeys;
    }

    /**
     * Returns array of ColumnMap objects that are foreign keys for this table
     *
     * @return array ColumnMap[]
     */
    public function getForeignKeys()
    {
        return $this->foreignKeys;
    }

    /**
     * Add a validator to a table's column
     *
     * @param string $columnName The name of the validator's column
     * @param string $name       The rule name of this validator
     * @param string $classname  The dot-path name of class to use (e.g. myapp.propel.MyValidator)
     * @param string $value
     * @param string $message    The error message which is returned on invalid values
     *
     * @return void
     */
    public function addValidator($columnName, $name, $classname, $value, $message)
    {
        if (false !== ($pos = strpos($columnName, '.'))) {
            $columnName = substr($columnName, $pos + 1);
        }

        $col = $this->getColumn($columnName);
        if ($col !== null) {
            $validator = new ValidatorMap($col);
            $validator->setName($name);
            $validator->setClass($classname);
            $validator->setValue($value);
            $validator->setMessage($message);
            $col->addValidator($validator);
        }
    }

    /**
     * Build relations
     * Relations are lazy loaded for performance reasons
     * This method should be overridden by descendents
     */
    public function buildRelations()
    {
    }

    /**
     * Adds a RelationMap to the table
     *
     * @param string  $name          The relation name
     * @param string  $tablePhpName  The related table name
     * @param integer $type          The relation type (either RelationMap::MANY_TO_ONE, RelationMap::ONE_TO_MANY, or RelationMAp::ONE_TO_ONE)
     * @param array   $columnMapping An associative array mapping column names (local => foreign)
     * @param string  $onDelete      SQL behavior upon deletion ('SET NULL', 'CASCADE', ...)
     * @param string  $onUpdate      SQL behavior upon update ('SET NULL', 'CASCADE', ...)
     * @param string  $pluralName    Optional plural name for *_TO_MANY relationships
     *
     * @return RelationMap the built RelationMap object
     */
    public function addRelation($name, $tablePhpName, $type, $columnMapping = array(), $onDelete = null, $onUpdate = null, $pluralName = null)
    {
        // note: using phpName for the second table allows the use of DatabaseMap::getTableByPhpName()
        // and this method autoloads the TableMap if the table isn't loaded yet
        $relation = new RelationMap($name);
        $relation->setType($type);
        $relation->setOnUpdate($onUpdate);
        $relation->setOnDelete($onDelete);
        if (null !== $pluralName) {
            $relation->setPluralName($pluralName);
        }
        // set tables
        if ($type == RelationMap::MANY_TO_ONE) {
            $relation->setLocalTable($this);
            $relation->setForeignTable($this->dbMap->getTableByPhpName($tablePhpName));
        } else {
            $relation->setLocalTable($this->dbMap->getTableByPhpName($tablePhpName));
            $relation->setForeignTable($this);
            $columnMapping = array_flip($columnMapping);
        }
        // set columns
        foreach ($columnMapping as $local => $foreign) {
            $relation->addColumnMapping(
                $relation->getLocalTable()->getColumn($local),
                $relation->getForeignTable()->getColumn($foreign)
            );
        }
        $this->relations[$name] = $relation;

        return $relation;
    }

    /**
     * Gets a RelationMap of the table by relation name
     * This method will build the relations if they are not built yet
     *
     * @param String $name The relation name
     *
     * @return boolean true if the relation exists
     */
    public function hasRelation($name)
    {
        return array_key_exists($name, $this->getRelations());
    }

    /**
     * Gets a RelationMap of the table by relation name
     * This method will build the relations if they are not built yet
     *
     * @param String $name The relation name
     *
     * @return RelationMap     The relation object
     * @throws PropelException When called on an inexistent relation
     */
    public function getRelation($name)
    {
        if (!array_key_exists($name, $this->getRelations())) {
            throw new PropelException('Calling getRelation() on an unknown relation, ' . $name);
        }

        return $this->relations[$name];
    }

    /**
     * Gets the RelationMap objects of the table
     * This method will build the relations if they are not built yet
     *
     * @return RelationMap[]
     */
    public function getRelations()
    {
        if (!$this->relationsBuilt) {
            $this->buildRelations();
            $this->relationsBuilt = true;
        }

        return $this->relations;
    }

    /**
     *
     * Gets the list of behaviors registered for this table
     *
     * @return array
     */
    public function getBehaviors()
    {
        return array();
    }

    /**
     * Does this table has a primaryString column?
     *
     * @return boolean True if the table has a primaryString column.
     */
    public function hasPrimaryStringColumn()
    {
        return null !== $this->getPrimaryStringColumn();
    }

    /**
     * Gets the ColumnMap for the primary string column.
     *
     * @return ColumnMap|null The primary string column, null if none given.
     */
    public function getPrimaryStringColumn()
    {
        foreach ($this->getColumns() as $column) {
            if ($column->isPrimaryString()) {
                return $column;
            }
        }

        return null;
    }

    // Deprecated methods and attributes, to be removed

    /**
     * Does this table contain the specified column?
     *
     * @deprecated Use hasColumn instead
     *
     * @param mixed   $name      name of the column or ColumnMap instance
     * @param boolean $normalize Normalize the column name (if column name not like FIRST_NAME)
     *
     * @return boolean True if the table contains the column.
     */
    public function containsColumn($name, $normalize = true)
    {
        return $this->hasColumn($name, $normalize);
    }

    /**
     * Normalizes the column name, removing table prefix and uppercasing.
     * article.first_name becomes FIRST_NAME
     *
     * @deprecated Use ColumnMap::normalizeColumnName() instead
     *
     * @param string $name
     *
     * @return string Normalized column name.
     */
    protected function normalizeColName($name)
    {
        return ColumnMap::normalizeName($name);
    }

    /**
     * Returns array of ColumnMap objects that make up the primary key for this table.
     *
     * @deprecated Use getPrimaryKeys instead
     * @return ColumnMap[]
     */
    public function getPrimaryKeyColumns()
    {
        return array_values($this->primaryKeys);
    }

    //---Utility methods for doing intelligent lookup of table names

    /**
     * The prefix on the table name.
     *
     * @deprecated Not used anywhere in Propel
     */
    private $prefix;

    /**
     * Get table prefix name.
     *
     * @deprecated Not used anywhere in Propel
     * @return string A String with the prefix.
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * Set table prefix name.
     *
     * @deprecated Not used anywhere in Propel
     *
     * @param string $prefix The prefix for the table name (ie: SCARAB for
     * SCARAB_PROJECT).
     *
     * @return void
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * Tell me if i have PREFIX in my string.
     *
     * @deprecated Not used anywhere in Propel
     *
     * @param string $data
     *
     * @return boolean True if prefix is contained in data.
     */
    protected function hasPrefix($data)
    {
        if (!$this->prefix) {
            return false;
        }

        return (strpos($data, (string) $this->prefix) === 0);
    }

    /**
     * Removes the PREFIX if found
     *
     * @deprecated Not used anywhere in Propel
     *
     * @param string $data A String.
     *
     * @return string A String with data, but with prefix removed.
     */
    protected function removePrefix($data)
    {
        return $this->hasPrefix($data) ? substr($data, strlen($this->prefix)) : $data;
    }

    /**
     * Removes the PREFIX, removes the underscores and makes
     * first letter caps.
     *
     * SCARAB_FOO_BAR becomes FooBar.
     *
     * @deprecated Not used anywhere in Propel. At buildtime, use Column::generatePhpName() for that purpose
     *
     * @param string $data
     *
     * @return string A String with data processed.
     */
    final public function removeUnderScores($data): string
    {
        $out = '';
        $tmp = $this->removePrefix($data);
        $tok = strtok($tmp, '_');
        while ($tok) {
            $out .= ucfirst($tok);
            $tok = strtok('_');
        }

        return $out;
    }

    /**
     * Makes the first letter caps and the rest lowercase.
     *
     * @deprecated Not used anywhere in Propel.
     *
     * @param string $data A String.
     *
     * @return string A String with data processed.
     */
    private function firstLetterCaps($data): string
    {
        return (ucfirst(strtolower($data)));
    }
}
