<?php

/**
 * This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

/**
 * PDOStatement that provides some enhanced functionality needed by Propel.
 *
 * Simply adds the ability to count the number of queries executed and log the queries/method calls.
 *
 * @author     Oliver Schonrock <oliver@realtsp.com>
 * @author     Jarno Rantanen <jarno.rantanen@tkk.fi>
 * @since      2007-07-12
 * @package    propel.runtime.connection
 */
class DebugPDOStatement extends PDOStatement
{
    /**
     * The PDO connection from which this instance was created.
     *
     * @var       PropelPDO
     */
    protected $pdo;

    /**
     * Hashmap for resolving the PDO::PARAM_* class constants to their human-readable names.
     * This is only used in logging the binding of variables.
     *
     * @see       self::bindValue()
     * @var       array
     */
    protected static $typeMap = array(
        PDO::PARAM_BOOL => "PDO::PARAM_BOOL",
        PDO::PARAM_INT => "PDO::PARAM_INT",
        PDO::PARAM_STR => "PDO::PARAM_STR",
        PDO::PARAM_LOB => "PDO::PARAM_LOB",
        PDO::PARAM_NULL => "PDO::PARAM_NULL",
    );

    /**
     * @var       array  The values that have been bound
     */
    protected $boundValues = array();

    /**
     * Construct a new statement class with reference to main DebugPDO object from
     * which this instance was created.
     *
     * @param PropelPDO $pdo Reference to the parent PDO instance.
     *
     * @return DebugPDOStatement
     */
    protected function __construct(PropelPDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param array $values Parameters which were passed to execute(), if any. Default: bound parameters.
     *
     * @return string
     */
    public function getExecutedQueryString(array $values = array())
    {
        $sql = $this->queryString;
        $boundValues = empty($values) ? $this->boundValues : $values;
        $matches = array();
        if (preg_match_all('/(:p[0-9]+\b)/', $sql, $matches)) {
            $size = count($matches[1]);
            for ($i = $size - 1; $i >= 0; $i--) {
                $pos = $matches[1][$i];

                // trimming extra quotes, making sure value is properly quoted afterwards
                $boundValue = $boundValues[$pos];
                if (is_string($boundValue)) { // quoting only needed for string values
                    $boundValue = trim($boundValue, "'");
                    $boundValue = $this->pdo->quote($boundValue);
                }

                if (is_resource($boundValue)) {
                    $boundValue = '[BLOB]';
                }

                $sql = str_replace($pos, (string)$boundValue, $sql);
            }
        }

        return $sql;
    }

    /**
     * Executes a prepared statement.  Returns a boolean value indicating success.
     * Overridden for query counting and logging.
     *
     * @param string $input_parameters
     *
     * @return boolean
     */
    public function execute($input_parameters = null): bool
    {
        $debug = $this->pdo->getDebugSnapshot();
        $return = parent::execute($input_parameters);

        $sql = $this->getExecutedQueryString($input_parameters ? $input_parameters : []);
        $this->pdo->log($sql, null, __METHOD__, $debug);
        $this->pdo->setLastExecutedQuery($sql);
        $this->pdo->incrementQueryCount();

        return $return;
    }

    /**
     * Binds a value to a corresponding named or question mark placeholder in the SQL statement
     * that was use to prepare the statement. Returns a boolean value indicating success.
     *
     * @param integer $pos   Parameter identifier (for determining what to replace in the query).
     * @param mixed   $value The value to bind to the parameter.
     * @param integer $type  Explicit data type for the parameter using the PDO::PARAM_* constants. Defaults to PDO::PARAM_STR.
     *
     * @return boolean
     */
    public function bindValue($pos, $value, $type = PDO::PARAM_STR): bool
    {
        $debug = $this->pdo->getDebugSnapshot();
        $typestr = isset(self::$typeMap[$type]) ? self::$typeMap[$type] : '(default)';
        $return = parent::bindValue($pos, $value, $type);
        $valuestr = $type == PDO::PARAM_LOB ? '[LOB value]' : var_export($value, true);
        $msg = sprintf('Binding %s at position %s w/ PDO type %s', $valuestr, $pos, $typestr);

        $this->boundValues[$pos] = $value;

        $this->pdo->log($msg, null, __METHOD__, $debug);

        return $return;
    }

    /**
     * Binds a PHP variable to a corresponding named or question mark placeholder in the SQL statement
     * that was use to prepare the statement. Unlike PDOStatement::bindValue(), the variable is bound
     * as a reference and will only be evaluated at the time that PDOStatement::execute() is called.
     * Returns a boolean value indicating success.
     *
     * @param integer $pos            Parameter identifier (for determining what to replace in the query).
     * @param mixed   $value          The value to bind to the parameter.
     * @param integer $type           Explicit data type for the parameter using the PDO::PARAM_* constants. Defaults to PDO::PARAM_STR.
     * @param integer $length         Length of the data type. To indicate that a parameter is an OUT parameter from a stored procedure, you must explicitly set the length.
     * @param mixed   $driver_options
     *
     * @return boolean
     */
    public function bindParam($pos, &$value, $type = PDO::PARAM_STR, $length = 0, $driver_options = null): bool
    {
        $originalValue = $value;
        $debug = $this->pdo->getDebugSnapshot();
        $typestr = isset(self::$typeMap[$type]) ? self::$typeMap[$type] : '(default)';
        $return = parent::bindParam($pos, $value, $type, $length, $driver_options);
        $valuestr = $length > 100 ? '[Large value]' : var_export($value, true);
        $msg = sprintf('Binding %s at position %s w/ PDO type %s', $valuestr, $pos, $typestr);

        $this->boundValues[$pos] = $originalValue;

        $this->pdo->log($msg, null, __METHOD__, $debug);

        return $return;
    }
}
